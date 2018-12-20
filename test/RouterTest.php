<?php

namespace tests;

use Selami\Router\Exceptions\InvalidRequestMethodException;
use Selami\Router\Router;
use Zend\Diactoros\ServerRequestFactory;
use ReflectionObject;
use PHPUnit\Framework\TestCase;

class RouterTest extends TestCase
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
        $this->config['cache_file']    = sys_get_temp_dir() .'/fsrt.cache';
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
     */
    public function shouldExtractRouteFromURLSuccessfully($requestedPath, $folder, $expected) : void
    {
        $router = new Router(
            $this->config['default_return_type'],
            $this->request->getMethod(),
            $this->request->getUri()->getPath()
        );
        $router= $router->withSubFolder($this->config['folder']);
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
            $this->request->getUri()->getPath()
        );
        $router = $router->withSubFolder($this->config['folder'])
            ->withCacheFile($this->config['cache_file']);

        $router->add(Router::GET, '/', 'app/main', Router::HTML, 'home');
        $router->getRoute();
        $this->assertFileExists(
            $this->config['cache_file'],
            'Couldn\'t cache the file'
        );
        // Rest of the test should run without throwing exception
        $router = new Router(
            $this->config['default_return_type'],
            $this->request->getMethod(),
            $this->request->getUri()->getPath()
        );
        $router = $router->withSubFolder($this->config['folder'])
            ->withSubFolder($this->config['cache_file']);
        $router->getRoute();
    }
    /**
     * @test
     * @expectedException \Selami\Router\Exceptions\InvalidCacheFileException
     */
    public function shouldThrowExceptionForInvalidCachedFile() : void
    {
        file_put_contents('/tmp/failed.cache', '');
        $router = new Router(
            $this->config['default_return_type'],
            $this->request->getMethod(),
            $this->request->getUri()->getPath()
        );
        $router = $router->withSubFolder($this->config['folder'])
            ->withCacheFile('/tmp/failed.cache')
            ->withDefaultReturnType(Router::HTML);
        $router->add(Router::GET, '/', 'app/main', Router::HTML, 'home');
        $router->getRoute();
        $this->assertFileExists(
            $this->config['cache_file'],
            'Couldn\'t cache the file'
        );
    }


    /**
     * @test
     * @dataProvider extractFolderDataProvider
     */
    public function shouldReadCacheRoutesSuccessfully($requestedPath, $folder, $expected) : void
    {
        $router = new Router(
            $this->config['default_return_type'],
            $this->request->getMethod(),
            $this->request->getUri()->getPath()
        );
        $router = $router->withCacheFile($this->config['cache_file'])
            ->withSubFolder($this->config['folder']);
        $router->add(Router::GET, '/', 'app/main', Router::HTML, 'home');
        $router->add(Router::GET, '/', 'app/main', null, 'home');
        $router->add(Router::GET, '/json', 'app/json', Router::JSON);
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

    /**
     * @test
     */
    public function shouldCorrectlyInstantiateRouter() : void
    {
        $router = new Router(
            $this->config['default_return_type'],
            $this->request->getMethod(),
            $this->request->getUri()->getPath()
        );
        $router = $router->withSubFolder($this->config['folder']);
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
            '/alias/123',
            $this->config['folder']
        );

        $router->get('/', 'app/main', null, 'home');
        $router->get('/json', 'app/json', Router::JSON);
        $router->get('/html', 'app/json');
        $router->post('/json', 'app/redirect', Router::REDIRECT);
        $router->get('/alias', 'app/alias', null, 'alias');
        $router->get('/alias/{param:\d+}', 'app/alias', null, 'alias/param');
        $route =  $router->getRoute();
        $this->assertObjectHasAttribute('aliases', $route, "Router didn't correctly return route data");
        $aliases = $route->getAliases();
        $this->assertArrayHasKey('home', $aliases, "Router didn't correctly return aliases");
        $this->assertArrayHasKey('alias', $aliases, "Router didn't correctly return aliases");
        $this->assertEquals('/', $route->getAlias('home'), "Router didn't correctly return aliases");
        $this->assertEquals('/alias', $route->getAlias('alias'), "Router didn't correctly return aliases");
        $this->assertObjectHasAttribute('controller', $route, "Router didn't correctly return route data");
        $this->assertEquals(
            'app/alias',
            $route->getController(),
            "Router didn't correctly return router data"
        );
        $this->assertEquals(Router::JSON, $route->getReturnType(), "Router didn't correctly return router data");
        $this->assertEquals('GET', $route->getRequestMethod(), "Router didn't correctly return router data");
        $this->assertEquals('/alias/{param:\d+}', $route->getPattern(), "Router didn't correctly return router data");
        $this->assertArrayHasKey('param', $route->getUriParameters(), "Router didn't correctly return aliases");
        $this->assertEquals('123', $route->getUriParameters()['param'], "Router didn't correctly return aliases");
        $this->assertEquals('/alias/123', $route->getRealUri(), "Router didn't correctly return aliases");
    }

    /**
     * @test
     * @expectedException \Selami\Router\Exceptions\InvalidRequestMethodException
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
     * @expectedException \Selami\Router\Exceptions\InvalidRequestMethodException
     */
    public function shouldThrowUnexpectedValueExceptionForConstructorMethod() : void
    {
        new Router(
            $this->config['default_return_type'],
            'UNEXPECTEDVALUE',
            $this->request->getUri()->getPath(),
            $this->config['folder']
        );
    }

    /**
     * @test
     * @expectedException \Selami\Router\Exceptions\InvalidRequestMethodException
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
     * @expectedException \TypeError
     */
    public function shouldThrowInvalidArgumentExceptionForAddMethodIfRequestMethotIsNotStringOrArray() : void
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
        $this->assertEquals('405', $routeInfo->getStatusCode(), "Router didn't correctly return Method Not Allowed");
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
        $this->assertEquals('404', $routeInfo->getStatusCode(), "Router didn't correctly returnNot FOund");
    }

    public static function tearDownAfterClass() : void
    {
        if (file_exists(sys_get_temp_dir() .'/fsrt.cache')) {
            unlink(sys_get_temp_dir() .'/fsrt.cache');
        }
        if (file_exists('/tmp/failed.cache')) {
            unlink('/tmp/failed.cache');
        }
    }
}
