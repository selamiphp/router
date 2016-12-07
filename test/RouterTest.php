<?php

namespace tests;

use Selami;
use Zend\Diactoros\ServerRequestFactory;
use ReflectionObject;

class myRouterClass extends \PHPUnit_Framework_TestCase
{
    private $config = [
        'folder'                => '',
        'default_return_type'   =>'html'
    ];

    private $routes = [
        'home' => ['get', '/', 'app/main'],
        ['get', '/json', 'app/json', 'json'],
        ['post', '/json', 'app/redirect', 'redirect'],
        'alias' => ['get', '/alias', 'app/alias'],
    ];
    private $request = null;

    public function setUp()
    {
        $basedir = dirname(__DIR__) . '/app';
        $this->config['base_dir']   = $basedir;
        $this->config['app_dir']    = $basedir;
        $_SERVER                    = [];
        $_FILES                     = [];
        $_GET                       = [];
        $_POST                      = [];
        $_SERVER                    = [];
        $_COOKIE                    = [];
        $_SERVER['DOCUMENT_ROOT']   = $basedir."/htdocs";
        $_SERVER['SCRIPT_NAME']     = '/index.php';
        $_SERVER['REQUEST_URI']     = '/alias';
        $_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';
        $_SERVER['SERVER_NAME']     = 'Selami';
        $_SERVER['SERVER_PORT']     = '8080';
        $_SERVER['REQUEST_METHOD']  = 'GET';
        $_SERVER['QUERY_STRING']    = 'p1=1&p2=2';
        $_SERVER['HTTPS']           = '';
        $_SERVER['REMOTE_ADDR']     = '127.0.0.1';
        $_SERVER['REQUEST_TIME']    = time();
        $this->request              = ServerRequestFactory::fromGlobals($_SERVER, $_GET, $_POST, $_COOKIE, $_FILES);
    }

    /**
     * @test
     * @dataProvider extractFolderDataProvider
     * @param $requestedPath string
     * @param $folder string
     * @param $expected string
     */
    public function shouldExtractRouteFromURLSuccessfully($requestedPath, $folder, $expected)
    {
        $router = new Selami\Router(
            $this->routes,
            $this->config['default_return_type'],
            $this->request->getMethod(),
            $this->request->getUri()->getPath(),
            $this->config['folder']
        );
        $reflector = new ReflectionObject($router);
        $method = $reflector->getMethod('extractFolder');
        $method->setAccessible(true);
        $result = $method->invoke($router, $requestedPath, $folder );
        $this->assertEquals(
            $expected,
            $result,
            "extractFolder did not correctly extract the requested path for sub folder"
         );
    }

    public function extractFolderDataProvider()
    {
        return [
            ['/', '', '/'],
            ['', '', '/'],
            ['/admin/dashboard', 'admin', '/dashboard']
        ];
    }

    /**
     * @test
     */
    public function shouldCorrectlyInstantiateRouter()
    {
        $router = new Selami\Router(
            $this->routes,
            $this->config['default_return_type'],
            $this->request->getMethod(),
            $this->request->getUri()->getPath(),
            $this->config['folder']
        );
        $this->assertInstanceOf('Selami\Router', $router);
        $this->assertAttributeContains('GET', 'method', $router, "Router didn't correctly return method as GET.");
    }

    /**
     * @test
     */
    public function shouldCorrectlyReturnAliases()
    {
        $router = new Selami\Router(
            $this->routes,
            $this->config['default_return_type'],
            $this->request->getMethod(),
            $this->request->getUri()->getPath(),
            $this->config['folder']
        );
        $reflector = new ReflectionObject($router);
        $method = $reflector->getMethod('getAliases');
        $method->setAccessible(true);
        $aliases = $method->invoke($router);
        $this->assertArrayHasKey('home', $aliases, "Router didn't correctly return aliases");
        $this->assertArrayHasKey('alias', $aliases, "Router didn't correctly return aliases");
        $this->assertEquals('/', $aliases['home'], "Router didn't correctly return aliases");
        $this->assertEquals('/alias', $aliases['alias'], "Router didn't correctly return aliases");
    }

    /**
     * @test
     */
    public function shouldCorrectlyReturnRouteAndRouteAliases()
    {
        $router = new Selami\Router(
            $this->routes,
            $this->config['default_return_type'],
            $this->request->getMethod(),
            $this->request->getUri()->getPath(),
            $this->config['folder']
        );
        $routeInfo = $router->getRoute();
        $this->assertArrayHasKey('aliases', $routeInfo, "Router didn't correctly return route data");
        $this->assertArrayHasKey('controller', $routeInfo['route'], "Router didn't correctly return route data");
        $this->assertArrayHasKey('action', $routeInfo['route'], "Router didn't correctly return router data");
        $this->assertEquals('app', $routeInfo['route']['controller'], "Router didn't correctly return router data");
        $this->assertEquals('alias', $routeInfo['route']['action'], "Router didn't correctly return router data");
    }

    /**
     * @test
     */
    public function shouldCorrectlyReturnMethodNotAllowed()
    {
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->request = ServerRequestFactory::fromGlobals($_SERVER, $_GET, $_POST, $_COOKIE, $_FILES);
        $router = new Selami\Router(
            $this->routes,
            $this->config['default_return_type'],
            $this->request->getMethod(),
            $this->request->getUri()->getPath(),
            $this->config['folder']
        );
        $routeInfo = $router->getRoute();
        $this->assertEquals('405', $routeInfo['route']['status'], "Router didn't correctly return Method Not Allowed");
    }

    /**
     * @test
     */
    public function shouldCorrectlyReturnNotFound()
    {
        $_SERVER['REQUEST_URI'] = '/notexists';
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $this->request = ServerRequestFactory::fromGlobals($_SERVER, $_GET, $_POST, $_COOKIE, $_FILES);
        $router = new Selami\Router(
            $this->routes,
            $this->config['default_return_type'],
            $this->request->getMethod(),
            $this->request->getUri()->getPath(),
            $this->config['folder']
        );
        $routeInfo = $router->getRoute();
        $this->assertEquals('404', $routeInfo['route']['status'], "Router didn't correctly returnNot FOund");
    }
}
