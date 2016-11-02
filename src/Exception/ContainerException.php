<?php

namespace Selami\Core\Exception;

use InvalidArgumentException;
use Psr\Container\Exception\ContainerException as PsrContainerException;

class ContainerException extends InvalidArgumentException implements PsrContainerException
{
    protected $code = 1000;
}