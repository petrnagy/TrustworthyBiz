<?php

/*
 * This file is part of the trustworthy.biz project (http://trustworthy.biz/)
 * Copyright (c) 2018 Petr Nagy (http://www.petrnagy.cz/)
 * See readme.txt for more information
 */

use Symfony\Component\Security\Csrf\CsrfToken;

function get_random_string($length = 4) {
    return bin2hex(openssl_random_pseudo_bytes($length));
} // end function

function make_url($type, $id) {
    global $app;

    switch ( $type ) {
        case 'page':
            $title = $app['sql']->select('title')->from('page')->where('id = %i', $id)->fetchSingle();
            return '/page/' . slugify($title) . '/' . $id . '/';
        case 'category':
            $name = $app['sql']->select('name')->from('category')->where('id = %i', $id)->fetchSingle();
            return '/things/' . slugify($name) . '/' . $id . '/';
        case 'thing':
            $name = $app['sql']->select('name')->from('thing')->where('id = %i', $id)->fetchSingle();
            return '/thing/' . slugify($name) . '/' . $id . '/';
        case 'label':
            $name = $app['sql']->select('name')->from('label')->where('id = %i', $id)->fetchSingle();
            return '/things/with-label/' . slugify($name) . '/' . $id . '/';
    } // end switch
} // end function

function get_page($id) {
    global $app;

    $row = $app['sql']->select('*')->from('page')->where('id = %i', $id)->and('deleted_at IS NULL')->fetch();
    $row['url'] = make_url('page', $id);
    return $row;
} // end function

function get_pages() {
    global $app;
    $ids = $app['sql']->select('id')->from('page')->where('deleted_at IS NULL')->fetchPairs();
    $pages = [];
    foreach ($ids as $id) {
        if ( $page = get_page($id) ) {
            $pages[] = $page;
        } // end if
    } // end foreach
    return $pages;
} // end function

function get_categories() {
    global $app;

    $key = "all-categories";
    if ( $cached = $app['cache']->fetch($key) ) return $cached;

    $categories = [];
    $ids = $app['sql']->select('id')->from('category')->where('deleted_at IS NULL')->orderBy('sequence ASC, name ASC')->fetchPairs();
    foreach ($ids as $id) {
        $categories[] = get_category($id);
    } // end foreach

    $app['cache']->store($key, $categories, 900);

    return $categories;
} // end function

function get_types() {
    global $app;

    $key = "all-types";
    if ( $cached = $app['cache']->fetch($key) ) return $cached;

    $types = [];
    $ids = $app['sql']->select('id')->from('type')->where('deleted_at IS NULL')->orderBy('name ASC')->fetchPairs();
    foreach ($ids as $id) {
        $types[] = get_type($id);
    } // end foreach

    $app['cache']->store($key, $types, 900);

    return $types;
} // end function

function get_labels() {
    global $app;

    $key = "all-labels";
    if ( $cached = $app['cache']->fetch($key) ) return $cached;

    $labels = [];
    $ids = $app['sql']->select('id')->from('label')->where('deleted_at IS NULL')->orderBy('name ASC')->fetchPairs();
    foreach ($ids as $id) {
        $labels[] = get_label($id);
    } // end foreach

    $app['cache']->store($key, $labels, 900);

    return $labels;
} // end function

function get_category($id) {
    global $app;

    $row = $app['sql']->select('*')->from('category')->where('id = %i', $id)->and('deleted_at IS NULL')->fetch();
    $ids = $app['sql']->select('thing_id')->from('thing_mn_category')->where('category_id = %i', $id)->fetchPairs();
    $row['cnt'] = $app['sql']->select('COUNT(*)')->from('thing')->where('id IN %in', $ids)->and('deleted_at IS NULL')->and('approved_at IS NOT NULL')->fetchSingle();
    $row['url'] = make_url('category', $id);

    return $row;
} // end function

function get_type($id) {
    global $app;

    return $app['sql']->select(['id', 'name'])->from('type')->where('id = %i', $id)->and('deleted_at IS NULL')->fetch();
} // end function

function get_label($id) {
    global $app;

    $row = $app['sql']->select(['id', 'name'])->from('label')->where('id = %i', $id)->and('deleted_at IS NULL')->fetch();
    $ids = $app['sql']->select('thing_id')->from('thing_mn_label')->where('label_id = %i', $id)->fetchPairs();
    $row['cnt'] = $app['sql']->select('COUNT(*)')->from('thing')->where('id IN %in', $ids)->and('deleted_at IS NULL')->and('approved_at IS NOT NULL')->fetchSingle();
    $row['url'] = make_url('label', $row['id']);

    return $row;
} // end function

function get_things($category_id = null, $label_id = null, $ordering = null, $page = null, $cnt = false) {
    global $app;
    $page = abs( intval( is_null($page) ? 1 : $page ) );

    $stmt = $app['sql']->select($cnt ? 'COUNT(*)' : 'thing.id')->from('thing')->where('thing.deleted_at IS NULL')->and('approved_at IS NOT NULL');
    if ( $category_id ) {
        $stmt->and('thing_mn_category.category_id = %i', $category_id);
        $stmt->innerJoin('thing_mn_category')->on('thing_mn_category.thing_id = thing.id');
    } // end if
    if ( $label_id ) {
        $stmt->and('thing_mn_label.label_id = %i', $label_id);
        $stmt->innerJoin('thing_mn_label')->on('thing_mn_label.thing_id = thing.id');
    } // end if
    switch ( $ordering ) {
        case 'a-z':
            $stmt->orderBy('thing.name ASC');
        break;
        case 'new':
            $stmt->orderBy('thing.created_at DESC');
        break;
        case 'top':
            $stmt->orderBy('thing.score DESC');
        break;
        default:
            $stmt->orderBy('thing.score DESC');
        break;
    } // end switch
    
    $stmt->limit(THINGS_PER_PAGE);
    $stmt->offset( (($page-1)*THINGS_PER_PAGE) );

    if ( $cnt ) {
        return $stmt->fetchSingle();
    } else {
        $ids = $stmt->fetchPairs();
    } // end if-else

    $things = [];
    foreach ($ids as $id) {
        $things[] = get_thing($id);
    } // end foreach
    
    return $things;
} // end function

function get_things_cnt($category_id = null, $label_id = null) {
    return get_things($category_id, $label_id, null, null, true);
} // end function

function get_upcoming_things() {
    global $app;

    $ids = $app['sql']->select('id')->from('thing')->where('deleted_at IS NULL')->and('approved_at IS NULL')->orderBy('id DESC')->fetchPairs();
    $things = [];
    foreach ($ids as $id) {
        $things[] = get_thing($id);
    } // end foreach
    return $things;
} // end function

function get_thing($id) {
    global $app;

    $row = $app['sql']->select('thing.*')->from('thing')->where('thing.id = %i', $id)->fetch();
    $row['tn'] = $row['tn'];
    $row['img'] = $row['img'];
    $row['url'] = make_url('thing', $id);
    $categories = $types = $labels = [];

    $thingCategoriesIds = $app['sql']->select('category_id')->from('thing_mn_category')->where('thing_id = %i', $id)->fetchPairs();
    foreach ($thingCategoriesIds as $categoryId) {
        if ( $category = get_category($categoryId) ) {
            $categories[] = $category;
        } // end if
    } // end foreach
    $row['categories'] = $categories;

    $thingTypesIds = $app['sql']->select('type_id')->from('thing_mn_type')->where('thing_id = %i', $id)->fetchPairs();
    foreach ($thingTypesIds as $typeId) {
        if ( $type = get_type($typeId) ) {
            $types[] = $type;
        } // end if
    } // end foreach
    $row['types'] = $types;

    $thingLabelsIds = $app['sql']->select('label_id')->from('thing_mn_label')->where('thing_id = %i', $id)->fetchPairs();
    foreach ($thingLabelsIds as $labelId) {
        if ( $label = get_label($labelId) ) {
            $labels[] = $label;
        } // end if
    } // end foreach
    $row['labels'] = $labels;

    $row['grade'] = get_grade($row['score']);
    $row['stars'] = get_stars($row['score']);

    return $row;
} // end function

function get_similar($id) {
    global $app;
    $similar = [];

    $categoryIds = $app['sql']->select('category_id')->from('thing_mn_category')->where('thing_id = %i', $id)->fetchPairs();
    $labelIds = $app['sql']->select('label_id')->from('thing_mn_label')->where('thing_id = %i', $id)->fetchPairs();
    
    $categoryThingIds = $app['sql']->select('thing_id')->from('thing_mn_category')->where('category_id IN %in', $categoryIds)->and('thing_id != %i', $id)->fetchPairs();
    $labelThingIds = $app['sql']->select('thing_id')->from('thing_mn_label')->where('label_id IN %in', $labelIds)->and('thing_id != %i', $id)->fetchPairs();
    $combinedIds = array_unique( array_merge($categoryThingIds, $labelThingIds) );
    $bestMatchIds = array_intersect( $categoryThingIds, $labelThingIds );
    
    $filteredIds = $app['sql']->select('id')->from('thing')->where('id IN %in', $combinedIds)
                              ->and('deleted_at IS NULL')->and('approved_at IS NOT NULL')
                              ->orderBy('CASE WHEN id IN %in THEN 1 ELSE 0 END DESC, score DESC, id DESC', $bestMatchIds)
                              ->fetchPairs();

    foreach ($filteredIds as $filteredId) {
        if ( $thing = get_thing($filteredId) ) {
            $similar[] = $thing;
            if ( count($similar) == 6 ) {
                break;
            } // end if
        } // end if
    } // end foreach

    return $similar;
} // end function

function get_grade($score) {
    if ( $score == 0.00 )        return 'na';
    elseif ( $score < 20.00 )    return 'f';
    elseif ( $score < 30.00 )    return 'e';
    elseif ( $score < 40.00 )    return 'd';
    elseif ( $score < 60.00 )    return 'c';
    elseif ( $score < 75.00 )    return 'b';
    elseif ( $score < 90.00 )    return 'a';
    else                         return 'a+';
} // end function

function get_stars($score) {
    if ( $score == 0.00 )        return '0.00';
    elseif ( $score < 20.00 )    return '0.50';
    elseif ( $score < 30.00 )    return '1.00';
    elseif ( $score < 40.00 )    return '2.00';
    elseif ( $score < 60.00 )    return '2.50';
    elseif ( $score < 75.00 )    return '4.00';
    elseif ( $score < 90.00 )    return '4.50';
    else                         return '5.00';
} // end function

function grade2score($grade) {
    switch ( $grade ) {
        case Grade::A_PLUS: return 100;
        case Grade::A:      return 89;
        case Grade::B:      return 74;
        case Grade::C:      return 59;
        case Grade::D:      return 39;
        case Grade::E:      return 20;
        case Grade::F:      return 19;
        default:            return null;
    } // end switch
} // end function

function similar_things($name) {
    global $app;
    $ids = $app['sql']->select('id')->from('thing')->where('deleted_at IS NULL')->and('name LIKE %~like~', $name)->fetchPairs();
    $things = [];
    foreach ($ids as $id) {
        if ( $thing = get_thing($id) ) {
            $things[] = $thing;
        } // end if
    } // end foreach
    return $things;
} // end function

function validate_thing($data) {
    global $__DIR_ROOT;

    $errors = [];
    if ( mb_strlen(trim(nvl($data['name']))) < 3 ) {
        $errors[] = 'Name must have at least 3 characters.';
    } // end if
    // if ( mb_strlen(trim(nvl($data['summary']))) < 10 ) {
    //     $errors[] = 'Summary must have at least 10 characters.';
    // } // end if

    if ( ! filter_var(nvl($data['homepage']), FILTER_VALIDATE_URL) ) {
        $errors[] = 'Enter valid homepage URL address.';
    } // end if
    $categories = false;
    foreach (explode(';', nvl($data['categories'])) as $id) {
        if ( get_category($id) ) {
            $categories = true;
            break;
        } // end if
    } // end foreach
    if ( ! $categories ) {
        $errors[] = 'At least one category must be selected.';
    } // end if
    $types = false;
    foreach (explode(';', nvl($data['types'])) as $id) {
        if ( get_type($id) ) {
            $types = true;
            break;
        } // end if
    } // end foreach
    if ( ! $types ) {
        $errors[] = 'At least one type must be selected.';
    } // end if
    if ( empty($data['tn']) || empty($data['img']) || ! file_exists($__DIR_ROOT . "/web{$data['tn']}") || ! file_exists($__DIR_ROOT . "/web{$data['img']}") ) {
        $errors[] = 'The logo must be uploaded as well.';
    } // end if
    return $errors;
} // end function

function sanitize_thing($data) {
    $data['name'] = mb_substr(sanitize_string($data['name']), 0, 50);
    $data['summary'] = mb_substr(sanitize_string($data['summary']), 0, 100);
    $data['description'] = isset($data['description']) ? sanitize_string($data['description']) : null;
    $data['homepage'] = mb_substr($data['homepage'], 0, 150);
    
    $data['facebook'] = empty($data['facebook']) ? null : mb_substr($data['facebook'], 0, 150);
    $data['twitter'] = empty($data['twitter']) ? null : mb_substr($data['twitter'], 0, 150);
    $data['instagram'] = empty($data['instagram']) ? null : mb_substr($data['instagram'], 0, 150);
    $data['linkedin'] = empty($data['linkedin']) ? null : mb_substr($data['linkedin'], 0, 150);
    
    $data['tn'] = str_replace('..', '', $data['tn']);
    $data['img'] = str_replace('..', '', $data['img']);
    if ( 0 !== strpos($data['tn'], '/upload') ) {
        $data['tn'] = null;
    } // end if
    if ( 0 !== strpos($data['img'], '/upload') ) {
        $data['img'] = null;
    } // end if

    $ids = [];
    foreach (explode(';', $data['categories']) as $id) {
        if ( get_category($id) ) {
            $ids[] = $id;
        } // end if
    } // end foreach
    $data['categories'] = $ids;

    $ids = [];
    foreach (explode(';', $data['types']) as $id) {
        if ( get_type($id) ) {
            $ids[] = $id;
        } // end if
    } // end foreach
    $data['types'] = $ids;

    $ids = [];
    foreach (explode(';', $data['labels']) as $id) {
        if ( $id && get_label($id) ) {
            $ids[] = $id;
        } // end if
    } // end foreach
    $data['labels'] = $ids;

    return $data;
} // end function

function new_thing($data) {
    global $app;

    if ( empty($data['summary']) ) {
        $data['summary'] = fetch_summary_from_title($data['homepage']);
        if ( ! strlen($data['summary']) ) {
            $data['summary'] = get_random_string(50);
        } // end if
    } // end if

    $data = sanitize_thing($data);
    $app['sql']->insert('thing', [
        'name'              => $data['name'],
        'summary'           => $data['summary'],
        'homepage'          => $data['homepage'],
        'facebook'          => nvl($data['facebook'], null),
        'twitter'           => nvl($data['twitter'], null),
        'instagram'         => nvl($data['instagram'], null),
        'linkedin'          => nvl($data['linkedin'], null),
        'edited'            => 1,
        'tn'                => $data['tn'],
        'img'               => $data['img'],
        'created_at'        => new DateTime,
        'approved_at'       => null,
        'is_revision_of'    => nvl($data['is_revision_of'], null),
    ])->execute();
    
    $id = $app['sql']->getInsertId();

    foreach ($data['categories'] as $categoryId) {
        $app['sql']->insert('thing_mn_category', [
            'category_id'   => $categoryId,
            'thing_id'      => $id
        ])->execute();
    } // end foreach

    foreach ($data['types'] as $typeId) {
        $app['sql']->insert('thing_mn_type', [
            'type_id'       => $typeId,
            'thing_id'      => $id
        ])->execute();
    } // end foreach

    foreach ($data['labels'] as $labelId) {
        $app['sql']->insert('thing_mn_label', [
            'label_id'       => $labelId,
            'thing_id'       => $id
        ])->execute();
    } // end foreach

    // $app['cache']->clear();

    return get_thing($id);
} // end function

function update_thing($data) {
    global $app;
    
    $data = sanitize_thing($data);
    $thing = get_thing($data['id']);

    if ( ! $thing ) {
        throw new InvalidArgumentException;
    } // end if

    $app['sql']->update('thing', [
        'name'          => $data['name'],
        'summary'       => $data['summary'],
        'homepage'      => $data['homepage'],
        'facebook'      => $data['facebook'],
        'twitter'       => $data['twitter'],
        'linkedin'      => $data['linkedin'],
        'instagram'     => $data['instagram'],
        'description'   => $data['description'],
        'tn'            => $data['tn'],
        'img'           => $data['img'],
        'edited'        => $thing['edited'] + 1,
    ])->where('id = %i', $data['id'])->execute();
    
    $app['sql']->delete('thing_mn_category')->where('thing_id = %i', $data['id'])->execute();
    foreach ($data['categories'] as $categoryId) {
        $app['sql']->insert('thing_mn_category', [
            'category_id'   => $categoryId,
            'thing_id'      => $data['id']
        ])->execute();
    } // end foreach

    $app['sql']->delete('thing_mn_type')->where('thing_id = %i', $data['id'])->execute();
    foreach ($data['types'] as $typeId) {
        $app['sql']->insert('thing_mn_type', [
            'type_id'   => $typeId,
            'thing_id'  => $data['id']
        ])->execute();
    } // end foreach

    $app['sql']->delete('thing_mn_label')->where('thing_id = %i', $data['id'])->execute();
    foreach ($data['labels'] as $labelId) {
        $app['sql']->insert('thing_mn_label', [
            'label_id'   => $labelId,
            'thing_id'  => $data['id']
        ])->execute();
    } // end foreach

    // $app['cache']->clear();

    return get_thing($data['id']);
} // end function

function vote($thingId, $optionSlug, $value) {
    global $app;
    $request = $app['request_stack']->getCurrentRequest();

    $exists = $app['sql']->select('id')->from('thing_option')->where('thing_id = %i', $thingId)
    ->and('value_slug = %s', $optionSlug)->and('user_session = %s', $app['session']->getId())
    ->and('deleted_at IS NULL')->fetchSingle();

    if ( $exists ) {
        $app['sql']->update('thing_option', [
            'value'             => $value,
        ])->where('id = %i', $exists)->execute();
    } else {
        $app['sql']->insert('thing_option', [
            'thing_id'          => $thingId,
            'value_slug'        => $optionSlug,
            'value'             => $value,
            'user_ip'           => $request->getClientIp(),
            'user_agent'        => $request->headers->get('User-Agent'),
            'user_session'      => $app['session']->getId(),
            'created_at'        => new DateTime
        ])->execute();
    } // end if-else

} // end function

function sanitize_string($item) {
    $item = strip_tags($item);
    $item = preg_replace('~(?<open><|&lt;|U\+003C|&#60;)(?<tag>\ *?script\ *?)(?<close>>|U\+003E|&#62;|&gt;).*~uis', '', $item);
    $item = trim($item);
    return $item;
} // end function

function approve_thing($id) {
    global $app;
    $thing = get_thing($id);
    if ( $thing['is_revision_of'] ) {
        $data = (array) $thing;
        $data['id'] = $thing['is_revision_of'];
        $data['categories'] = $data['types'] = $data['labels'] = [];

        foreach ($thing['categories'] as $category)     $data['categories'][] = $category['id'];
        foreach ($thing['types'] as $type)              $data['types'][] = $type['id'];
        foreach ($thing['labels'] as $label)            $data['labels'][] = $label['id'];

        $data['categories'] = implode(';', $data['categories']);
        $data['types'] = implode(';', $data['types']);
        $data['labels'] = implode(';', $data['labels']);
        update_thing($data);
        $app['sql']->update('thing', ['approved_at' => new DateTime, 'deleted_at' => new DateTime])->where('id = %i', $id)->execute();
    } else {
        $app['sql']->update('thing', ['approved_at' => new DateTime])->where('id = %i', $id)->execute();
    } // end if-else
} // end function

function reject_thing($id) {
    global $app;
    $app['sql']->update('thing', ['deleted_at' => new DateTime])->where('id = %i', $id)->execute();
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

function normalize($term) {
    $term = html_entity_decode($term, ENT_QUOTES);
    $term = preg_replace('/[^\p{L}\p{N}_]+/u', ' ', $term);
    $term = preg_replace('/[+\-><\(\)~*\"@]+/u', ' ', $term);
    $term = trim($term);
    return $term;
} // end function

function generate_thumbnail($uploadedFile) {
    global $app, $__DIR_ROOT;
    static $size = 300, $tnSize = 150;
    $doubleSize = 2*$size;
    $doubleTnSize = 2*$tnSize;

    $hash = md5(microtime().rand(-1000, 1000));
    $ext = substr($uploadedFile['name'], strrpos($uploadedFile['name'], '.') + 1);
    if ( ! in_array($ext, ['jpg', 'jpeg', 'gif', 'png', 'bmp']) ) {
        throw new InvalidArgumentException('Only these extensions are allowed: jpg, jpeg, gif, png, bmp');
    } // end if

    $file = "{$__DIR_ROOT}/tmp/uploads/{$hash}.$ext";
    if ( ! move_uploaded_file($uploadedFile['tmp_name'], $file) ) {
        throw new Exception('Could not move file to temporary location');
    } // end if

    $tnName = "tn--{$hash}_{$tnSize}x{$tnSize}.{$ext}";
    $imgName = "logo--{$hash}_{$size}x{$size}.{$ext}";
    $tn = "{$__DIR_ROOT}/web/upload/logo/{$tnName}";
    $img = "{$__DIR_ROOT}/web/upload/logo/{$imgName}";
    $tnUrl = "/upload/logo/{$tnName}";
    $imgUrl = "/upload/logo/{$imgName}";
    
    $imgSize = getimagesize($file);
    $longerSide = ( $imgSize[0] > $imgSize[1] ? $imgSize[0] : $imgSize[1] );
    if ( $longerSide < $size ) {
        $size = $longerSide;
    } // end if
    $scale = (int) $tnSize / 4;

    $commands = [];
    $commands[] = "convert -define jpeg:size={$doubleTnSize}x{$doubleTnSize} '{$file}' -delete 1--1 -thumbnail {$tnSize}x{$tnSize}^ -gravity center -extent {$tnSize}x{$tnSize} -scale {$scale}x{$scale} -scale {$tnSize}x{$tnSize} '{$tn}'";
    $commands[] = "convert -define jpeg:size={$doubleSize}x{$doubleSize} '{$file}' -delete 1--1 -scale {$size}x{$size} '{$img}'";
    foreach ($commands as $command) {
        exec($command);
    } // end foreach
    
    return ( filesize($tn) && filesize($img) ? [ 'tn' => $tnUrl, 'img' => $imgUrl ] : false );
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
} // end function

/**
 * @uses global $http_credentials
 */
function require_http_auth() {
    global $http_credentials;
	
    $has_supplied_credentials = !(empty($_SERVER['PHP_AUTH_USER']) && empty($_SERVER['PHP_AUTH_PW']));
    if ( $has_supplied_credentials ) {
        if ( array_key_exists($_SERVER['PHP_AUTH_USER'], $http_credentials) ) {
            if ( $http_credentials[$_SERVER['PHP_AUTH_USER']] === nvl($_SERVER['PHP_AUTH_PW']) ) {
                return true;
            } // end if
        } // end if
    } // end if
	
	header('HTTP/1.1 401 Authorization Required');
    header('WWW-Authenticate: Basic realm="Access denied"');
    exit;
} // end function

function fulltext_search($q) {
    global $app;
    $results = [];

    $fulltext = normalize($q);

    if ( mb_strlen($fulltext) < 2 ) {
        // $results[] = [ 'name' => 'Write at least two characters for search', 'url' => wwwroot(), 'cls' => 'unclickable' ];
        return $results;
    } // end if

    $thingRows = $app['sql']->select("id, MATCH(name, summary) AGAINST ('*{$fulltext}*') AS score")
         ->from('thing')
         ->where("MATCH(name, summary) AGAINST ('*{$fulltext}*' IN BOOLEAN MODE)")
         ->and('deleted_at IS NULL')
         ->and('approved_at IS NOT NULL')
         ->orderBy("score DESC, id DESC")
         ->limit(6)
         ->fetchAll();
    
    foreach ($thingRows as $row) {
        $results[] = get_thing($row['id']);
    } // end foreach
    
    $categoryIds = $app['sql']->select('id')->from('category')->where('name LIKE %like~', $q)->and('deleted_at IS NULL')->limit(3)->fetchPairs();

    foreach ($categoryIds as $id) {
        $category = get_category($id);
        $category['summary'] = 'Category';
        $results[] = $category;
    } // end foreach

    // if ( ! count($results) ) {
    //     $results[] = [ 'name' => 'Nothing found', 'url' => wwwroot(), 'cls' => 'unclickable' ];
    // } // end if

    return $results;
} // end function

function csrf_passed() {
    global $app;

    $request = $app['request_stack']->getCurrentRequest();
    $token = $headers = $request->headers->get('X-CSRF-TOKEN');
    return $app['csrf.token_manager']->isTokenValid(new CsrfToken($app['session']->getId(), $token));
} // end function

function get_option_median($thingId, $optionSlug){
    global $app;

    $key = "{$thingId}x{$optionSlug}";
    if ( $cached = $app['cache']->fetch($key) ) {
        return $cached;
    } // end if

    $thingId = (int) $thingId;
    $optionSlug = slugify($optionSlug);

    $median = $app['sql']->query("SELECT x.`value` 
                                  FROM thing_option x, thing_option y
                                  WHERE x.thing_id = {$thingId}
                                  AND x.value_slug = '{$optionSlug}'
                                  AND x.deleted_at IS NULL
                                  GROUP BY x.`value`
                                  HAVING SUM(SIGN(1-SIGN(y.`value`-x.`value`)))/COUNT(*) > .5
                                  LIMIT 1")->fetchSingle();
    
    $app['cache']->store($key, $median, 60);
    return $median;
} // end function

function get_top_categories() {
    global $app;

    $ids = $app['sql']->query("SELECT mn.category_id
                        FROM thing_mn_category mn
                        INNER JOIN category c ON c.id = mn.category_id
                        WHERE c.deleted_at IS NULL
                        GROUP BY mn.category_id 
                        ORDER BY COUNT(mn.category_id) DESC
                        LIMIT 6")->fetchPairs();

    $categories = [];
    foreach ($ids as $id) {
        if ( $cat = get_category($id) ) {
            $categories[] = $cat;
        } // end if
    } // end foreach
    return $categories;
} // end function

function get_top_labels() {
    global $app;

    $ids = $app['sql']->query("SELECT mn.label_id
                        FROM thing_mn_label mn
                        INNER JOIN label c ON c.id = mn.label_id
                        WHERE c.deleted_at IS NULL
                        GROUP BY mn.label_id 
                        ORDER BY COUNT(mn.label_id) DESC
                        LIMIT 6")->fetchPairs();
    
    $labels = [];
    foreach ($ids as $id) {
        if ( $label = get_label($id) ) {
            $labels[] = $label;
        } // end if
    } // end foreach
    return $labels;
} // end function

function fetch_summary_from_title($url) {
    global $app;

    if ( filter_var($url, FILTER_VALIDATE_URL) ) {
        $html = load_page_content($url);
        $matches = [];
        preg_match('~<title>(?<summary>.+)<\/title>~us', $html, $matches);
        if ( ! empty($matches['summary']) ) {
            return $matches['summary'];
        } // end if
    } // end if
    return null;
} // end function

function load_page_content($url) {
    global $app;

    $key = "Html from: {$url}";
    if ( $cached = $app['cache']->fetch($key) ) return $cached;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_POST, 0);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8",
        "User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_12_6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/60.0.3112.90 Safari/537.36",
        // "Referer: https://www.google.com",
        // "Origin: https://www.google.com",
        "Content-Type: application/x-www-form-urlencoded",
        "Accept-Language: en-US,en;q=0.5",
    ]);
    curl_setopt($ch, CURLOPT_PROGRESSFUNCTION, function($downloadSize, $downloaded, $uploadSize, $uploaded){
        $limit = 10 * 1024 * 1024; // 10M
        return ($downloaded > $limit) ? 1 : 0;
    });

    $out = curl_exec($ch);
    curl_close($ch);

    if ( strlen($out) > 0 ) {
        $app['cache']->store($key, $out, 3600);
    } // end if
    
    return $out;
} // end function

function load_image_content($url) {
    global $app;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    // curl_setopt($ch, CURLOPT_HEADER, 1);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_POST, 0);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Accept: text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8",
        "User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_12_6) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/60.0.3112.90 Safari/537.36",
        "Content-Type: application/x-www-form-urlencoded",
        "Accept-Language: en-US,en;q=0.5",
    ]);
    curl_setopt($ch, CURLOPT_PROGRESSFUNCTION, function($downloadSize, $downloaded, $uploadSize, $uploaded){
        $limit = 10 * 1024 * 1024; // 10M
        return ($downloaded > $limit) ? 1 : 0;
    });

    $out = curl_exec($ch);
    curl_close($ch);
    
    $f = finfo_open();
    $mime = finfo_buffer($f, $out, FILEINFO_MIME_TYPE);
    
    if ( $out && $mime ) {
        return [
            'Content-Type' => $mime,
            'data' => $out,
        ];
    } else {
        return null;
    } // end if-else

} // end function

function initialize_params($app) {
    global $TWIG_HELPERS;
    $request = $app['request_stack']->getCurrentRequest();

    $params = [];
    $params['sort'] = $request->get('sort') ? $request->get('sort') : 'magic';
    $params['page'] = $request->get('page') ? $request->get('page') : 1;
    $params['perPage'] = THINGS_PER_PAGE;
    $params['csrf'] = $app['csrf.token_manager']->getToken($app['session']->getId());
    $params['me'] = me();
    $params['wwwroot'] = wwwroot();
    $params['clear_me'] = clear_me();
    $params['helpUrl'] = make_url('page', 1);
    $params['appName'] = 'Trustworthy.biz';
    $params['title'] = 'Which app is worth your time & money?';
    $params['description'] = 'Crowdsourced list of well established apps, websites and projects';
    $params['topCategories'] = get_top_categories();
    $params['topLabels'] = get_top_labels();
    
    $params['sorting'] = [
        ['magic', 'Magic'],
        ['top', 'Top score'],
        ['new', 'Newest first'],
        ['a-z', 'Alphabetically'],
    ];

    $params['helpers'] = new DynamicMockupDI;
    $params['helpers']->print_crowdsourced_field = function($thingId, $optionSlug){
        $median = get_option_median($thingId, $optionSlug);
        $crate = Option::get($optionSlug);

        if ( false === $median || null === $median ) {
            $val = $crate->empty;
        } else {
            $val = Option::val($crate, $median)[1];
        } // end if-else

        $o = '';
        $o .= '<span class="value pointer tap-to-edit" title="Tap to edit" data-toggle="tooltip">'.htmlentities($val).'</span>';
        $o .= '<select class="form-control hidden pointer live-update" data-id="'.$thingId.'" data-slug="'.htmlentities($optionSlug).'">';
        $o .= '<option value="">- - -</option>';
        foreach ($crate->values as $row) {
            $selected = $row[0] == $median ? 'selected="selected"' : '';
            $o .= '<option '.$selected.' value="'.htmlentities($row[0]).'">'.htmlentities($row[1]).'</option>';
        } // end foreach
        $o .= '</select>';
        return $o;
    };
    $params['helpers']->make_url = function($what, $id){
        return make_url($what, $id);
    };
    $params['helpers']->clean_url = function($url){
        $url = mb_strtolower($url);
        $url = str_replace(['http://', 'https://', 'www.'], '', $url);
        if ( '/' == substr($url, -1) ) {
            $url = substr($url, 0, strrpos($url, '/'));
        } // end if
        return $url;
    };
    $params['helpers']->make_url = function(){
        return call_user_func_array('make_url', func_get_args());
    };
    $params['helpers']->crowd_class = function($thing, $optionSlug){
        $median = get_option_median($thing['id'], $optionSlug);
        if ( $median ) {
            $crate = Option::get($optionSlug);
            if ( $crate->colorizable ) {
                $val = Option::val($crate, $median);
                if ( in_array($val[2], [Grade::A_PLUS]) ) {
                    return 'good';
                } elseif ( in_array($val[2], [Grade::A]) ) {
                    return 'okay';
                } elseif ( in_array($val[2], [Grade::F, Grade::E, Grade::D]) ) {
                    return 'bad';
                } elseif ( in_array($val[2], [Grade::C]) ) {
                    return 'warn';
                } else {
                    return '';
                } // end if-else
            } // end if
        } // end if
    };

    return $params;
} // end function

function smart_urlencode($url) {
    return implode('/', array_map('urlencode', explode('/', $url)));
} // end function
