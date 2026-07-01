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
    protected array $activeAuths = [];

    public function __construct(
        protected Interface\ClientRepositoryInterface $clientRepository,
        protected string $baseUriRegex,
        protected string $controllerDirectory,
        protected string $namespace,
        protected \DI\Container $container,
        protected string $hashKey,
        protected $onDecodeToken = null,
        protected $onValidateClient = null,
        protected bool $allowClientCredentialsInQuery = true,
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

                $apiScopes = $this->getApiScopes($attributes);

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
                        'apiScopes' => $apiScopes,
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
     * @return array<int, string>
     */
    protected function getApiScopes(array $attributes): array
    {
        $scopes = [];

        foreach ($attributes as $attribute) {

            if ($attribute->getName() != 'Frootbox\RestApi\Attribute\ApiScope') {
                continue;
            }

            $arguments = $attribute->getArguments();

            foreach ($arguments as $argument) {

                if (is_array($argument)) {
                    $scopes = array_merge($scopes, $argument);
                    continue;
                }

                $scopes[] = $argument;
            }
        }

        return array_values(array_unique(array_filter($scopes)));
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

            if (
                (empty($pattern) || \OpenApi\Generator::isDefault($pattern))
                && !empty($arguments['schema'])
                && is_object($arguments['schema'])
                && !\OpenApi\Generator::isDefault($arguments['schema'])
            ) {
                $pattern = $arguments['schema']->pattern ?? null;
            }

            if (is_string($pattern) && $pattern !== '' && !\OpenApi\Generator::isDefault($pattern)) {
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
                throw new \Exception('Route ' . $requestedPath . ' does not exist');
            }

            if (empty($route['auths'])) {
                throw new \Exception('Auth method missing.');
            }

            $this->activeAuths = $route['auths'];

            $authed = false;
            $authError = null;
            $token = null;

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

                        if (empty($_SERVER['HTTP_AUTHORIZATION']) or stripos($_SERVER['HTTP_AUTHORIZATION'], 'Bearer ') !== 0) {
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

                        [ $clientId, $clientSecret ] = $this->getClientCredentials();

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

            $this->assertApiScopesAllowed($token, $route['apiScopes'] ?? []);

            // Get controller
            $controller = $this->container->get($route['class']);

            // Perform controller action
            $response = $this->container->call([ $controller, $route['method'] ]);

            $this->sendResponseHeaders($response);

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

    protected function assertApiScopesAllowed(?\Frootbox\RestApi\Token $token, array $requiredScopes): void
    {
        if (empty($requiredScopes)) {
            return;
        }

        if ($token === null) {
            throw new \Frootbox\RestApi\Exception\Forbidden('Token does not provide the required scope.');
        }

        $tokenScopes = $token->getPayload('scope') ?? [];

        if (is_string($tokenScopes)) {
            $tokenScopes = preg_split('/\s+/', trim($tokenScopes));
        }

        if (!is_array($tokenScopes)) {
            $tokenScopes = [];
        }

        $tokenScopes = array_values(array_filter(array_unique($tokenScopes)));

        if (empty(array_diff($requiredScopes, $tokenScopes))) {
            return;
        }

        throw new \Frootbox\RestApi\Exception\Forbidden('Token does not provide the required scope.');
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

        if ($statusCode === 401) {
            $this->sendAuthenticateHeader();
        }

        die(json_encode([
            'error' => [
                'code' => $code ?: 'error',
                'message' => $message ?: 'Unexpected API error.',
            ],
        ]));
    }

    protected function sendAuthenticateHeader(): void
    {
        if (in_array(\Frootbox\RestApi\Attribute\Bearer::class, $this->activeAuths, true)) {
            header('WWW-Authenticate: Bearer error="invalid_token"', false);

            return;
        }

        if (
            in_array(\Frootbox\RestApi\Attribute\Client::class, $this->activeAuths, true)
            || in_array(\Frootbox\RestApi\Attribute\BasicAuth::class, $this->activeAuths, true)
        ) {
            header('WWW-Authenticate: Basic realm="api"', false);
        }
    }

    protected function getClientCredentials(): array
    {
        $clientId = null;
        $clientSecret = null;

        if (!empty($_SERVER['PHP_AUTH_USER'])) {
            $clientId = $_SERVER['PHP_AUTH_USER'];
        }

        if (!empty($_SERVER['PHP_AUTH_PW'])) {
            $clientSecret = $_SERVER['PHP_AUTH_PW'];
        }

        if (empty($clientId) && empty($clientSecret)) {
            $authorization = $_SERVER['HTTP_AUTHORIZATION'] ?? '';

            if (stripos($authorization, 'Basic ') === 0) {
                $decoded = base64_decode(substr($authorization, 6), true);

                if ($decoded !== false && str_contains($decoded, ':')) {
                    [ $clientId, $clientSecret ] = explode(':', $decoded, 2);
                }
            }
        }

        if (empty($clientId) && empty($clientSecret)) {
            try {
                $payload = new Payload();

                $clientId = $payload->getBodyParameter('client_id');
                $clientSecret = $payload->getBodyParameter('client_secret');
            }
            catch (\Frootbox\RestApi\Exception\InvalidInput) {
                // Keep client authentication backwards compatible for endpoints that do not use parsed bodies.
            }
        }

        if ($this->allowClientCredentialsInQuery) {
            if (empty($clientId) && !empty($_GET['client_id'])) {
                $clientId = $_GET['client_id'];
            }

            if (empty($clientSecret) && !empty($_GET['client_secret'])) {
                $clientSecret = $_GET['client_secret'];
            }
        }

        return [
            $clientId !== null ? (string) $clientId : null,
            $clientSecret !== null ? (string) $clientSecret : null,
        ];
    }

    protected function sendResponseHeaders(\Frootbox\RestApi\Response\ResponseInterface $response): void
    {
        if (method_exists($response, 'getStatusCode')) {
            http_response_code($response->getStatusCode());
        }

        if (!method_exists($response, 'getHeaders')) {
            return;
        }

        foreach ($response->getHeaders() as $name => $value) {
            header($name . ': ' . $value);
        }
    }
}
