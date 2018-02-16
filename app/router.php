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

// Homepage
$app->get('/', function () use ($app) {
    $params = initialize_params($app);
    $html = $app['twig']->render('index.twig', $params);
    return new Response($html, 200);
});

// Page detail
$app->get('/page/{slug}/{id}', function ($slug, $id) use ($app) {
    $page = get_page($id);

    if ( ! $page || $page->deleted_at ) {
        throw new NotFoundHttpException;
    } // end if

    $params = initialize_params($app);
    $params['page'] = $page;
    $params['title'] = $page['title'];

    $html = $app['twig']->render('page.twig', $params);
    return new Response($html, 200);
})->assert('id', '\d+');

// All categories
$app->get('/categories', function () use ($app) {
    $params = initialize_params($app);
    $params['title'] = 'Categories';
    $params['categories'] = get_categories();
    $html = $app['twig']->render('categories.twig', $params);
    return new Response($html, 200);
});

// All things
$app->get('/things', function () use ($app) {
    $params = initialize_params($app);

    $params['categories'] = get_categories();
    $params['things'] = get_things();
    $params['sort'] = $app->get('sort');
    $html = $app['twig']->render('things.twig', $params);
    return new Response($html, 200);
});

// Things filtered by category
$app->get('/things/{slug}/{id}', function ($slug, $id) use ($app) {
    $params = initialize_params($app);
    $category = get_category($id);

    if ( ! $category ) {
        throw new NotFoundHttpException;
    } // end if

    $params['categories'] = get_categories();
    $params['category'] = $category;
    $params['things'] = get_things($id);
    
    $html = $app['twig']->render('things.twig', $params);
    return new Response($html, 200);
})->assert('id', '\d+');

// Thing detail
$app->get('/thing/{slug}/{id}', function () use ($app) {
    $params = initialize_params($app);

    
    $html = $app['twig']->render('thing.twig', $params);
    return new Response($html, 200);
})->assert('id', '\d+');

// New thing form
$app->get('/thing/new', function () use ($app) {
    $params = initialize_params($app);

    $json = [];
    foreach (get_categories() as $category) {
        $json[] = (object) [ 'text' => $category['name'], 'value' => $category['id'] ];
    } // end foreach
    $params['jsonCategories'] = json_encode($json);
    
    $html = $app['twig']->render('new_thing.twig', $params);
    return new Response($html, 200);
});

// Edit thing form
$app->get('/thing/edit/{id}', function () use ($app) {
    $params = initialize_params($app);

    
    $html = $app['twig']->render('edit_thing.twig', $params);
    return new Response($html, 200);
})->assert('id', '\d+');

// Creates new thing
$app->post('/thing/new', function () use ($app) {
    $params = initialize_params($app);

    
    $html = $app['twig']->render('edit_thing.twig', $params);
    return new Response($html, 200);
});

// Updates existing thing
$app->put('/thing/update/{id}', function () use ($app) {
    $params = initialize_params($app);

    
    $html = $app['twig']->render('edit_thing.twig', $params);
    return new Response($html, 200);
})->assert('id', '\d+');

// Updates single parameter for existing thing [crowdsourced ajax fields]
$app->patch('/thing/patch/{id}', function () use ($app) {
    $params = initialize_params($app);

    
    $html = $app['twig']->render('edit_thing.twig', $params);
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