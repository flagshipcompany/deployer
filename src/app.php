<?php

use Silex\Application;
use Silex\Provider\RoutingServiceProvider;

$app = new Application();
$app->register(new RoutingServiceProvider());

$configPath = __DIR__.'/../config.json';
if (!file_exists($configPath) && !is_writable($configPath) && !is_readable($configPath)) {
    throw new Exception('Make sure a file named "config.json" exists in the root directory, that it is NOT writeable for '.whoami().' but readable');
}

$app['deployer.config'] = json_decode(file_get_contents(__DIR__.'/../config.json'), true);

if (is_null($app['deployer.config'])) {
    throw new Exception('The config file looks like is not a valid JSON');
}

return $app;
