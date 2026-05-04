# Frootbox REST API

A lightweight, attribute-based REST API framework for PHP.

This package provides a simple way to build versioned REST APIs with support for multiple authentication methods like API keys, Bearer tokens, Basic Auth, and custom client credentials.

---

## ✨ Features

- Attribute-based routing (OpenAPI compatible)
- API versioning via namespace (`V1`, `V2`, ...)
- Multiple authentication methods:
    - API Key
    - Bearer (JWT)
    - Basic Auth
    - Client credentials
- Dependency Injection support (PHP-DI)
- Automatic route discovery
- Named route parameters (`{id}`, `{int:id}`)
- JSON response handling

---

## 📦 Installation

~~~markdown
composer require frootbox/restapi
~~~

---

## 🚀 Getting Started

### 1. Create a Server instance

~~~markdown
use Frootbox\\RestApi\\Server;
use DI\\Container;

$container = new Container();

$server = new Server(
    clientRepository: $clientRepository, // implements ClientRepositoryInterface
    baseUriRegex: '#^/api/v(?P<Version>[0-9]+)(?P<Path>/.*)$#',
    controllerDirectory: __DIR__ . '/Controller',
    namespace: 'App\\\\Controller',
    container: $container,
    hashKey: 'your-secret-key'
);

$server->execute();
~~~

---

## 📁 Controller Structure

Controllers must follow a versioned namespace structure:

~~~markdown
src/
└── Controller/
    └── V1/
        └── UserController.php
~~~

---

## 🧩 Example Controller

~~~markdown
namespace App\\Controller\\V1;

use OpenApi\\Attributes as OA;
use Frootbox\\RestApi\\Attribute\\Auth;
use Frootbox\\RestApi\\Attribute\\ApiKey;
use Frootbox\\RestApi\\Response\\Payload;

class UserController
{
    #[OA\\Get(path: '/users/{int:id}')]
    #[Auth(type: new ApiKey())]
    public function getUser(int $id): Payload
    {
        return new Payload([
            'id' => $id,
            'name' => 'John Doe'
        ]);
    }
}
~~~

---

## 🔐 Authentication

You can define one or multiple authentication methods per endpoint:

~~~markdown
#[Auth(type: new ApiKey())]
#[Auth(type: new Bearer())]
~~~

### Supported Auth Methods

#### API Key

Send via header:

~~~markdown
x-api-key: your-api-key
~~~

---

#### Bearer Token (JWT)

~~~markdown
Authorization: Bearer <token>
~~~

---

#### Basic Auth

~~~markdown
Authorization: Basic base64(clientId:clientSecret)
~~~

---

#### Client Credentials (GET or Basic)

~~~markdown
GET /endpoint?client_id=xxx&client_secret=yyy
~~~

or via Basic Auth.

---

## 🧠 Client Validation

You must provide a repository implementing:

~~~markdown
Frootbox\\RestApi\\Interface\\ClientRepositoryInterface
~~~

Example:

~~~markdown
class ClientRepository implements ClientRepositoryInterface
{
    public function validate(string $clientId, string $clientSecret): void
    {
        if ($clientId !== 'test' || $clientSecret !== 'secret') {
            throw new \\Exception('Invalid client credentials');
        }
    }

    public function validateApiKey(string $apiKey): void
    {
        if ($apiKey !== 'abc123') {
            throw new \\Exception('Invalid API key');
        }
    }
}
~~~

---

## 🔄 Versioning

API version is extracted from the URL:

~~~markdown
/api/v1/users/1
~~~

Your controllers must match the version namespace:

~~~markdown
namespace App\\Controller\\V1;
~~~

---

## 🧾 Route Parameters

### Integer parameter

~~~markdown
/users/{int:id}
~~~

### String parameter

~~~markdown
/users/{slug}
~~~

### ULID parameter

~~~markdown
/users/{ulid:id}
~~~

### OpenAPI parameter pattern

~~~php
#[OA\Get(path: '/users/{id}')]
#[OA\Parameter(
    name: 'id',
    in: 'path',
    required: true,
    schema: new OA\Schema(type: 'string', pattern: '^[0-9A-HJKMNP-TV-Z]{26}$'),
)]
~~~

Static routes are matched before dynamic routes, so `/users/search` is preferred over `/users/{id}` regardless of reflection order.

Parameters are automatically injected into the method.

---

## 📤 Responses

All responses must return:

~~~markdown
Frootbox\\RestApi\\Response\\Payload
~~~

Example:

~~~markdown
return new Payload([
    'success' => true
]);
~~~

---

## ⚙️ Dependency Injection

Controllers are resolved via the provided DI container:

~~~markdown
$container->call([$controller, $method]);
~~~

You can inject services directly into controller methods:

~~~markdown
public function getUser(UserService $service, int $id)
~~~

---

## 🔧 Hooks

### Token decoding hook

~~~markdown
$server = new Server(..., onDecodeToken: function ($token) {
    // custom logic
});
~~~

### Client validation hook

~~~markdown
$server = new Server(..., onValidateClient: function ($clientId) {
    // custom logic
});
~~~

---

## ⚠️ Important Notes

- Always use HTTPS when transmitting credentials
- API keys should be treated like passwords
- Bearer tokens are validated using HS256
- Route matching is case-insensitive

---

## 📄 License

MIT
"""
