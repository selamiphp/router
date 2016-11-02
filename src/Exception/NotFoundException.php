<?php

namespace Selami\Core\Exception;

use Psr\Container\Exception\NotFoundException as PsrNotFoundException;
use Selami\Core\Exception\ContainerException as ContainerException;

class NotFoundException extends ContainerException implements PsrNotFoundException
{
    protected $code = 1001;
}