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

namespace Selami\Router;

use Selami\Router\Exceptions\InvalidRequestMethodException;
use Psr\Http\Message\ServerRequestInterface;
/**
 * Router
 *
 * This class is responsible for registering route objects,
 * determining aliases if available and finding requested route
 */
final class Router
{
    public const HTML = 1;
    public const JSON = 2;
    public const TEXT = 3;
    public const XML = 4;
    public const REDIRECT = 5;
    public const DOWNLOAD = 6;
    public const CUSTOM = 7;
    public const EMPTY = 8;

    public const OPTIONS = 'OPTIONS';
    public const HEAD = 'HEAD';
    public const GET = 'GET';
    public const POST = 'POST';
    public const PUT = 'PUT';
    public const DELETE = 'DELETE';
    public const PATCH = 'PATCH';

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
     * @var int
     */
    private $defaultReturnType;

    /**
     * @var null|string
     */
    private $cacheFile;

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

    /*
     * Router constructor.
     * Create new router.

     */
    public function __construct(
        int $defaultReturnType,
        string $method,
        string $requestedPath
    ) {
        if (!in_array($method, self::$validRequestMethods, true)) {
            $message = sprintf('%s is not valid Http request method.', $method);
            throw new InvalidRequestMethodException($message);
        }
        $this->method = $method;
        $this->requestedPath = $requestedPath;
        $this->defaultReturnType = ($defaultReturnType >=1 && $defaultReturnType <=7) ? $defaultReturnType : self::HTML;
    }

    public static function createWithServerRequestInterface(int $defaultReturnType, ServerRequestInterface $request)
    {
        return new self($defaultReturnType, $request->getMethod(), $request->getUri()->getPath());
    }

    public function withDefaultReturnType(int $defaultReturnType) : self
    {
        $new = clone $this;
        $new->$defaultReturnType = $defaultReturnType;
        return $new;
    }

    public function withSubFolder(string $folder) : self
    {
        $new = clone $this;
        $new->requestedPath = $this->extractFolder($this->requestedPath, $folder);
        return $new;
    }

    public function withCacheFile(?string $fileName=null) : self
    {
        $new = clone $this;
        $new->cacheFile = $fileName;
        return $new;
    }

    /*
     * Remove sub folder from requestedPath if defined
     */
    private function extractFolder(string $requestPath, string $folder) : string
    {
        if (!empty($folder)) {
            $requestPath = '/' . trim((string) preg_replace('#^/' . $folder . '#msi', '/', $requestPath), '/');
        }
        if ($requestPath === '') {
            $requestPath = '/';
        }
        return $requestPath;
    }

    public function add(
        $requestMethods,
        string $route,
        $action,
        ?int $returnType = null,
        ?string $alias = null
    ) : void {
    
        $requestMethodsGiven = is_array($requestMethods) ? $requestMethods : [$requestMethods];
        $returnType = $this->determineReturnType($returnType);
        foreach ($requestMethodsGiven as $requestMethod) {
            $this->checkRequestMethodIsValid($requestMethod);
            if ($alias !== null) {
                $this->aliases[$alias] = $route;
            }
            $this->routes[] = [strtoupper($requestMethod), $route, $action, $returnType];
        }
    }


    public function __call(string $method, array $args) : void
    {
        $defaults = [
            null,
            null,
            $this->defaultReturnType,
            null
        ];
        [$route, $action, $returnType, $alias] = array_merge($args, $defaults);
        $this->add($method, $route, $action, $returnType, $alias);
    }

    private function determineReturnType(?int $returnType) : int
    {
        if ($returnType === null) {
            return $this->defaultReturnType;
        }
        return ($returnType >=1 && $returnType <=7) ? $returnType : self::HTML;
    }

    private function checkRequestMethodIsValid(string $requestMethod) : void
    {
        if (!in_array(strtoupper($requestMethod), self::$validRequestMethods, true)) {
            $message = sprintf('%s is not valid Http request method.', $requestMethod);
            throw new InvalidRequestMethodException($message);
        }
    }

    public function getRoute() : Route
    {
        $selamiDispatcher = new Dispatcher($this->routes, $this->defaultReturnType, $this->cacheFile);
        $routeInfo = $selamiDispatcher->dispatcher()
            ->dispatch($this->method, $this->requestedPath);
        return $selamiDispatcher->runDispatcher($routeInfo)
            ->withAliases($this->aliases);
    }
}
