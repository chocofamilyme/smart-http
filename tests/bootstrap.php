<?php

require_once __DIR__.'/../vendor/autoload.php';

use Phalcon\Mvc\Application;
use Phalcon\Di\FactoryDefault;

$di = new FactoryDefault();

$di->set(
    'logger',
    function () {
        return new \Phalcon\Logger\Adapter\Stream('php://stderr');
    }
);

$di->set(
    'config',
    function () {
        return new \Phalcon\Config([
            'smartHttp' => [

            ],
        ]);
    }
);

return new Application($di);
