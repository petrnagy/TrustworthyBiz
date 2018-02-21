<?php

print "Starting\n\n";

$html = file_get_contents(__DIR__ . '/bl.html');

$matches = [];

preg_match_all('~<li.+?class="tag__icon">.+?<\/div>.+?(?<tag>.+?)<div class="tag__count">(?<cnt>\d+)<.+?<\/li>~su', $html, $matches);

$o = [];

for ( $i = 0; $i < count($matches['tag']); $i++ ) { 
    $tag = $matches['tag'][$i];
    $cnt = $matches['cnt'][$i];
    if ( $cnt >= 10 ) {
        $tag = trim( mb_strtolower( $tag ) );
        $q = "INSERT INTO `label` (`name`, created_at) VALUES ('{$tag}', NOW());";
        $o[] = $q;
    } // end if
}

$bytes = file_put_contents(__DIR__ . '/bl_out.txt', implode(PHP_EOL, $o));

print "Wrote {$bytes} bytes\n\n";

exit;