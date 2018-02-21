<?php

class OptionCrate {
    public static $a = ['🐭', '🐁', '🖱️', '🐀'];
    public static function def() { return 'Tap to enter value! ' . self::$a[array_rand(self::$a)]; }
    public $empty;
    public $values = [];
    public function __construct(string $empty = null, array $values) {
        $this->empty = $empty ? $empty : self::def();
        $this->values = $values;
    } // end method
}

abstract class Option {
    public static function get($slug) {
        global $OPTIONS;
        if ( array_key_exists($slug, $OPTIONS) ) {
            return $OPTIONS[$slug];
        } else {
            throw new InvalidArgumentException;
        } // end if
    }
    public static function val(OptionCrate $crate, $value) {
        foreach ($crate->values as $row) {
            if ( $row[0] == $value ) {
                return $row;
            } // end if
        } // end foreach
    }
}

abstract class Grade {
    const A_PLUS = 'a+';
    const A = 'a';
    const B = 'b';
    const C = 'c';
    const D = 'd';
    const E = 'e';
    const F = 'f';
}

$OPTIONS = [
    'founded' => new OptionCrate(null, [
         ['last-12-months', 'In last 12 months 👶', Grade::D],
         ['over-1-year-ago', 'Over 1 year ago 👦', Grade::B],
         ['over-3-years-ago', 'Over 3 years ago 👨', Grade::A],
         ['over-5-years-ago', 'Over 5 years ago 👴', Grade::A_PLUS],
    ]),
    'pricing' => new OptionCrate(null, [
        ['free', 'Free 😃', Grade::C],
        ['freemium', 'Freemium 😎', Grade::A_PLUS],
        ['free-personal', 'Free for personal use 🏠', Grade::B],
        ['commercial', 'Commercial 🏦', Grade::A],
   ]),
   'pricing-model' => new OptionCrate(null, [
        ['one-time', 'One time purchase 💵', Grade::B],
        ['subscription', 'Subscription 🔄', Grade::A],
        ['both', 'Both 🤔', Grade::A_PLUS],
        ['n-a', 'Not applicable', null],
    ]),
];

