<?php

/*
 * This file is part of the trustworthy.biz project (http://trustworthy.biz/)
 * Copyright (c) 2018 Petr Nagy (http://www.petrnagy.cz/)
 * See readme.txt for more information
 */

$category = null;
$url = null;

foreach ($argv as $k => $arg) {
    if ( strpos($arg, 'category:') !== false ) {
        $categoryId = (int) str_replace('category:', '', $arg);
        $category = $app['sql']->select('')
    } // end if
} // end foreach

