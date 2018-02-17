<?php

/*
 * This file is part of the trustworthy.biz project (http://trustworthy.biz/)
 * Copyright (c) 2018 Petr Nagy (http://www.petrnagy.cz/)
 * See readme.txt for more information
 */

function initialize_params($app) {
    $params = [];
    $params['me'] = me();
    $params['clear_me'] = clear_me();
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
    $params['sorting'] = [
        ['magic', 'Magic'],
        ['top', 'Top score'],
        ['new', 'Newest first'],
        ['a-z', 'Alphabetically'],
    ];
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
        case 'thing':
            $name = $app['sql']->select('name')->from('thing')->where('id = %i', $id)->fetchSingle();
            return '/thing/' . slugify($name) . '/' . $id;
    } // end switch
} // end function

function get_page($id) {
    global $app;

    return $app['sql']->select('*')->from('page')->where('id = %i', $id)->fetch();
} // end function

function get_categories() {
    global $app;

    $categories = [];
    $ids = $app['sql']->select('id')->from('category')->where('deleted_at IS NULL')->orderBy('sequence ASC, name ASC')->fetchPairs();
    foreach ($ids as $id) {
        $categories[] = get_category($id);
    } // end foreach
    return $categories;
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

    $stmt = $app['sql']->select('thing.id')->from('thing')->where('thing.deleted_at IS NULL');
    if ( $category_id ) {
        $stmt->and('thing_mn_category.category_id = %i', $category_id);
        $stmt->innerJoin('thing_mn_category')->on('thing_mn_category.thing_id = thing.id');
    } // end if
    $stmt->orderBy('thing.score DESC, thing.edits DESC');
    $ids = $stmt->fetchPairs();

    $things = [];
    foreach ($ids as $id) {
        $things[] = get_thing($id);
    } // end foreach
    
    return $things;
} // end function

function get_thing($id) {
    global $app;

    $row = $app['sql']->select('thing.*')->from('thing')->where('thing.id = %i', $id)->fetch();
    $row['url'] = make_url('thing', $id);
    return $row;
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

function generate_thumbnail($uploadedFile) {
    global $app, $__DIR_ROOT;
    static $size = 600, $tnSize = 300;
    $doubleSize = 2*$size;
    $doubleTnSize = 2*$tnSize;

    $hash = md5(microtime().rand(-1000, 1000));
    $ext = substr($uploadedFile['name'], strrpos($uploadedFile['name'], '.') + 1);
    if ( ! in_array($ext, ['jpg', 'jpeg', 'gif', 'png', 'bmp']) ) {
        throw new InvalidArgumentException('Only these extensions are allowed: jpg, jpeg, gif, png, bmp');
    } // end if

    $file = "/tmp/{$hash}.$ext";
    if ( ! move_uploaded_file($uploadedFile['tmp_name'], $file) ) {
        throw new Exception('Could not move file to temporary location');
    } // end if

    $tn = "{$__DIR_ROOT}/web/upload/logo/[tn]{$hash}_{$tnSize}x{$tnSize}.{$ext}";
    $img = "{$__DIR_ROOT}/web/upload/logo/[logo]{$hash}_{$size}x{$size}.{$ext}";
    
    $imgSize = getimagesize($file);
    $longerSide = ( $imgSize[0] > $imgSize[1] ? $imgSize[0] : $imgSize[1] );
    if ( $longerSide < $size ) {
        $size = $longerSide;
    } // end if
    $scale = (int) $tnSize / 4;

    $commands = [];
    $commands[] = "convert -define jpeg:size={$doubleTnSize}x{$doubleTnSize} '{$file}' -thumbnail {$tnSize}x{$tnSize}^ -gravity center -extent {$tnSize}x{$tnSize} -scale {$scale}x{$scale} -scale {$tnSize}x{$tnSize} '{$tn}'";
    $commands[] = "convert -define jpeg:size={$doubleSize}x{$doubleSize} '{$file}' -scale {$size}x{$size} '{$img}'";
    foreach ($commands as $command) {
        exec($command);
    } // end foreach
    
    return ( filesize($tn) ? $tn : false );
} // end method

function nvl( & $var, $default = false) {
    if ( isset($var) ) {
        return $var;
    } else {
        return $default;
    } // end if-else
} // end function

function clear_me() {
    return str_replace('?' . nvl($_SERVER['QUERY_STRING']), '', me());
} // end function

function me() {
    return wwwroot() . nvl($_SERVER['REQUEST_URI']);
} // end function

function protocol() {
    $protocol = 'http' . (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off' ? 's' : '');
    return $protocol;
} // end function

function wwwroot() {
    return protocol() . '://' . $_SERVER['HTTP_HOST'];
}