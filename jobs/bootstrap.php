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

error_reporting(E_ALL);
ini_set('display_errors', 1);

$app['sql'] = new \DibiConnection([
    'driver'    => DB_DRIVER,
    'host'      => DB_ADDR,
    'username'  => DB_USER,
    'password'  => DB_PSW,
    'database'  => DB_NAME,
    'charset'   => 'utf8',
    'name'      => 'main',
    'profiler'  => false,
]);

$app->register(new Moust\Silex\Provider\CacheServiceProvider(), array(
    'cache.options' => array(
        'driver' => 'file',
        'cache_dir' => "{$__DIR_ROOT}/tmp/cache/",
    )
));

require __DIR__ . '/lib.php';

return $app;
