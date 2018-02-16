<?php

/*
 * This file is part of the trustworthy.biz project (http://trustworthy.biz/)
 * Copyright (c) 2018 Petr Nagy (http://www.petrnagy.cz/)
 * See readme.txt for more information
 */

use \Symfony\Component\HttpFoundation\Response;
use \Symfony\Component\HttpFoundation\JsonResponse;
use \Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use \Symfony\Component\HttpKernel\Exception\HttpException;

$app->get('/', function () use ($app) {
    $params = initialize_params($app);
    $html = $app['twig']->render('index.twig', $params);
    return new Response($html, 200);
});

$app->get('/page/{slug}/{id}', function ($slug, $id) use ($app) {
    $page = $app['sql']->select('*')->from('page')->where('id = %i', $id)->fetch();

    if ( ! $page || $page->deleted_at ) {
        throw new NotFoundHttpException;
    } // end if

    $params = initialize_params($app);
    $params['page'] = $page;
    $params['title'] = $page['title'];

    $html = $app['twig']->render('page.twig', $params);
    return new Response($html, 200);
})->assert('id', '\d+');

$app->get('/categories', function () use ($app) {
    $params = initialize_params($app);

    $categories = [];
    $ids = $app['sql']->select('id')->from('category')->where('deleted_at IS NULL')->orderBy('sequence ASC, name ASC')->fetchPairs();
    foreach ($ids as $id) {
        $categories[] = get_category($id);
    } // end foreach
    $params['title'] = 'Categories';
    $params['categories'] = $categories;
    $html = $app['twig']->render('categories.twig', $params);
    return new Response($html, 200);
});

$app->get('/things', function () use ($app) {
    $params = initialize_params($app);

    
    $html = $app['twig']->render('things.twig', $params);
    return new Response($html, 200);
});

$app->get('/things/{slug}/{id}', function ($slug, $id) use ($app) {
    $params = initialize_params($app);
    $category = get_category($id);

    if ( ! $category ) {
        throw new NotFoundHttpException;
    } // end if

    $params['category'] = $category;
    $params['things'] = get_things($id);
    
    $html = $app['twig']->render('things.twig', $params);
    return new Response($html, 200);
})->assert('id', '\d+');

$app->get('/thing/{slug}/{id}', function () use ($app) {
    $params = initialize_params($app);

    
    $html = $app['twig']->render('thing.twig', $params);
    return new Response($html, 200);
})->assert('id', '\d+');

$app->error(function (\Exception $e) use ($app) {
    if ( $app['debug'] ) {
        throw $e;
    } // end if

    $params = initialize_params($app);
    $params['blob'] = get_random_string(1000);
    if ($e instanceof NotFoundHttpException) {
        return new Response($app['twig']->render('404.twig', $params), 404);
    } // end if

    $code = ($e instanceof HttpException) ? $e->getStatusCode() : 500;
    $params['code'] = $code;
    return new Response($app['twig']->render('error.twig', $params), $code);
});