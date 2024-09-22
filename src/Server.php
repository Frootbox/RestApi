<?php
/**
 *
 */

namespace Frootbox\RestApi;

class Server
{
    protected array $routes = [];

    public function __construct(
        protected Interfaces\ClientRepositoryInterface $clientRepository,
        protected string $baseUriRegex,
        protected string $controllerDirectory,
        protected string $namespace,
        protected \DI\Container $container,
        protected string $hashKey,
        protected $onDecodeToken = null,
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
            preg_match('#\\\\V([0-9]+)\\\\#', $reflection->getName(), $match);
            $version = (int) $match[1];

            foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {

                if ($method->class != $reflection->getName()) {
                    continue;
                }

                $attributes = $method->getAttributes();

                // Get auth
                $auth = \Frootbox\RestApi\Attributes\Bearer::class;

                foreach ($attributes as $attribute) {

                    if ($attribute->getName() == 'Frootbox\RestApi\Attributes\Auth') {
                        $auth = get_class($attribute->getArguments()['type']);
                    }
                }

                foreach ($attributes as $attribute) {

                    if (empty($attribute->getArguments()['path'])) {
                        continue;
                    }

                    // Extract route
                    $route = $attribute->getArguments()['path'];

                    // Generate regex
                    $regex = $route;
                    $regex = '#^' . preg_replace_callback('#{(.*?)}#', function($data) {
                        return '(?P<' . $data[1] . '>[^\/]*)';
                    }, $regex) . '$#i';

                    // Extract http-method
                    $httpMethod = str_replace('OpenApi\\Attributes\\', '', $attribute->getName());

                    // Add route to stack
                    $routes[$httpMethod][] = [
                        'route' => $route,
                        'regex' => $regex,
                        'version' => $version,
                        'httpMethod' => $httpMethod,
                        'method' => $method->getName(),
                        'class' => $controllerClass,
                        'auth' => $auth,
                    ];
                }
            }
        }

        $this->routes = $routes;
    }

    /**
     * Execute
     * @return never
     */
    public function execute(): never
    {
        try {

            $request = explode('?', $_SERVER['REQUEST_URI'])[0];
            preg_match($this->baseUriRegex, $request, $match);

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

                    if (preg_match('#^[0-9]+$#', $key)) {
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

            if (empty($route['auth'])) {
                throw new \Exception('Auth method missing.');
            }

            if ($route['auth'] == \Frootbox\RestApi\Attributes\Bearer::class) {

                $jwt = substr($_SERVER['HTTP_AUTHORIZATION'], 7);
                $decoded = \Firebase\JWT\JWT::decode($jwt, new \Firebase\JWT\Key($this->hashKey, 'HS256'));

                $token = new \Frootbox\RestApi\Token(payload: json_decode(json_encode($decoded), true));

                if (is_callable($this->onDecodeToken)) {
                    call_user_func($this->onDecodeToken, $token);
                }
            }
            elseif ($route['auth'] == \Frootbox\RestApi\Attributes\Client::class) {

                // Validate client
                $this->clientRepository->validate(
                    clientId: $_GET['client_id'],
                    clientSecret: $_GET['client_secret'],
                );
            }
            else {
                throw new \Exception('Unknown auth: ' . $route['auth']);
            }

            // Get controller and method
            $controller = new $routeData['class'];

            $response = $this->container->call([ $controller, $routeData['method'] ]);

            header('Content-Type: application/json; charset=utf-8');
            die($response->tojson());
        }
        catch (\Exception $exception) {
            http_response_code(500);
            die($exception->getMessage());
        }
    }
}
