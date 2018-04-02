<?php

use Silex\Application;
use Silex\Provider\RoutingServiceProvider;
use Modules\FlatFileConfigServiceProvider;
use Modules\GithubServiceProvider;
use Modules\GitlabServiceProvider;
use Symfony\Component\HttpFoundation\Request;

$app = new Application();

$app->register(new Silex\Provider\MonologServiceProvider(), array(
    'monolog.logfile' => __DIR__.'/../var/logs/error.log',
));
$app->error(function (Exception $e, Request $request, $code) use ($app) {
    $app['logger']->addError($e);
});

$app->register(new RoutingServiceProvider());

$app->register(new GithubServiceProvider());
$app->register(new GitlabServiceProvider());

$app->register(new FlatFileConfigServiceProvider(__DIR__.'/../config.json'));

return $app;
