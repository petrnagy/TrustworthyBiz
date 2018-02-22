<?php

/*
 * This file is part of the trustworthy.biz project (http://trustworthy.biz/)
 * Copyright (c) 2018 Petr Nagy (http://www.petrnagy.cz/)
 * See readme.txt for more information
 */

require __DIR__ . '/../vendor/autoload.php';

require __DIR__ . '/../app/bootstrap.php';

$app = new \Silex\Application();
$app['debug'] = ! PRODUCTION;

$app->register(new Silex\Provider\SessionServiceProvider(), [
    'session.storage.save_path' => $app['debug'] ? "/tmp" : "{$__DIR_ROOT}/tmp/session/"
]);
$app['session']->start();

\Tracy\Debugger::enable( PRODUCTION ? \Tracy\Debugger::PRODUCTION : \Tracy\Debugger::DEVELOPMENT, $__DIR_ROOT . '/tmp/logs/' );
\Tracy\Debugger::$strictMode = true;
\Tracy\Debugger::$email = 'tracy@trustworthy.biz';

$app['sql'] = new \DibiConnection([
    'driver'    => DB_DRIVER,
    'host'      => DB_ADDR,
    'username'  => DB_USER,
    'password'  => DB_PSW,
    'database'  => DB_NAME,
    'charset'   => 'utf8',
    'name'      => 'main',
    'profiler'  => false,
    //'profiler'  => ( $app['debug'] ? [ 'run' => true, 'file' => $__DIR_ROOT . '/tmp/logs/dibi.log' ] : null ),
]);
if ( $app['debug'] ) {
    $bar = new \Dibi\Bridges\Tracy\Panel();
    $bar->register($app['sql']);    
} // end if

require_once $__DIR_ROOT . '/app/router.php';

$app->register(new \Silex\Provider\AssetServiceProvider(), [
    'assets.version' => 'v' . BUILD,
    'assets.version_format' => '%s?version=%s',
    'assets.named_packages' => [
        'css' => ['version' => 'css2', 'base_path' => '/css'],
        'images' => ['base_path' => '/img'],
    ],
]);

$app->register(new \Silex\Provider\TwigServiceProvider(), [
    'twig.path' => $__DIR_ROOT . '/views',
    'twig.options' => [
        'cache' => "{$__DIR_ROOT}/tmp/cache/"
    ],
]);

$app->register(new Moust\Silex\Provider\CacheServiceProvider(), array(
    'cache.options' => array(
        'driver' => 'file',
        'cache_dir' => "{$__DIR_ROOT}/tmp/cache/",
    )
));

$app->register(new Silex\Provider\HttpCacheServiceProvider(), array(
    'http_cache.cache_dir' => "{$__DIR_ROOT}/tmp/cache/",
));

$app->register(new Silex\Provider\CsrfServiceProvider());

$app->before(function (\Symfony\Component\HttpFoundation\Request $request, \Silex\Application $app) {
    require_http_auth();
}, \Silex\Application::EARLY_EVENT);

$app->run();
