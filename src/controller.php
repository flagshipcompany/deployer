<?php

use Silex\Application;
use Symfony\Component\HttpFoundation\Request;

$app->post('/{project}/{env}', function (Request $request, Application $app, $project, $env) {

    return $app['deployer.vcs_service']->run($request);

});
