# Selami Router

[![Build Status](https://api.travis-ci.org/selamiphp/router.svg?branch=master)](https://travis-ci.org/selamiphp/router) [![Coverage Status](https://coveralls.io/repos/github/selamiphp/router/badge.svg?branch=master)](https://coveralls.io/github/selamiphp/router?branch=master) [![Latest Stable Version](https://poser.pugx.org/selami/router/v/stable)](https://packagist.org/packages/selami/router) [![Total Downloads](https://poser.pugx.org/selami/router/downloads)](https://packagist.org/packages/selami/router) [![Latest Unstable Version](https://poser.pugx.org/selami/router/v/unstable)](https://packagist.org/packages/selami/router) [![License](https://poser.pugx.org/selami/router/license)](https://packagist.org/packages/selami/router)
[![Scrutinizer Code Quality](https://scrutinizer-ci.com/g/selamiphp/router/badges/quality-score.png?b=master)](https://scrutinizer-ci.com/g/selamiphp/router/) [![Codacy Badge](https://api.codacy.com/project/badge/Grade/748983d7d23e4c26b13dd76fc781cdc8)](https://www.codacy.com/app/mehmet/framework?utm_source=github.com&amp;utm_medium=referral&amp;utm_content=selamiphp/router&amp;utm_campaign=Badge_Grade)


Router and Dispatcher built on top of [nikic/FastRoute](https://github.com/nikic/FastRoute) that returns controller class name to be instantiated, desired content-type and route aliases if defined.


## Installation

Install this library using composer:

```bash
$ composer require selami/router
```
## Example
Get global values.

```php
<?php
declare(strict_types=1);

$request = new PSR7ServerRequest(); // Let's say this class implements PSR7 ServerRequestInterface
$response = new PSR7ServerResponse(); // Let's say this class implements PSR7 ResponseInterface
$defaultReturnType  = Selami\Router::JSON; // Possible values: Selami\Router::HTML, Selami\Router::JSON, Selami\Router::TEXT, Selami\Router::CUSTOM, Selami\Router::REDIRECT, Selami\Router::DOWNLOAD. To be used to send output.
$requestMethod      = 'GET';        // i.e. $_SERVER['REQUEST_METHOD']
$requestedUri       = '/user/12/inbox'; // i.e. $_SERVER['REQUEST_URI']

```
Create Selami\Router Instance.

```php
$router = new Selami\Router(
    $defaultReturnType,
    $requestMethod,
    $requestedUri
);
```
Add routes that expect HTTP request methods. $route variable uses nikic/FastRoute's route syntax.

```php
use Selami\Router\Router;

$route = '/';
$action = Controllers\Home::class;
$returnType = Router::HTML;
$alias = 'home';

$router->get(
    $route,         // required
    $action,        // required
    $returnType,    // optional, default: $defaultReturnType
    $alias          // optional, default null
);

$router->post('/login', Controllers\Login::class, Router::REDIRECT);
$router->get('/dashboard', Controllers\Dashboard::class, Router::HTML, 'dashboard');
$router->get('/api/user/{id}', Controllers\Api\Users::class, Router::JSON);
$router->get('/user/{id:\d+}/{box}', Controllers\Api\Users\Inbox::class, Router::HTML, 'user_home');

```
Get requested route info and aliases.

```php

$routeInfo = $router->getRoute(); // Returns Selami\Router\Route object.

```
Now you can call your controller.

```php
$controller = new {$routeInfo->getController()}($routeInfo->getUriParameters());

$outputMethod = 'return' . ucfirst($routeInfo->getReturnType());

echo $controller->$outputMethod($request, $response);

```

Our sample Controller class can be like:
```php
<?php

declare(strict_types=1);

namespace Controller\Api\Users;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use BaseController;

class Inbox extends BaseController {
    
    private $uriParameters;
    
    public function __construct($uriParameters) 
    {
        parent::__construct();
        $this->uriParameters = $uriParameters;
    }
    
    public function returnHTML(ServerRequestInterface $request, ResponseInterface $response)
    {
        $response = $this->response->withHeader('Content-Type', 'text/html');
        $response->getBody()->write('Your user id is: ' . $this->args['id'] . '. You are viewing your '. $this->uriParameters['box']);
        return $response->output();
    }
}

```

## $route value syntax when adding get, post, put etc routes.

See [FastRoute documentation](https://github.com/nikic/FastRoute).
