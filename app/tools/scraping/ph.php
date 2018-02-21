<?php

print "Starting\n\n";

$html = file_get_contents(__DIR__ . '/ph.html');

$matches = [];

preg_match_all('~<div class="item_.+?>.+?<a class="info_.+?><span.+?>(?<tag>.+?)<~su', $html, $matches);

$o = [];

foreach ($matches['tag'] as $tag) {
    $tag = trim( mb_strtolower( $tag ) );
    $q = "INSERT INTO `label` (`name`, created_at) VALUES ('{$tag}', NOW());";
    $o[] = $q;
} // end foreach

$bytes = file_put_contents(__DIR__ . '/ph_out.txt', implode(PHP_EOL, $o));

print "Wrote {$bytes} bytes\n\n";

exit;