<?php

use Silex\Application;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

$app->post('/{project}/{env}', function (Request $request, Application $app, $project, $env) {

    $hash = substr($request->headers->get('X_HUB_SIGNATURE'), 5);
    $cmpHash = hash_hmac('sha1', $request->getContent(), $app['deployer.config'][$project][$env]['secret']);

    if (!$hash === $cmpHash) {
        return new Response("$hash is not equal to expected hash $cmpHash", 400);
    }

    if (strpos($request->headers->get('USER_AGENT'), 'GitHub-Hookshot') === false) {
        return new Response('Not a github webhook', 400);
    }

    $handler = new Handler($app['deployer.config'][$project], $env);

    $handler->run();

    return new Response();
});
