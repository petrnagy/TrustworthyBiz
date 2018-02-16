<?php

/*
 * This file is part of the trustworthy.biz project (http://trustworthy.biz/)
 * Copyright (c) 2018 Petr Nagy (http://www.petrnagy.cz/)
 * See readme.txt for more information
 */

function initialize_params($app) {
    $params = [];
    $params['helpUrl'] = make_url('page', 1);
    $params['appName'] = 'Trustworthy.biz';
    $params['title'] = 'Crowdsourced list of well established apps, websites and projects';
    $params['topCategories'] = [];
    foreach ([1,2,3,4,5,6] as $id) {
        $cat = get_category($id);
        if ( $cat ) {
            $params['topCategories'][] = $cat;
        } // end if
    } // end foreach
    return $params;
} // end function

function get_random_string($length = 4) {
    return bin2hex(openssl_random_pseudo_bytes($length));
} // end function

function make_url($type, $id) {
    global $app;

    switch ( $type ) {
        case 'page':
            $title = $app['sql']->select('title')->from('page')->where('id = %i', $id)->fetchSingle();
            return '/page/' . slugify($title) . '/' . $id;
        case 'category':
            $name = $app['sql']->select('name')->from('category')->where('id = %i', $id)->fetchSingle();
            return '/things/' . slugify($name) . '/' . $id;
    } // end switch
} // end function

function get_category($id) {
    global $app;
    $row = $app['sql']->select('*')->from('category')->where('id = %i', $id)->and('deleted_at IS NULL')->fetch();
    if ( $row ) {
        $row['cnt'] = $app['sql']->select('COUNT(*)')->from('thing_mn_category')->where('category_id = %i', $id)->fetchSingle();
        $row['url'] = make_url('category', $id);
    } // end if
    return $row;
} // end function

function get_things($category_id = null) {
    global $app;
    $stmt = $app['sql']->select('thing.*')->from('thing')->where('thing.deleted_at IS NULL');
    if ( $category_id ) {
        $stmt->and('thing_mn_category.category_id = %i', $category_id);
        $stmt->innerJoin('thing_mn_category')->on('thing_mn_category.thing_id = thing.id');
    } // end if
    $stmt->orderBy('thing.score DESC, thing.edits DESC');
    return $stmt->fetchAll();
} // end function

function slugify($text) {
    $text = preg_replace('~[^\pL\d]+~u', '-', $text);
    $text = iconv('utf-8', 'us-ascii//TRANSLIT', $text);
    $text = preg_replace('~[^-\w]+~', '', $text);
    $text = trim($text, '-');
    $text = preg_replace('~-+~', '-', $text);
    $text = strtolower($text);

    if (empty($text)) {
        return 'n-a';
    } // end if

    return $text;
} // end function