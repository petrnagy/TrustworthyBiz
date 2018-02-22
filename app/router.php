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
$app->get('/page/{slug}/{id}/', function ($slug, $id) use ($app) {
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
$app->get('/categories/', function () use ($app) {
    $params = initialize_params($app);
    $params['title'] = 'Categories';
    $params['categories'] = get_categories();
    $html = $app['twig']->render('categories.twig', $params);
    return new Response($html, 200);
});

// All labels
$app->get('/labels/', function () use ($app) {
    $params = initialize_params($app);
    $params['title'] = 'Labels';
    $params['labels'] = get_labels();
    $html = $app['twig']->render('labels.twig', $params);
    return new Response($html, 200);
});

// All things
$app->get('/things/', function () use ($app) {
    $params = initialize_params($app);

    $request = $app['request_stack']->getCurrentRequest();
    
    $params['categories'] = get_categories();
    $params['things'] = get_things(null, null, $request->get('sort'), $request->get('page'));
    $params['total_cnt'] = get_things_cnt(null, null);
    $params['title'] = 'All things';
    $html = $app['twig']->render('things.twig', $params);
    return new Response($html, 200);
});

// All things waiting for approal/rejection
$app->get('/things/upcoming/', function () use ($app) {
    $params = initialize_params($app);
    require_http_auth();

    $params['things'] = get_upcoming_things();
    $params['title'] = 'Upcoming things';
    
    $html = $app['twig']->render('things_upcoming.twig', $params);
    return new Response($html, 200);
});

// Autocomplete
$app->get('/things/autocomplete/', function () use ($app) {
    $request = $app['request_stack']->getCurrentRequest();
    $q = $request->get('q');
    $results = fulltext_search($q);
    return new JsonResponse($results, 200);
});

$app->patch('/thing/approve/{id}/', function ($id) use ($app) {
    require_http_auth();

    approve_thing($id);
    return new Response('', 200);
})->assert('id', '\d+');

$app->patch('/thing/reject/{id}/', function ($id) use ($app) {
    require_http_auth();
    
    reject_thing($id);
    return new Response('', 200);
})->assert('id', '\d+');

// Things filtered by label
$app->get('/things/with-label/{slug}/{id}/', function ($slug, $id) use ($app) {
    $params = initialize_params($app);
    $label = get_label($id);

    if ( ! $label ) {
        throw new NotFoundHttpException;
    } // end if
    $request = $app['request_stack']->getCurrentRequest();
    $params['categories'] = get_categories();
    $params['label'] = $label;
    $params['things'] = get_things(null, $id, $request->get('sort'), $request->get('page'));
    $params['total_cnt'] = get_things_cnt(null, $id);
    $params['title'] = 'Things with label ' . $label['name'];
    
    $html = $app['twig']->render('things.twig', $params);
    return new Response($html, 200);
})->assert('id', '\d+');

// Things filtered by category
$app->get('/things/{slug}/{id}/', function ($slug, $id) use ($app) {
    $params = initialize_params($app);
    $category = get_category($id);

    if ( ! $category ) {
        throw new NotFoundHttpException;
    } // end if
    $request = $app['request_stack']->getCurrentRequest();

    $params['categories'] = get_categories();
    $params['category'] = $category;
    $params['things'] = get_things($id, null, $request->get('sort'), $request->get('page'));
    $params['total_cnt'] = get_things_cnt($id, null);
    $params['title'] = 'Things in ' . $category['name'];
    
    $html = $app['twig']->render('things.twig', $params);
    return new Response($html, 200);
})->assert('id', '\d+');

// New thing form
$app->get('/thing/new/', function () use ($app) {
    $params = initialize_params($app);

    $json = [];
    foreach (get_categories() as $category) {
        $json[] = (object) [ 'text' => $category['name'], 'value' => $category['id'] ];
    } // end foreach
    $params['jsonCategories'] = json_encode($json);
    $json = [];
    foreach (get_types() as $type) {
        $json[] = (object) [ 'text' => $type['name'], 'value' => $type['id'] ];
    } // end foreach
    $params['jsonTypes'] = json_encode($json);
    $json = [];
    foreach (get_labels() as $label) {
        $json[] = (object) [ 'text' => $label['name'], 'value' => $label['id'] ];
    } // end foreach
    $params['jsonLabels'] = json_encode($json);

    $params['title'] = 'New thing';
    
    $html = $app['twig']->render('new_thing.twig', $params);
    return new Response($html, 200);
});

// Creates new thing
$app->post('/thing/new/', function () use ($app) {
    csrf_passed();
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

// Thing detail
$app->get('/thing/edit/{id}/', function ($id) use ($app) {
    $params = initialize_params($app);

    $thing = get_thing($id);
    if ( $thing['deleted_at'] ) {
        throw new NotFoundHttpException;
    } elseif ( $thing['is_revision_of'] ) {
        return $app->redirect( make_url('thing', $thing['is_revision_of']) );
    } // end if
    $params['thing'] = $thing;

    $json = [];
    foreach (get_categories() as $category) {
        $json[] = (object) [ 'text' => $category['name'], 'value' => $category['id'] ];
    } // end foreach
    $params['jsonCategories'] = json_encode($json);
    $params['jsonCategoriesAssigned'] = [];
    foreach ($thing['categories'] as $category) {
        $params['jsonCategoriesAssigned'][] = $category['id'];
    } // end foreach
    $params['jsonCategoriesAssigned'] = json_encode($params['jsonCategoriesAssigned']);
    
    $json = [];
    foreach (get_types() as $type) {
        $json[] = (object) [ 'text' => $type['name'], 'value' => $type['id'] ];
    } // end foreach
    $params['jsonTypes'] = json_encode($json);
    $params['jsonTypesAssigned'] = [];
    foreach ($thing['types'] as $type) {
        $params['jsonTypesAssigned'][] = $type['id'];
    } // end foreach
    $params['jsonTypesAssigned'] = json_encode($params['jsonTypesAssigned']);

    $json = [];
    foreach (get_labels() as $label) {
        $json[] = (object) [ 'text' => $label['name'], 'value' => $label['id'] ];
    } // end foreach
    $params['jsonLabels'] = json_encode($json);
    $params['jsonLabelsAssigned'] = [];
    foreach ($thing['labels'] as $label) {
        $params['jsonLabelsAssigned'][] = $label['id'];
    } // end foreach
    $params['jsonLabelsAssigned'] = json_encode($params['jsonLabelsAssigned']);

    $params['title'] = 'Edit ' . $thing['name'];

    $html = $app['twig']->render('edit_thing.twig', $params);
    return new Response($html, 200);
})->assert('id', '\d+');

// Updates existing thing
$app->put('/thing/edit/{id}/', function () use ($app) {
    if ( ! csrf_passed() ) {
        return new JsonResponse(['errors' => [], 'url' => '/', 'note' => 'csrf check failed, go away!'], 200);
    } // end if

    $request = $app['request_stack']->getCurrentRequest();
    $data = $request->get('new');
    $errors = validate_thing($data);
    if ( count($errors) ) {
        return new JsonResponse(['errors' => $errors], 200);
    } // end if
    
    $data['is_revision_of'] = $data['id'];
    if ( $thing = new_thing($data) ) {
        return new JsonResponse(['errors' => [], 'url' => $thing['url']], 200);
    } else {
        return new JsonResponse(['errors' => ['Please try again later']], 500);
    } // end if-else
})->assert('id', '\d+');

// Updates single parameter for existing thing [crowdsourced ajax fields]
$app->patch('/thing/patch/{id}/', function ($id) use ($app) {
    $params = initialize_params($app);
    
    if ( ! csrf_passed() ) {
        return new JsonResponse(['csrf test failed, go away!'], 200);
    } // end if

    $request = $app['request_stack']->getCurrentRequest();
    $data = $request->request->all();

    foreach ($data as $slug => $value) {
        if ( $crate = Option::get($slug) ) {
            if ( Option::val($crate, $value) ) {
                if ( $thing = get_thing($id) ) {
                    vote($id, $slug, $value);
                    return new JsonResponse([], 200);
                } // end if
            } // end if
        } // end if
    } // end foreach
    
    return new JsonResponse([], 400);
})->assert('id', '\d+');

// Uploads logo to /web/uploads/logo
$app->post('/upload/logo/', function () use ($app) {
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

// Thing detail
$app->get('/thing/{slug}/{id}/', function ($slug, $id) use ($app) {
    $params = initialize_params($app);

    $thing = get_thing($id);
    if ( $thing['is_revision_of'] ) {
        if ( $thing['approved_at'] || $thing['deleted_at'] ) {
            return $app->redirect( make_url('thing', $thing['is_revision_of']) );
        } // end if
    } // end if
    if ( $thing['deleted_at'] ) {
        throw new NotFoundHttpException;
    } // end if

    $params['thing'] = $thing;
    $params['title'] = $thing['name'];
    $params['similar'] = get_similar($thing['id']);
    $html = $app['twig']->render('thing.twig', $params);
    return new Response($html, 200);
})->assert('id', '\d+');

// Finds similar things
$app->get('/thing/similar/', function () use ($app) {
    $request = $app['request_stack']->getCurrentRequest();
    $name = $request->get('name');

    $json = [];
    foreach (similar_things($name) as $thing) {
        $json[] = (object) [ 'name' => $thing['name'], 'url' => $thing['url'], 'tn' => $thing['tn'] ];
    } // end foreach
    
    return new JsonResponse($json, 200);
});

$app->error(function (\Exception $e) use ($app) {
    if ( $app['debug'] ) {
        throw $e;
    } // end if

    $params = initialize_params($app);
    $params['blob'] = get_random_string(1260);
    if ($e instanceof NotFoundHttpException) {
        return new Response($app['twig']->render('404.twig', $params), 404);
    } // end if

    $code = ($e instanceof HttpException) ? $e->getStatusCode() : 500;
    $params['code'] = $code;
    return new Response($app['twig']->render('error.twig', $params), $code);
});

// robots.txt
$app->get('/robots.txt', function () use ($app) {
    $params = initialize_params($app);
    $html = $app['twig']->render('robots.twig', $params);
    return new Response($html, 200, [
        'Content-Type' => 'text/plain'
    ]);
});

// sitemap.xml
$app->get('/sitemap.xml', function () use ($app) {
    $params = initialize_params($app);
    $params['pages'] = get_pages();
    $params['categories'] = get_categories();
    $params['labels'] = get_labels();
    $params['things'] = get_things();
    $html = $app['twig']->render('sitemap.xml.twig', $params);
    return new Response($html, 200, [
        'Content-Type' => 'application/xml'
    ]);
});

// sitemap.xml
$app->get('/sitemap/', function () use ($app) {
    $params = initialize_params($app);
    $params['title'] = 'Sitemap';
    $params['pages'] = get_pages();
    $params['categories'] = get_categories();
    $params['labels'] = get_labels();
    $params['things'] = get_things();
    $html = $app['twig']->render('sitemap.twig', $params);
    return new Response($html, 200);
});
