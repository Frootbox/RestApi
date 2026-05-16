<?php
/**
 * @author Jan Habbo Brüning <jan.habbo.bruening@gmail.com>
 *
 * @noinspection PhpUnnecessaryLocalVariableInspection
 * @noinspection PhpFullyQualifiedNameUsageInspection
 */

declare(strict_types=1);

namespace Frootbox\RestApi;

class Server
{
    protected array $routes = [];

    public function __construct(
        protected Interface\ClientRepositoryInterface $clientRepository,
        protected string $baseUriRegex,
        protected string $controllerDirectory,
        protected string $namespace,
        protected \DI\Container $container,
        protected string $hashKey,
        protected $onDecodeToken = null,
        protected $onValidateClient = null,
    )
    {
        $routes = [
            'Get' => [],
            'Post' => [],
            'Put' => [],
            'Delete' => [],
            'Patch' => [],
        ];

        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($controllerDirectory, \FilesystemIterator::SKIP_DOTS)) as $file) {

            if ($file->getFilename() != 'Controller.php') {
                continue;
            }

            $path = str_replace($controllerDirectory, '', $file->getPathname());
            $path = substr($path, 0, -4);

            $controllerClass = $namespace . str_replace('/', '\\', $path);

            // Build reflection class
            $reflection = new \ReflectionClass($controllerClass);

            // Extract version
            if (!preg_match('#\\\\V([0-9]+)\\\\#', $reflection->getName(), $match)) {
                continue;
            }

            $version = (int) $match[1];

            foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {

                if ($method->class != $reflection->getName()) {
                    continue;
                }

                $attributes = $method->getAttributes();

                // Get auth
                $auths = [];

                foreach ($attributes as $attribute) {

                    if ($attribute->getName() == 'Frootbox\RestApi\Attribute\Auth') {
                        $auths[] = get_class($attribute->getArguments()['type']);
                    }
                }

                if (empty($auths)) {
                    $auths[] = \Frootbox\RestApi\Attribute\Bearer::class;
                }

                foreach ($attributes as $attribute) {

                    if (empty($attribute->getArguments()['path'])) {
                        continue;
                    }

                    // Extract route
                    $route = $attribute->getArguments()['path'];
                    $parameterPatterns = $this->getParameterPatterns($attributes);
                    $compiledRoute = $this->compileRoute($route, $parameterPatterns);

                    // Extract http-method
                    $httpMethod = str_replace('OpenApi\\Attributes\\', '', $attribute->getName());

                    // Add route to stack
                    $routes[$httpMethod][] = [
                        'route' => $route,
                        'regex' => $compiledRoute['regex'],
                        'priority' => $compiledRoute['priority'],
                        'version' => $version,
                        'httpMethod' => $httpMethod,
                        'method' => $method->getName(),
                        'class' => $controllerClass,
                        'auths' => $auths,
                    ];
                }
            }
        }

        foreach ($routes as &$methodRoutes) {
            usort($methodRoutes, static function (array $left, array $right): int {
                return $right['priority'] <=> $left['priority'];
            });
        }
        unset($methodRoutes);

        $this->routes = $routes;
    }

    /**
     * @param \ReflectionAttribute[] $attributes
     * @return array<string, string>
     */
    protected function getParameterPatterns(array $attributes): array
    {
        $patterns = [];

        foreach ($attributes as $attribute) {

            if ($attribute->getName() != 'OpenApi\Attributes\Parameter') {
                continue;
            }

            $arguments = $attribute->getArguments();

            if (($arguments['in'] ?? null) != 'path' || empty($arguments['name'])) {
                continue;
            }

            $pattern = $arguments['pattern'] ?? null;

            if (empty($pattern) && !empty($arguments['schema']) && is_object($arguments['schema'])) {
                $pattern = $arguments['schema']->pattern ?? null;
            }

            if (is_string($pattern) && $pattern !== '') {
                $patterns[$arguments['name']] = $this->normalizeRoutePattern($pattern);
            }
        }

        return $patterns;
    }

    /**
     * @return array{regex: string, priority: int}
     */
    protected function compileRoute(string $route, array $parameterPatterns): array
    {
        $priority = 0;
        $parameterCount = 0;
        $offset = 0;
        $regex = '';

        preg_match_all('#{(?:(int|ulid):)?([A-Za-z_][A-Za-z0-9_]*)}#', $route, $matches, \PREG_SET_ORDER | \PREG_OFFSET_CAPTURE);

        foreach ($matches as $match) {
            $placeholder = $match[0][0];
            $placeholderOffset = $match[0][1];
            $type = $match[1][0] ?: null;
            $name = $match[2][0];

            $regex .= preg_quote(substr($route, $offset, $placeholderOffset - $offset), '#');

            $pattern = $parameterPatterns[$name] ?? null;

            if ($pattern === null) {
                $pattern = match ($type) {
                    'int' => '[0-9]+',
                    'ulid' => '[0-9A-HJKMNP-TV-Z]{26}',
                    default => '[^\/]+',
                };
            }

            if ($pattern === '[^\/]+') {
                $priority += 1;
            }
            else {
                $priority += 10;
            }

            $regex .= '(?P<' . $name . '>' . $pattern . ')';
            $offset = $placeholderOffset + strlen($placeholder);
            ++$parameterCount;
        }

        $regex .= preg_quote(substr($route, $offset), '#');

        $staticSegmentCount = 0;

        foreach (explode('/', trim($route, '/')) as $segment) {

            if ($segment !== '' && !preg_match('#^{.*}$#', $segment)) {
                ++$staticSegmentCount;
            }
        }

        $priority += ($staticSegmentCount * 100) - $parameterCount;

        return [
            'regex' => '#^' . $regex . '$#i',
            'priority' => $priority,
        ];
    }

    protected function normalizeRoutePattern(string $pattern): string
    {
        if (str_starts_with($pattern, '^')) {
            $pattern = substr($pattern, 1);
        }

        if (str_ends_with($pattern, '$')) {
            $pattern = substr($pattern, 0, -1);
        }

        return $pattern;
    }

    /**
     * Execute
     * @return never
     */
    public function execute(): never
    {
        try {

            $request = explode('?', $_SERVER['REQUEST_URI'])[0];

            if (!preg_match($this->baseUriRegex, $request, $match)) {
                throw new \Exception('Invalid request URI.');
            }

            $requestedVersion = $match['Version'];
            $requestedPath = $match['Path'];

            $httpMethod = ucfirst(strtolower($_SERVER['REQUEST_METHOD']));

            $route = null;

            foreach ($this->routes[$httpMethod] as $routeData) {

                if ($requestedVersion != $routeData['version']) {
                    continue;
                }

                if (!preg_match($routeData['regex'], '/' . $requestedPath, $matches)) {
                    continue;
                }

                foreach ($matches as $key => $value) {

                    if (preg_match('#^[0-9]+$#', (string) $key)) {
                        continue;
                    }

                    $_GET[$key] = $value;
                }

                $route = $routeData;

                break;
            }

            if (empty($route)) {
                throw new \Exception('Route does not exist');
            }

            if (empty($route['auths'])) {
                throw new \Exception('Auth method missing.');
            }

            $authed = false;
            $authError = null;

            foreach ($route['auths'] as $auth) {

                try {

                    if ($auth == \Frootbox\RestApi\Attribute\BasicAuth::class) {

                        if (empty($_SERVER['PHP_AUTH_USER']) && empty($_SERVER['PHP_AUTH_PW'])) {
                            continue;
                        }

                        if (empty($_SERVER['PHP_AUTH_USER']) || empty($_SERVER['PHP_AUTH_PW'])) {
                            throw new \Exception('Auth information missing.');
                        }

                        // Validate client
                        $this->clientRepository->validate(
                            clientId: $_SERVER['PHP_AUTH_USER'],
                            clientSecret: $_SERVER['PHP_AUTH_PW'],
                        );

                        if (is_callable($this->onValidateClient)) {
                            call_user_func($this->onValidateClient, $_SERVER['PHP_AUTH_USER']);
                        }

                        $authed = true;
                    }
                    elseif ($auth == \Frootbox\RestApi\Attribute\Bearer::class) {

                        if (empty($_SERVER['HTTP_AUTHORIZATION']) or !str_starts_with($_SERVER['HTTP_AUTHORIZATION'], 'Bearer ')) {
                            continue;
                        }

                        $jwt = substr($_SERVER['HTTP_AUTHORIZATION'], 7);
                        $decoded = \Firebase\JWT\JWT::decode($jwt, new \Firebase\JWT\Key($this->hashKey, 'HS256'));

                        $token = new \Frootbox\RestApi\Token(payload: json_decode(json_encode($decoded), true));

                        if (is_callable($this->onDecodeToken)) {
                            call_user_func($this->onDecodeToken, $token);
                        }

                        $authed = true;
                    }
                    elseif ($auth == \Frootbox\RestApi\Attribute\None::class) {
                        $authed = true;
                    }
                    elseif ($auth == \Frootbox\RestApi\Attribute\Client::class) {

                        $clientId = null;
                        $clientSecret = null;

                        if (!empty($_SERVER['PHP_AUTH_USER'])) {
                            $clientId = $_SERVER['PHP_AUTH_USER'];
                        }

                        if (!empty($_SERVER['PHP_AUTH_PW'])) {
                            $clientSecret = $_SERVER['PHP_AUTH_PW'];
                        }

                        if (!empty($_GET['client_id'])) {
                            $clientId = $_GET['client_id'];
                        }

                        if (!empty($_GET['client_secret'])) {
                            $clientSecret = $_GET['client_secret'];
                        }

                        if (empty($clientSecret) && empty($clientId)) {
                            continue;
                        }

                        if (empty($clientSecret)) {
                            throw new \Exception('Client secret missing.');
                        }

                        if (empty($clientId)) {
                            throw new \Exception('Client ID missing.');
                        }

                        // Validate client
                        $this->clientRepository->validate(
                            clientId: $clientId,
                            clientSecret: $clientSecret,
                            onValidateClient: $this->onValidateClient,
                        );

                        $authed = true;
                    }
                    elseif ($auth == \Frootbox\RestApi\Attribute\ApiKey::class) {

                        // Obtain api key
                        $headers = function_exists('getallheaders') ? getallheaders() : [];
                        $apiKey = $headers['x-api-key']
                            ?? $headers['X-API-Key']
                            ?? $_SERVER['HTTP_X_API_KEY']
                            ?? null;

                        if (empty($apiKey)) {
                            continue;
                        }

                        // Validate api key
                        $this->clientRepository->validateApiKey(
                            apiKey: $apiKey,
                            onValidateClient: $this->onValidateClient,
                        );

                        $authed = true;
                    }
                    else {
                        throw new \Frootbox\RestApi\Exception\NotAuthed('Unknown auth: ' . $auth);
                    }

                    break;
                }
                catch (\Throwable $e) {
                    $authError = $e->getMessage();
                }
            }

            if (!$authed) {
                throw new \Frootbox\RestApi\Exception\NotAuthed($authError ?? 'Not authed.');
            }

            // Get controller
            $controller = $this->container->get($route['class']);

            // Perform controller action
            $response = $this->container->call([ $controller, $route['method'] ]);

            header('Content-Type: application/json; charset=utf-8');
            die($response->tojson());
        }
        catch (\Frootbox\RestApi\Exception\AbstractException $exception) {

            $this->respondWithError(
                statusCode: $exception->getHttpStatusCode(),
                message: $exception->getMessage() ?: 'Unknown Error: ' . get_class($exception),
            );
        }
        catch (\Frootbox\Exceptions\Interfaces\HttpException $exception) {

            $this->respondWithError(
                statusCode: $exception->getHttpStatusCode(),
                message: $exception->hasPublicMessage() ? $exception->getMessage() : null,
                code: $exception->getErrorCode(),
            );
        }
        catch (\Throwable $exception) {

            $this->respondWithError(
                statusCode: 500,
                message: $exception->getMessage() ?: 'Unknown Error: ' . get_class($exception),
            );
        }
    }

    /**
     * @param int $statusCode
     * @param string|null $message
     * @param string|null $code
     * @return never
     */
    protected function respondWithError(int $statusCode, ?string $message, ?string $code = null): never
    {
        http_response_code($statusCode);
        header('Content-Type: application/json; charset=utf-8');

        die(json_encode([
            'error' => [
                'code' => $code ?: 'error',
                'message' => $message ?: 'Unexpected API error.',
            ],
        ]));
    }
}
