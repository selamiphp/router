<?php
declare(strict_types=1);

namespace Selami\Router;

class Route
{
    private $requestMethod;
    private $statusCode;
    private $returnType;
    private $pattern;
    private $uriParameters;
    private $aliases;
    private $controller;
    private $realUri;

    public function __construct(
        string $requestMethod,
        string $pattern,
        int $statusCode,
        int $returnType,
        string $controller,
        array $uriParameters
    ) {
        $this->requestMethod = $requestMethod;
        $this->pattern = $pattern;
        $this->statusCode = $statusCode;
        $this->returnType = $returnType;
        $this->controller = $controller;
        $this->uriParameters = $uriParameters;
        $this->aliases = [];
        $this->realUri = $this->buildRealUri($pattern, $uriParameters);
    }

    private function buildRealUri($pattern, $uriParameters) : string
    {
        if (count($uriParameters) > 0) {
            foreach ($uriParameters as $key => $value) {
                $pattern = preg_replace('/{'.$key.'(.*?)}/msi', $value, $pattern);
            }
        }
        return $pattern;
    }

    public function withAliases(array $aliases) : self
    {
        $new = clone $this;
        $new->aliases = $aliases;
        return $new;
    }
    
    public function withStatusCode(int $statusCode) : self
    {
        $new = clone $this;
        $new->statusCode = $statusCode;
        return $new;
    }

    public function getStatusCode() : int
    {
        return $this->statusCode;
    }

    public function getReturnType() : int
    {
        return $this->returnType;
    }

    public function getController() : string
    {
        return $this->controller;
    }

    public function getRequestMethod() : string
    {
        return $this->requestMethod;
    }

    public function getPattern() : string
    {
        return $this->pattern;
    }

    public function getRealUri() : string
    {
        return $this->realUri;
    }

    public function getUriParameters() : array
    {
        return $this->uriParameters;
    }

    public function getAliases() : array
    {
        return $this->aliases;
    }

    public function getAlias(string $name) : string
    {
        return $this->aliases[$name] ?? '';
    }
}
