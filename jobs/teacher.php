<?php

/*
 * This file is part of the trustworthy.biz project (http://trustworthy.biz/)
 * Copyright (c) 2018 Petr Nagy (http://www.petrnagy.cz/)
 * See readme.txt for more information
 */

$thingIds = $app['sql']->select('id')->from('thing')
                       ->where('deleted_at IS NULL')
                       ->and('approved_at IS NOT NULL')
                       ->and('is_revision_of IS NULL')
                       ->fetchPairs();

foreach ($thingIds as $thingId) {
    $perc = 0.00;
    $score = [];
    $optionRows = $app['sql']->select('*')->from('thing_option')->where('thing_id = %i', $thingId)->groupBy('value_slug')->fetchAll();
    if ( count($optionRows) ) {
        foreach ($optionRows as $optionRow) {
            $median = get_option_median($thingId, $optionRow['value_slug']);
            if ( array_key_exists($optionRow['value_slug'], $OPTIONS) ) {
                $optionData = Option::val($OPTIONS[$optionRow['value_slug']], $median);
                if ( $optionData ) {
                    $score[] = grade2score($optionData[2]);
                } // end if
            } // end if
        } // end foreach
    } // end if

    if ( count($score) ) {
        $perc = array_sum($score) / count($score);
    } // end if

    $name = $app['sql']->select('name')->from('thing')->where('id = %i', $thingId)->fetchSingle();
    if ( $perc ) {
        success("Thing '{$name}' - score: {$perc}");
    } else {
        info("Thing '{$name}' - score: NULL");
    } // end if-else
    
    // ~ ~ ~ MAGIC ~ ~ ~
    if ( $perc > 10 && $perc < 95 ) {
        $perc += rand(-5, 5);
    } // end if

    $app['sql']->update('thing', ['score' => $perc])->where('id = %i', $thingId)->execute();

    if ( $perc ) {
        $lastLogWrite = $app['sql']->select('created_at')->from('score_log')->where('thing_id = %i', $thingId)->orderBy('id DESC')->limit(1)->fetchSingle();
        if ( ! $lastLogWrite || (time() - $lastLogWrite->getTimestamp()) > 86400 ) {
            $app['sql']->insert('score_log', [
                'thing_id' => $thingId,
                'created_at' => new DateTime,
                'score' => $perc,
            ])->execute();
        } // end if    
    } // end if
    
} // end foreach


