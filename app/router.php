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
$app->get('/thing/{slug}/{id}', function ($slug, $id) use ($app) {
    $params = initialize_params($app);

    $params['thing'] = get_thing($id);
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

// New thing form
$app->get('/thing/similar', function () use ($app) {
    $params = initialize_params($app);

    $request = $app['request_stack']->getCurrentRequest();
    $name = $request->get('name');

    $json = [];
    foreach (similar_things($name) as $thing) {
        $json[] = (object) [ 'name' => $thing['name'], 'url' => $thing['url'], 'tn' => $thing['tn'] ];
    } // end foreach
    
    return new JsonResponse($json, 200);
});

// Creates new thing
$app->post('/thing/new', function () use ($app) {
    $params = initialize_params($app);

    $request = $app['request_stack']->getCurrentRequest();
    $new = $request->get('new');
    $errors = validate_thing($new);
    if ( count($errors) ) {
        return new JsonResponse(['errors' => $errors], 200);
    } // end if
    
    if ( $thing = new_thing($new) ) {
        return new JsonResponse(['errors' => [], 'url' => $thing['url']], 200);
    } else {
        return new JsonResponse(['errors' => ['Please try again later']], 500);
    } // end if-else
});

// Edit thing form
$app->get('/thing/edit/{id}', function () use ($app) {
    $params = initialize_params($app);

    
    $html = $app['twig']->render('edit_thing.twig', $params);
    return new Response($html, 200);
})->assert('id', '\d+');

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

// Uploads logo to /web/uploads/logo
$app->post('/upload/logo/', function () use ($app) {
    $params = initialize_params($app);
    $code = 200;
    $json = (object) [ 'result' => 'ok', 'error' => null ];

    $file = isset($_FILES['logo']) ? $_FILES['logo'] : null;

    if ( $file ) {
        try {
            $images = generate_thumbnail($file);
            if ( $images ) {
                $json = (object) [ 'result' => 'ok', 'error' => null, 'images' => $images ];
            } else {
                $code = 500;
                $json = (object) [ 'result' => 'error', 'error' => 'Something went wrong' ];    
            } // end if
        } catch (InvalidArgumentException $ex) {
            $code = 400;
            $json = (object) [ 'result' => 'error', 'error' => $ex->getMessage() ];
        } catch (Exception $ex) {
            $code = 500;
            $json = (object) [ 'result' => 'error', 'error' => 'Server error' ];
        } // end try-catch
    } else {
        $code = 400;
        $json = (object) [ 'result' => 'error', 'error' => 'No file was uploaded' ];
    } // end if

    return new JsonResponse($json, $code);
});

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