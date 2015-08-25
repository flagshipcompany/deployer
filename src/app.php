<?php

use Silex\Application;
use Silex\Provider\RoutingServiceProvider;
use Modules\FlatFileConfigServiceProvider;
use Modules\GithubServiceProvider;

$app = new Application();

$app->register(new Silex\Provider\MonologServiceProvider(), array(
    'monolog.logfile' => __DIR__.'/var/logs/error.log',
));

$app->register(new RoutingServiceProvider());

$app->register(new GithubServiceProvider());
$app->register(new FlatFileConfigServiceProvider(__DIR__.'/../config.json'));

return $app;
