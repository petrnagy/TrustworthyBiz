<?php

/*
 * This file is part of the trustworthy.biz project (http://trustworthy.biz/)
 * Copyright (c) 2018 Petr Nagy (http://www.petrnagy.cz/)
 * See readme.txt for more information
 */

$__globalLog = [];

function _dump($msg) {
    echo colorMessage("DUMP: " . print_r($msg, true), '31', 'red');
    exit;
} // end function

function fatal($msg) {
    echo colorMessage("FATAL: {$msg}", '31', 'red');
    exit;
} // end function

function error($msg) {
    $o = colorMessage($msg, '31', 'red');
    echo $o;
    return $o;
} // end function

function success($msg) {
    $o = colorMessage($msg, '32', 'green');
    echo $o;
    return $o;
} // end function

function info($msg) {
    $o = colorMessage($msg, '36', 'cyan');
    echo $o;
    return $o;
} // end function

function warning($msg) {
    $o = colorMessage($msg, '33', 'orange');
    echo $o;
    return $o;
} // end function

function message($msg) {
    $o = colorMessage($msg, '37', 'black');
    echo $o;
    return $o;
} // end function

function colorMessage($msg, $color, $htmlColor) {
    global $__globalLog;
    $o = '';

    if ( 'cli' === php_sapi_name() ) {
        $o .= "\033[{$color}m" . '[' . getdt() . '] ' . $msg . "\033[0m" . "\n";
    } else {
        $o .= "<pre style='color: {$htmlColor};'>" . '[' . getdt() . '] ' . $msg . "\n</pre>";
    } // end if-else

    $__globalLog[] = '[' . getdt() . '] ' . $msg;

    return $o;
} // end function

function getlog() {
    global $__globalLog;
    return implode(PHP_EOL, $__globalLog);
} // end fundtion

function getdt() {
    return date('Y-m-d H:i:s');
} // end function

function lock($alias) {
    touch( getLockPath($alias) );
} // end function

function unlock($alias) {
    unlink( getLockPath($alias) );
} // end function

function isLocked($alias) {
    static $tolerance = 3600; // 1 hour
    $filename = getLockPath($alias);
    message("Checking if {$alias} is locked...");
    $ret = null;
    
    if ( defined('FORCE_RUN') ) {
        info("[detected --force run]");
        $ret = false;
    } elseif ( file_exists($filename) ) {
        if ( ( time() - filemtime($filename) ) > $tolerance ) {
            $ret = false;
        } else {
            $ret = true;
        } // end if-else
    } else {
        $ret = false;
    } // end if-else

    if ( $ret ) {
        message("...LOCKED");
    } else {
        message("...unlocked");
    } // end if-else
    return $ret;
} // end function   

function getLockPath($alias) {
    return __DIR__ . '/' .  '~' . normalize($alias) . '.lock';
} // end function
