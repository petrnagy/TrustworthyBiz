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

function get_types() {
    global $app;

    $types = [];
    $ids = $app['sql']->select('id')->from('type')->where('deleted_at IS NULL')->orderBy('name ASC')->fetchPairs();
    foreach ($ids as $id) {
        $types[] = get_type($id);
    } // end foreach
    return $types;
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

function get_type($id) {
    global $app;

    return $app['sql']->select('*')->from('type')->where('id = %i', $id)->and('deleted_at IS NULL')->fetch();
} // end function

function get_things($category_id = null) {
    global $app;

    $stmt = $app['sql']->select('thing.id')->from('thing')->where('thing.deleted_at IS NULL')->and('approved_at IS NOT NULL');
    if ( $category_id ) {
        $stmt->and('thing_mn_category.category_id = %i', $category_id);
        $stmt->innerJoin('thing_mn_category')->on('thing_mn_category.thing_id = thing.id');
    } // end if
    $stmt->orderBy('thing.score DESC');
    $ids = $stmt->fetchPairs();

    $things = [];
    foreach ($ids as $id) {
        $things[] = get_thing($id);
    } // end foreach
    
    return $things;
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
    $row['url'] = make_url('thing', $id);
    $categories = $types = [];

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

    $row['grade'] = get_grade($row['score']);

    return $row;
} // end function

function get_similar($id) {
    global $app;
    $similar = [];

    $categoryIds = $app['sql']->select('category_id')->from('thing_mn_category')->where('thing_id = %i', $id)->fetchPairs();
    $ids = $app['sql']->select('thing_id')->from('thing_mn_category')->where('category_id IN %in', $categoryIds)->and('thing_id != %i', $id)->fetchPairs();
    $filteredIds = $app['sql']->select('id')->from('thing')->where('id IN %in', $ids)->and('deleted_at IS NULL')->and('approved_at IS NOT NULL')->orderBy('score DESC, id DESC')->fetchPairs();

    foreach ($filteredIds as $filteredId) {
        if ( $thing = get_thing($filteredId) ) {
            $similar[] = $thing;
            if ( count($similar) == 5 ) {
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
    if ( mb_strlen(trim(nvl($data['summary']))) < 10 ) {
        $errors[] = 'Summary must have at least 10 characters.';
    } // end if
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

    return $data;
} // end function

function new_thing($data) {
    global $app;

    $data = sanitize_thing($data);
    $app['sql']->insert('thing', [
        'name'              => $data['name'],
        'summary'           => $data['summary'],
        'homepage'          => $data['homepage'],
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
            'type_id'   => $typeId,
            'thing_id'      => $id
        ])->execute();
    } // end foreach
    
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
    
    return get_thing($data['id']);
} // end function

function vote($thingId, $optionSlug, $value) {
    global $app;
    $request = $app['request_stack']->getCurrentRequest();

    // TODO: najit dle session posledni hlas a prepsat
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
        $data['categories'] = $data['types'] = [];
        
        foreach ($thing['categories'] as $category)     $data['categories'][] = $category['id'];
        foreach ($thing['types'] as $type)              $data['types'][] = $type['id'];

        $data['categories'] = implode(';', $data['categories']);
        $data['types'] = implode(';', $data['types']);
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

    $file = "/tmp/{$hash}.$ext";
    if ( ! move_uploaded_file($uploadedFile['tmp_name'], $file) ) {
        throw new Exception('Could not move file to temporary location');
    } // end if

    $tnName = "[tn]{$hash}_{$tnSize}x{$tnSize}.{$ext}";
    $imgName = "[logo]{$hash}_{$size}x{$size}.{$ext}";
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
    $commands[] = "convert -define jpeg:size={$doubleTnSize}x{$doubleTnSize} '{$file}' -thumbnail {$tnSize}x{$tnSize}^ -gravity center -extent {$tnSize}x{$tnSize} -scale {$scale}x{$scale} -scale {$tnSize}x{$tnSize} '{$tn}'";
    $commands[] = "convert -define jpeg:size={$doubleSize}x{$doubleSize} '{$file}' -scale {$size}x{$size} '{$img}'";
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

    if ( mb_strlen($fulltext) < 3 ) {
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

function initialize_params($app) {
    global $TWIG_HELPERS;

    $params = [];
    $params['csrf'] = $app['csrf.token_manager']->getToken($app['session']->getId());
    $params['me'] = me();
    $params['clear_me'] = clear_me();
    $params['helpUrl'] = make_url('page', 1);
    $params['appName'] = 'Trustworthy.biz';
    $params['title'] = 'Which app is worth your time & money?';
    $params['description'] = 'Crowdsourced list of well established apps, websites and projects';
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
        $o .= '<span class="value underline-info pointer tap-to-edit" title="Tap to edit" data-toggle="tooltip">'.htmlentities($val).'</span>';
        $o .= '<select class="form-control hidden pointer live-update" data-id="'.$thingId.'" data-slug="'.htmlentities($optionSlug).'">';
        $o .= '<option value="">- - -</option>';
        foreach ($crate->values as $row) {
            $selected = $row[0] == $val ? 'selected="selected"' : '';
            $o .= '<option '.$selected.' value="'.htmlentities($row[0]).'">'.htmlentities($row[1]).'</option>';
        } // end foreach
        $o .= '</select>';
        return $o;
    };

    return $params;
} // end function
