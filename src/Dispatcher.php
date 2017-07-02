<?php
declare(strict_types=1);

namespace Selami;

use FastRoute;
use RuntimeException;

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


    public function __construct(array $routes, int $defaultReturnType, ?string  $cachedFile)
    {
        $this->routes = $routes;
        $this->defaultReturnType = $defaultReturnType;
        $this->cachedFile = $cachedFile;
    }

    /**
     * Dispatch against the provided HTTP method verb and URI.
     *
     * @return FastRoute\Dispatcher
     * @throws RuntimeException;
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
     * Get route info for requested uri
     *
     * @param  array $routeInfo
     * @return array $routerData
     */
    public function runDispatcher(array $routeInfo) : array
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
