<?php

/*
 * This file is part of the trustworthy.biz project (http://trustworthy.biz/)
 * Copyright (c) 2018 Petr Nagy (http://www.petrnagy.cz/)
 * See readme.txt for more information
 */

if ( 'cli' === strtolower(PHP_SAPI) ) {
    trigger_error("This application cannot run in CLI mode", E_USER_ERROR);
} // end if

$__DIR_ROOT = dirname(__DIR__);

require $__DIR_ROOT . '/app/config.php';

require $__DIR_ROOT . '/app/lib.php';
