<?php
/**
 * Selami Router
 * PHP version 7.1+
 *
 * @category Library
 * @package  Router
 * @author   Mehmet Korkmaz <mehmet@mkorkmaz.com>
 * @license  https://github.com/selamiphp/router/blob/master/LICENSE (MIT License)
 * @link     https://github.com/selamiphp/router
 */

declare(strict_types = 1);

namespace Selami;

use FastRoute;
use InvalidArgumentException;
use UnexpectedValueException;
use RuntimeException;

/**
 * Router
 *
 * This class is responsible for registering route objects,
 * determining aliases if available and finding requested route
 */
final class Router
{
    const HTML = 1;
    const JSON = 2;
    const TEXT = 3;
    const XML = 4;
    const REDIRECT = 5;
    const DOWNLOAD = 6;
    const CUSTOM = 7;

    const OPTIONS = 'OPTIONS';
    const HEAD = 'HEAD';
    const GET = 'GET';
    const POST = 'POST';
    const PUT = 'PUT';
    const DELETE = 'DELETE';
    const PATCH = 'PATCH';

    /**
     * Routes array to be registered.
     * Some routes may have aliases to be used in templating system
     * Route item can be defined using array key as an alias key.
     * Each route item is an array has items respectively: Request Method, Request Uri, Controller/Action, Return Type.
     *
     * @var array
     */
    private $routes = [];

    /**
     * Aliases array to be registered.
     *
     * @var array
     */
    private $aliases = [];

    /**
     * HTTP request Method
     *
     * @var string
     */
    private $method;

    /**
     * Request Uri
     *
     * @var string
     */
    private $requestedPath;

    /**
     * Default return type if not noted in the $routes
     *
     * @var string
     */
    private $defaultReturnType;

    /**
     * @var null|string
     */
    private $cachedFile;

    /**
     * @var array
     */
    private $routerClosures = [];

    /**
     * Valid Request Methods array.
     * Make sures about requested methods.
     *
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
     * Router constructor.
     * Create new router.
     *
     * @param  int    $defaultReturnType
     * @param  string $method
     * @param  string $requestedPath
     * @param  string $folder
     * @param  string $cachedFile
     * @throws UnexpectedValueException
     */
    public function __construct(
        int $defaultReturnType,
        string $method,
        string $requestedPath,
        string $folder = '',
        ?string $cachedFile = null
    ) {
        if (!in_array($method, self::$validRequestMethods, true)) {
            $message = sprintf('%s is not valid Http request method.', $method);
            throw new UnexpectedValueException($message);
        }
        $this->method = $method;
        $this->requestedPath = $this->extractFolder($requestedPath, $folder);
        $this->defaultReturnType = ($defaultReturnType >=1 && $defaultReturnType <=7) ? $defaultReturnType : self::HTML;
        $this->cachedFile = $cachedFile;
    }

    /**
     * Remove sub folder from requestedPath if defined
     *
     * @param  string $requestPath
     * @param  string $folder
     * @return string
     */
    private function extractFolder(string $requestPath, string $folder) : string
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
     * Add route to routes list
     *
     * @param  string|array requestMethods
     * @param  string                      $route
     * @param  string                      $action
     * @param  int                         $returnType
     * @param  string                      $alias
     * @throws InvalidArgumentException
     * @throws UnexpectedValueException
     */
    public function add(
        $requestMethods,
        string $route,
        string $action,
        ?int $returnType = null,
        ?string $alias = null
    ) : void {
    
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
     * @param array  $args
     * @throws UnexpectedValueException
     * @throws InvalidArgumentException
     */
    public function __call(string $method, array $args) : void
    {
        $this->checkRequestMethodIsValid($method);
        $defaults = [
            null,
            null,
            $this->defaultReturnType,
            null
        ];
        [$route, $action, $returnType, $alias] = array_merge($args, $defaults);
        $this->add($method, $route, $action, $returnType, $alias);
    }

    /**
     * @param int|null $returnType
     * @return int
     */
    private function determineReturnType(?int $returnType) : int
    {
        if ($returnType === null) {
            return $this->defaultReturnType;
        }
        return ($returnType >=1 && $returnType <=7) ? $returnType : self::HTML;
    }

    /**
     * @param string $requestMethod
     * Checks if request method is valid
     * @throws UnexpectedValueException;
     */
    private function checkRequestMethodIsValid(string $requestMethod) : void
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
    private function checkRequestMethodParameterType($requestMethod) : void
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
     *
     * @return FastRoute\Dispatcher
     * @throws RuntimeException;
     */
    private function dispatcher() : FastRoute\Dispatcher
    {
        $this->setRouteClosures();
        if ($this->cachedFile !== null && file_exists($this->cachedFile)) {
            return $this->cachedDispatcher();
        }
        return $this->simpleDispatcher();
    }

    private function simpleDispatcher() : FastRoute\Dispatcher\GroupCountBased
    {
        /**
         * @var \FastRoute\RouteCollector $routeCollector
         */
        $routeCollector = new FastRoute\RouteCollector(
            new FastRoute\RouteParser\Std, new FastRoute\DataGenerator\GroupCountBased
        );
        $this->addRoutes($routeCollector);
        $this->createCachedRoute($routeCollector);
        return new FastRoute\Dispatcher\GroupCountBased($routeCollector->getData());
    }

    private function createCachedRoute($routeCollector ) : void
    {
        if ($this->cachedFile !== null && !file_exists($this->cachedFile)) {
            /**
             * @var FastRoute\RouteCollector $routeCollector
             */
            $dispatchData = $routeCollector->getData();
            file_put_contents($this->cachedFile, '<?php return ' . var_export($dispatchData, true) . ';');
        }
    }

    /**
     * @return FastRoute\Dispatcher\GroupCountBased
     * @throws RuntimeException
     */
    private function cachedDispatcher() : FastRoute\Dispatcher\GroupCountBased
    {
        $dispatchData = include $this->cachedFile;
        if (!is_array($dispatchData)) {
            throw new RuntimeException('Invalid cache file "' . $this->cachedFile . '"');
        }
        return new FastRoute\Dispatcher\GroupCountBased($dispatchData);
    }

    /**
     * Define Closures for all routes that returns controller info to be used.
     *
     * @param FastRoute\RouteCollector $route
     */
    private function addRoutes(FastRoute\RouteCollector $route) : void
    {
        $routeIndex=0;
        foreach ($this->routes as $definedRoute) {
            $definedRoute[3] = $definedRoute[3] ?? $this->defaultReturnType;
            $routeName = 'routeClosure'.$routeIndex;
            $route->addRoute(strtoupper($definedRoute[0]), $definedRoute[1], $routeName);
            $routeIndex++;
        }
    }
    private function setRouteClosures() : void
    {
        $routeIndex=0;
        foreach ($this->routes as $definedRoute) {
            $definedRoute[3] = $definedRoute[3] ?? $this->defaultReturnType;
            $routeName = 'routeClosure'.$routeIndex;
            [$requestMedhod, $url, $controller, $returnType] = $definedRoute;
            $returnType = ($returnType >=1 && $returnType <=7) ? $returnType : $this->defaultReturnType;
            $this->routerClosures[$routeName]= function ($args) use ($controller, $returnType) {
                return  ['controller' => $controller, 'returnType'=> $returnType, 'args'=> $args];
            };
            $routeIndex++;
        }
    }

    /**
     * Get router data that includes route info and aliases
     *
     * @return array
     */
    public function getRoute() : array
    {
        $dispatcher = $this->dispatcher();
        $routeInfo  = $dispatcher->dispatch($this->method, $this->requestedPath);
        $route = $this->runDispatcher($routeInfo);
        $routerData = [
            'route'     => $route,
            'aliases'   => $this->aliases
        ];
        return $routerData;
    }

    /**
     * Get route info for requested uri
     *
     * @param  array $routeInfo
     * @return array $routerData
     */
    private function runDispatcher(array $routeInfo) : array
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
     *
     * @param  array $routeInfo
     * @return array
     */
    private function getRouteData(array $routeInfo) : array
    {
        if ($routeInfo[0] === FastRoute\Dispatcher::FOUND) {
            [$dispatcher, $handler, $vars] = $routeInfo;
            return $this->routerClosures[$handler]($vars);
        }
        return [
            'status'        => 200,
            'returnType'    => Router::HTML,
            'definedRoute'  => null,
            'args'          => []
        ];
    }
}
