<?php
/**
 * Selami Router
 *
 * @link      https://github.com/selamiphp/router
 * @license   https://github.com/selamiphp/router/blob/master/LICENSE (MIT License)
 */
namespace Selami;

use FastRoute;
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
     * Each route item is an array has items respectively : Request Method, Request Uri, Controller/Action, Return Type.
     * Route item can be defined using array key as an alias key.
     * @var array
     */
    private $routes = [];

    /**
     * HTTP request Method
     * @var string
     */
    private $method = null;

    /**
     * Request Uri
     * @var string
     */
    private $requestedPath = null;

    /**
     * Default return type if not noted in the $routes
     * @var string
     */
    private $defaultReturnType = null;

    /**
     * Router constructor.
     * Create new router.
     *
     * @param array $routes
     * @param string $defaultReturnType
     * @param string $method
     * @param string $requestedPath
     * @param string $folder
     */
    public function __construct(array $routes, string $defaultReturnType, string $method, string $requestedPath, string $folder='')
    {
        $this->routes   = $routes;
        $this->method   = $method;
        $this->requestedPath     = $this->extractFolder($requestedPath, $folder);
        $this->defaultReturnType = $defaultReturnType;
    }

    /**
     * Remove subfolder from requestedPath if defined
     * @param $requestPath
     * @param $folder
     * @return string
     */
    private function extractFolder($requestPath, $folder)
    {
        if (!empty($folder)) {
            $requestPath = '/' . trim(preg_replace('#^/' . $folder . '#msi', '/', $requestPath), "/");
        }
        if($requestPath == ''){
            $requestPath = '/';
        }
        return $requestPath;
    }

    /**
     * Dispatch against the provided HTTP method verb and URI.
     * @return array
     */
    private function dispatcher()
    {
        $options = [
            'routeParser'   => 'FastRoute\\RouteParser\\Std',
            'dataGenerator' => 'FastRoute\\DataGenerator\\GroupCountBased',
            'dispatcher'    => 'FastRoute\\Dispatcher\\GroupCountBased',
            'routeCollector' => 'FastRoute\\RouteCollector',
        ];
        /** @var RouteCollector $routeCollector */
        $routeCollector = new $options['routeCollector'](
            new $options['routeParser'], new $options['dataGenerator']
        );
        $this->addRoutes($routeCollector);
        return new $options['dispatcher']($routeCollector->getData());
    }

    /**
     * @param FastRoute\RouteCollector $route
     */
    private function addRoutes(FastRoute\RouteCollector $route)
    {
        foreach ($this->routes as $definedRoute) {
            $definedRoute[3] = (isset($definedRoute[3])) ? $definedRoute[3] : $this->defaultReturnType;
            $route->addRoute(strtoupper($definedRoute[0]), $definedRoute[1], function($args) use($definedRoute) {
                $returnType = $definedRoute[3];
                $controller = $definedRoute[2];
                list($definedRoute, $action) = explode("/", $controller);
                return  ['definedRoute' => $definedRoute, 'action'=> $action, 'returnType'=> $returnType, 'args'=> $args];
            });
        }
    }

    /**
     * Get aliases and request uri from $routes
     * @return array
     */
    private function getAliases()
    {
        $aliases = [];
        foreach ($this->routes as $alias=>$value) {
            if (gettype($alias) == 'string') {
                $aliases[$alias] = $value[1];
            }
        }
        return $aliases;
    }

    /**
     *
     */
    public function getRoute()
    {
        $dispatcher = $this->dispatcher();
        $routeInfo  = $dispatcher->dispatch($this->method, $this->requestedPath);
        $routerData = [
            'route'     => $this->runDispatcher($routeInfo),
            'aliases'   => $this->getAliases()
        ];
        return $routerData;
    }

    /**
     * @param array $routeInfo
     * @return array $routerData
     */
    private function runDispatcher(array $routeInfo)
    {
        $routeData = [
            'status'        => 200,
            'responseText'  => 'OK',
            'returnType'    => 'html',
            'controller'    => null,
            'action'        => null,
            'args'          => []
        ];
        switch ($routeInfo[0]) {
            case FastRoute\Dispatcher::METHOD_NOT_ALLOWED:
                $routeData['status'] = 405;
                $routeData['responseText'] = "Method Not Allowed";
                break;
            case FastRoute\Dispatcher::FOUND:
                $handler = $routeInfo[1];
                $vars  = $routeInfo[2];
                $route = $handler($vars);
                $routeData['status'] = 200;
                $routeData['responseText'] = "OK";
                $routeData['returnType'] = $route['returnType'];
                $routeData['controller'] = $route['definedRoute'];
                $routeData['action'] = $route['action'];
                $routeData['args'] = $route['args'];
                break;
            case FastRoute\Dispatcher::NOT_FOUND:
            default:
                $routeData['status'] = 404;
                $routeData['responseText'] = "Not Found";
                break;
        }
        return $routeData;
    }

}