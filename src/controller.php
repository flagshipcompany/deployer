<?php

use Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

$app->post('/{project}/{env}', function (Request $request, Application $app, $project, $env) {

    try {
        $config = $app['deployer.config'][$project][$env];
    } catch (Exception $e) {
       return new Response($e->getMessage(), 500);
    }

    $requestedVcs = $config['vcs'] ?? 'github';

    return $app['deployer.vcs_service.'.$requestedVcs]->run($request, $config);

});
