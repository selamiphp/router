<?php

namespace tests;

use Selami\Router;
use Zend\Diactoros\ServerRequestFactory;
use ReflectionObject;
use UnexpectedValueException;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;


class MyRouterClass extends TestCase
{
    private $config = [
        'folder'                => '',
        'default_return_type'   => Router::HTML
    ];

    private $request;

    public function setUp() : void
    {
        $basedir = dirname(__DIR__) . '/app';
        $this->config['base_dir']   = $basedir;
        $this->config['app_dir']    = $basedir;
        $this->config['cache_file']    = '/tmp/fastroute.cache';
        $_SERVER                    = [];
        $_FILES                     = [];
        $_GET                       = [];
        $_POST                      = [];
        $_SERVER                    = [];
        $_COOKIE                    = [];
        $_SERVER['DOCUMENT_ROOT']   = $basedir . '/htdocs';
        $_SERVER['SCRIPT_NAME']     = '/index.php';
        $_SERVER['REQUEST_URI']     = '/alias';
        $_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';
        $_SERVER['SERVER_NAME']     = 'Selami';
        $_SERVER['SERVER_PORT']     = '8080';
        $_SERVER['REQUEST_METHOD']  = Router::GET;
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
    public function shouldExtractRouteFromURLSuccessfully($requestedPath, $folder, $expected) : void
    {
        $router = new Router(
            $this->config['default_return_type'],
            $this->request->getMethod(),
            $this->request->getUri()->getPath(),
            $this->config['folder']
        );
        $router->add(Router::GET, '/', 'app/main', null, 'home');
        $router->add(Router::GET, '/json', 'app/json', Router::JSON);
        $router->add(Router::POST, '/json', 'app/redirect', Router::REDIRECT);
        $router->add(Router::GET, '/alias', 'app/alias', null, 'alias');
        $reflector = new ReflectionObject($router);
        $method = $reflector->getMethod('extractFolder');
        $method->setAccessible(true);
        $result = $method->invoke($router, $requestedPath, $folder);
        $this->assertEquals(
            $expected,
            $result,
            'extractFolder did not correctly extract the requested path for sub folder'
        );
    }

    public function extractFolderDataProvider() : array
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
    public function shouldCacheRoutesSuccessfully() : void
    {
        $router = new Router(
            $this->config['default_return_type'],
            $this->request->getMethod(),
            $this->request->getUri()->getPath(),
            $this->config['folder'],
            $this->config['cache_file']
        );
        $router->add(Router::GET, '/', 'app/main', Router::HTML, 'home');
        $router->getRoute();
        $this->assertFileExists($this->config['cache_file'],
            'Couldn\'t cache the file'
        );
    }

    /**
     * @test
     */
    public function shouldCorrectlyInstantiateRouter() : void
    {
        $router = new Router(
            $this->config['default_return_type'],
            $this->request->getMethod(),
            $this->request->getUri()->getPath(),
            $this->config['folder']
        );
        $router->add(Router::GET, '/', 'app/main', null, 'home');
        $router->add(Router::GET, '/json', 'app/json', Router::JSON);
        $router->add(Router::POST, '/json', 'app/redirect', Router::REDIRECT);
        $router->add(Router::GET, '/alias', 'app/alias', null, 'alias');
        $this->assertInstanceOf(Router::class, $router);
        $this->assertAttributeContains(
            Router::GET,
            'method',
            $router,
            "Router didn't correctly return method as GET."
        );
    }

    /**
     * @test
     */
    public function shouldCorrectlyReturnRouteAndRouteAliases() : void
    {
        $router = new Router(
            Router::JSON,
            $this->request->getMethod(),
            $this->request->getUri()->getPath(),
            $this->config['folder']
        );

        $router->get('/', 'app/main', null, 'home');
        $router->get('/json', 'app/json', Router::JSON);
        $router->get('/html', 'app/json');
        $router->post('/json', 'app/redirect', Router::REDIRECT);
        $router->get('/alias', 'app/alias', null, 'alias');
        $routeInfo = $router->getRoute();
        $this->assertArrayHasKey('aliases', $routeInfo, "Router didn't correctly return route data");

        $this->assertArrayHasKey('home', $routeInfo['aliases'], "Router didn't correctly return aliases");
        $this->assertArrayHasKey('alias', $routeInfo['aliases'], "Router didn't correctly return aliases");
        $this->assertEquals('/', $routeInfo['aliases']['home'], "Router didn't correctly return aliases");
        $this->assertEquals('/alias', $routeInfo['aliases']['alias'], "Router didn't correctly return aliases");
        $this->assertArrayHasKey('controller', $routeInfo['route'], "Router didn't correctly return route data");
        $this->assertEquals(
            'app/alias',
            $routeInfo['route']['controller'],
            "Router didn't correctly return router data"
        );
        $this->assertEquals(Router::JSON, $routeInfo['route']['returnType'], "Router didn't correctly return router data");
    }

    /**
     * @test
     * @expectedException UnexpectedValueException
     */
    public function shouldThrowUnexpectedValueExceptionForCallMethod() : void
    {
        $router = new Router(
            $this->config['default_return_type'],
            $this->request->getMethod(),
            $this->request->getUri()->getPath(),
            $this->config['folder']
        );
        $router->nonAvalibleHTTPMethod('/', 'app/main', null, 'home');
    }

    /**
     * @test
     * @expectedException UnexpectedValueException
     */
    public function shouldThrowUnexpectedValueExceptionForConstructorMethod() : void
    {
        $router = new Router(
            $this->config['default_return_type'],
            'UNEXPECTEDVALUE',
            $this->request->getUri()->getPath(),
            $this->config['folder']
        );
    }

    /**
     * @test
     * @expectedException UnexpectedValueException
     */
    public function shouldThrowUnexpectedValueExceptionForAddMethod() : void
    {
        $router = new Router(
            $this->config['default_return_type'],
            $this->request->getMethod(),
            $this->request->getUri()->getPath(),
            $this->config['folder']
        );
        $router->add('nonAvailableHTTPMethod', '/', 'app/main', null, 'home');
    }

    /**
     * @test
     * @expectedException InvalidArgumentException
     */
    public function shouldThrowInvalidArgumentExceptionForAddMethodIfREquestMEthotIsNotStringOrArray() : void
    {
        $router = new Router(
            $this->config['default_return_type'],
            $this->request->getMethod(),
            $this->request->getUri()->getPath(),
            $this->config['folder']
        );
        $router->add(200, '/', 'app/main', null, 'home');
    }

    /**
     * @test
     */
    public function shouldCorrectlyReturnMethodNotAllowed() : void
    {
        $_SERVER['REQUEST_METHOD'] = Router::POST;
        $this->request = ServerRequestFactory::fromGlobals($_SERVER, $_GET, $_POST, $_COOKIE, $_FILES);
        $router = new Router(
            $this->config['default_return_type'],
            $this->request->getMethod(),
            $this->request->getUri()->getPath(),
            $this->config['folder']
        );
        $router->add(Router::GET, '/', 'app/main', null, 'home');
        $router->add(Router::GET, '/json', 'app/json', Router::JSON);
        $router->add(Router::POST, '/json', 'app/redirect', Router::REDIRECT);
        $router->add(Router::GET, '/alias', 'app/alias', null, 'alias');
        $routeInfo = $router->getRoute();
        $this->assertEquals('405', $routeInfo['route']['status'], "Router didn't correctly return Method Not Allowed");
    }

    /**
     * @test
     */
    public function shouldCorrectlyReturnNotFound() : void
    {
        $_SERVER['REQUEST_URI'] = '/notexists';
        $_SERVER['REQUEST_METHOD'] = Router::POST;
        $this->request = ServerRequestFactory::fromGlobals($_SERVER, $_GET, $_POST, $_COOKIE, $_FILES);
        $router = new Router(
            $this->config['default_return_type'],
            $this->request->getMethod(),
            $this->request->getUri()->getPath(),
            $this->config['folder']
        );
        $router->add(Router::GET, '/', 'app/main', null, 'home');
        $router->add(Router::GET, '/json', 'app/json', Router::JSON);
        $router->add(Router::POST, '/json', 'app/redirect', Router::REDIRECT);
        $router->add(Router::GET, '/alias', 'app/alias', null, 'alias');
        $routeInfo = $router->getRoute();
        $this->assertEquals('404', $routeInfo['route']['status'], "Router didn't correctly returnNot FOund");
    }

    public function tearDown() : void
    {
        if (file_exists($this->config['cache_file'])) {
            unlink($this->config['cache_file']);
        }
    }
}
