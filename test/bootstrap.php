<?php
namespace tests;

require_once dirname(__DIR__) . '/vendor/autoload.php';
$loader = new \Composer\Autoload\ClassLoader();
$loader->add('test', dirname(__DIR__));
$loader->register();
