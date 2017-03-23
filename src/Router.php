<?php
/**
 * Selami Router
 * PHP version 7+
 *
 * @license https://github.com/selamiphp/router/blob/master/LICENSE (MIT License)
 * @link https://github.com/selamiphp/router
 * @package router
 * @category library
 */

declare(strict_types = 1);

namespace Selami;

use FastRoute;
use InvalidArgumentException;
use UnexpectedValueException;

/**
 * Router
 *
 * This class is responsible for registering route objects,
 * determining aliases if available and finding requested route
 */
final class Router
{
    /**
     * routes array to be registered.
     * Some routes may have aliases to be used in templating system
     * Route item can be defined using array key as an alias key.
     * @var array
     */
    private $routes = [];

    /**
     * aliases array to be registered.
     * Each route item is an array has items respectively : Request Method, Request Uri, Controller/Action, Return Type.
     * @var array
     */
    private $aliases = [];

    /**
     * HTTP request Method
     * @var string
     */
    private $method;

    /**
     * Request Uri
     * @var string
     */
    private $requestedPath;

    /**
     * Default return type if not noted in the $routes
     * @var string
     */
    private $defaultReturnType;


    /**
     * Translation array.
     * Make sures about return type.
     * @var array
     */
    private static $translations = [
        'h'     => 'html',
        'html'  => 'html',
        'r'     => 'redirect',
        'redirect' => 'redirect',
        'j'     => 'json',
        'json'  => 'json',
        't'     => 'text',
        'text'  => 'text',
        'd'     => 'download',
        'download'  => 'download'
    ];

    /**
     * Valid Request Methods array.
     * Make sures about requested methods.
     * @var array
     */
    private static $validRequestMethods = [
        'GET',
        'OPTIONS',
        'HEAD',
        'POST',
        'PUT',
        'DELETE',
        'PATCH'
    ];


    /**
     * Valid Request Methods array.
     * Make sures about return type.
     * Index 0 is also default value.
     * @var array
     */
    private static $validReturnTypes = [
        'html',
        'json',
        'text',
        'redirect',
        'download'
    ];

    /**
     * Router constructor.
     * Create new router.
     *
     * @param string $defaultReturnType
     * @param string $method
     * @param string $requestedPath
     * @param string $folder
     * @throws UnexpectedValueException
     */
    public function __construct(
        string $defaultReturnType,
        string $method,
        string $requestedPath,
        string $folder = ''
    ) {
        if (!in_array($method, self::$validRequestMethods, true)) {
            $message = sprintf('%s is not valid Http request method.', $method);
            throw new UnexpectedValueException($message);
        }
        $this->method   = $method;
        $this->requestedPath     = $this->extractFolder($requestedPath, $folder);
        $this->defaultReturnType = self::$translations[$defaultReturnType] ?? self::$validReturnTypes[0];
    }

    /**
     * Remove sub folder from requestedPath if defined
     * @param string $requestPath
     * @param string $folder
     * @return string
     */
    private function extractFolder(string $requestPath, string $folder)
    {
        if (!empty($folder)) {
            $requestPath = '/' . trim(preg_replace('#^/' . $folder . '#msi', '/', $requestPath), '/');
        }
        if ($requestPath === '') {
            $requestPath = '/';
        }
        return $requestPath;
    }

    /**
     * add route to routes list
     * @param string|array requestMethods
     * @param string $route
     * @param string $action
     * @param string $returnType
     * @param string $alias
     * @throws InvalidArgumentException
     * @throws UnexpectedValueException
     */
    public function add($requestMethods, string $route, string $action, string $returnType = null, string $alias = null)
    {
        $requestMethodsGiven = is_array($requestMethods) ? (array) $requestMethods : [0 => $requestMethods];
        $returnType = $this->determineReturnType($returnType);
        foreach ($requestMethodsGiven as $requestMethod) {
            $this->checkRequestMethodParameterType($requestMethod);
            $this->checkRequestMethodIsValid($requestMethod);
            if ($alias !== null) {
                $this->aliases[$alias] = $route;
            }
            $this->routes[] = [strtoupper($requestMethod), $route, $action, $returnType];
        }
    }

    /**
     * @param string $method
     * @param array $args
     * @throws UnexpectedValueException
     * @throws InvalidArgumentException
     */
    public function __call(string $method, array $args)
    {

        $this->checkRequestMethodIsValid($method);
        $defaults = [
            null,
            null,
            $this->defaultReturnType,
            null
        ];
        list($route, $action, $returnType, $alias) = array_merge($args, $defaults);
        $this->add($method, $route, $action, $returnType, $alias);
    }

    /**
     * @param string|null $returnType
     * @return string
     */
    private function determineReturnType(string $returnType)
    {
        if ($returnType === null) {
            return $this->defaultReturnType;
        }
        return in_array($returnType, self::$validReturnTypes, true) ? $returnType : $this->defaultReturnType;
    }

    /**
     * @param string $requestMethod
     * Checks if request method is valid
     * @throws UnexpectedValueException;
     */
    private function checkRequestMethodIsValid(string $requestMethod)
    {
        if (!in_array(strtoupper($requestMethod), self::$validRequestMethods, true)) {
            $message = sprintf('%s is not valid Http request method.', $requestMethod);
            throw new UnexpectedValueException($message);
        }
    }

    /**
     * @param $requestMethod
     * @throws InvalidArgumentException
     */
    private function checkRequestMethodParameterType($requestMethod)
    {
        $requestMethodParameterType = gettype($requestMethod);
        if (!in_array($requestMethodParameterType, ['array', 'string'], true)) {
            $message = sprintf(
                'Request method must be string or array but %s given.',
                $requestMethodParameterType
            );
            throw new InvalidArgumentException($message);
        }
    }

    /**
     * Dispatch against the provided HTTP method verb and URI.
     * @return array
     */
    private function dispatcher()
    {
        $options = [
            'routeParser'   => FastRoute\RouteParser\Std::class,
            'dataGenerator' => FastRoute\DataGenerator\GroupCountBased::class,
            'dispatcher'    => FastRoute\Dispatcher\GroupCountBased::class,
            'routeCollector' => FastRoute\RouteCollector::class,
        ];
        /** @var RouteCollector $routeCollector */
        $routeCollector = new $options['routeCollector'](
            new $options['routeParser'], new $options['dataGenerator']
        );
        $this->addRoutes($routeCollector);
        return new $options['dispatcher']($routeCollector->getData());
    }

    /**
     * Define Closures for all routes that returns controller info to be used.
     * @param FastRoute\RouteCollector $route
     */
    private function addRoutes(FastRoute\RouteCollector $route)
    {
        foreach ($this->routes as $definedRoute) {
            $definedRoute[3] = $definedRoute[3] ?? $this->defaultReturnType;
            $route->addRoute(strtoupper($definedRoute[0]), $definedRoute[1], function ($args) use ($definedRoute) {
                list(,,$controller, $returnType) = $definedRoute;
                $returnType = Router::$translations[$returnType] ?? $this->defaultReturnType;
                return  ['controller' => $controller, 'returnType'=> $returnType, 'args'=> $args];
            });
        }
    }



    /**
     * Get router data that includes route info and aliases
     */
    public function getRoute()
    {
        $dispatcher = $this->dispatcher();
        $routeInfo  = $dispatcher->dispatch($this->method, $this->requestedPath);
        $routerData = [
            'route'     => $this->runDispatcher($routeInfo),
            'aliases'   => $this->aliases
        ];
        return $routerData;
    }


    /**
     * Get route info for requested uri
     * @param array $routeInfo
     * @return array $routerData
     */
    private function runDispatcher(array $routeInfo)
    {
        $routeData = $this->getRouteData($routeInfo);
        $dispatchResults = [
            FastRoute\Dispatcher::METHOD_NOT_ALLOWED => [
                'status' => 405
            ],
            FastRoute\Dispatcher::FOUND => [
                'status'  => 200
            ],
            FastRoute\Dispatcher::NOT_FOUND => [
                'status' => 404
            ]
        ];
        return array_merge($routeData, $dispatchResults[$routeInfo[0]]);
    }

    /**
     * Get routeData according to dispatcher's results
     * @param array $routeInfo
     * @return array
     */
    private function getRouteData(array $routeInfo)
    {
        if ($routeInfo[0] === FastRoute\Dispatcher::FOUND) {
            list(, $handler, $vars) = $routeInfo;
            return $handler($vars);
        }
        return [
            'status'        => 200,
            'returnType'    => 'html',
            'definedRoute'  => null,
            'args'          => []
        ];
    }
}
