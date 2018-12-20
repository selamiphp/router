<?php
declare(strict_types=1);

namespace Selami\Router;

use FastRoute;
use Selami\Router\Exceptions\InvalidCacheFileException;

class Dispatcher
{

    /**
     * Routes array to be registered.
     * Some routes may have aliases to be used in templating system
     * Route item can be defined using array key as an alias key.
     * Each route item is an array has items respectively: Request Method, Request Uri, Controller/Action, Return Type.
     *
     * @var array
     */
    private $routes;


    /**
     * Default return type if not noted in the $routes
     *
     * @var int
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

    private static $dispatchResults = [
        FastRoute\Dispatcher::METHOD_NOT_ALLOWED => 405,
        FastRoute\Dispatcher::FOUND => 200 ,
        FastRoute\Dispatcher::NOT_FOUND => 404
    ];

    public function __construct(array $routes, int $defaultReturnType, ?string  $cachedFile)
    {
        $this->routes = $routes;
        $this->defaultReturnType = $defaultReturnType;
        $this->cachedFile = $cachedFile;
    }

    /*
     * Dispatch against the provided HTTP method verb and URI.
     */
    public function dispatcher() : FastRoute\Dispatcher
    {
        $this->setRouteClosures();
        if ($this->cachedFile !== null && file_exists($this->cachedFile)) {
            return $this->cachedDispatcher();
        }
        /**
         * @var \FastRoute\RouteCollector $routeCollector
         */
        $routeCollector = new FastRoute\RouteCollector(
            new FastRoute\RouteParser\Std,
            new FastRoute\DataGenerator\GroupCountBased
        );
        $this->addRoutes($routeCollector);
        $this->createCachedRoute($routeCollector);
        return new FastRoute\Dispatcher\GroupCountBased($routeCollector->getData());
    }

    private function createCachedRoute($routeCollector) : void
    {
        if ($this->cachedFile !== null && !file_exists($this->cachedFile)) {
            /**
             * @var FastRoute\RouteCollector $routeCollector
             */
            $dispatchData = $routeCollector->getData();
            file_put_contents($this->cachedFile, '<?php return ' . var_export($dispatchData, true) . ';', LOCK_EX);
        }
    }

    private function cachedDispatcher() : FastRoute\Dispatcher\GroupCountBased
    {
        $dispatchData = include $this->cachedFile;
        if (!is_array($dispatchData)) {
            throw new InvalidCacheFileException('Invalid cache file "' . $this->cachedFile . '"');
        }
        return new FastRoute\Dispatcher\GroupCountBased($dispatchData);
    }

    /*
     * Define Closures for all routes that returns controller info to be used.
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
            $this->routerClosures[$routeName]= function ($uriArguments) use ($definedRoute) {
                $returnType = ($definedRoute[3] >=1 && $definedRoute[3] <=7) ? $definedRoute[3]
                    : $this->defaultReturnType;
                return  [
                    'status' => 200,
                    'requestMethod' => $definedRoute[0],
                    'controller' => $definedRoute[2],
                    'returnType' => $returnType,
                    'pattern' => $definedRoute[1],
                    'uriArguments'=> $uriArguments
                ];
            };
            $routeIndex++;
        }
    }

    public function runDispatcher(array $routeInfo) : Route
    {
        return  $this->getRouteData($routeInfo)
            ->withStatusCode(self::$dispatchResults[$routeInfo[0]]);
    }

    private function getRouteData(array $routeInfo) : Route
    {
        if ($routeInfo[0] === FastRoute\Dispatcher::FOUND) {
            [$dispatcher, $handler, $vars] = $routeInfo;
            $routeParameters =  $this->routerClosures[$handler]($vars);
            return new Route(
                $routeParameters['requestMethod'],
                $routeParameters['pattern'],
                $routeParameters['status'],
                $routeParameters['returnType'],
                $routeParameters['controller'],
                $routeParameters['uriArguments']
            );
        }
        return new Route('GET', '/', 200, 1, 'main', []);
    }
}
