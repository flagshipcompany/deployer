<?php

use Silex\Application;
use Silex\Provider\RoutingServiceProvider;
use Modules\FlatFileConfigServiceProvider;
use Modules\GithubServiceProvider;

$app = new Application();
$app->register(new RoutingServiceProvider());

$app->register(new GithubServiceProvider());
$app->register(new FlatFileConfigServiceProvider(__DIR__.'/../config.json'));

return $app;
