<?php

/*
 * This file is part of the trustworthy.biz project (http://trustworthy.biz/)
 * Copyright (c) 2018 Petr Nagy (http://www.petrnagy.cz/)
 * See readme.txt for more information
 */

class OptionCrate {
    public static $a = ['🐭', '🐁', '🖱️', '🐀', '🐱', '😺', '🐈', '🐶', '🐾'];
    public static function def() { return 'Tap to rate! ' . self::$a[array_rand(self::$a)]; }
    public $empty;
    public $colorizable;
    public $values = [];
    public function __construct(string $empty = null, $colorizable = true, array $values) {
        $this->empty = $empty ? $empty : self::def();
        $this->colorizable = (bool) $colorizable;
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
    'founded' => new OptionCrate(null, true, [
         ['last-12-months', 'In last 12 months 👶', Grade::D],
         ['over-1-year-ago', 'Over 1 year ago 👦', Grade::B],
         ['over-3-years-ago', 'Over 3 years ago 👨', Grade::A],
         ['over-5-years-ago', 'Over 5 years ago 👴', Grade::A_PLUS],
    ]),
    'pricing' => new OptionCrate(null, false, [
        ['free', 'Free 😃', Grade::B],
        ['freemium', 'Freemium 😎', Grade::A_PLUS],
        ['free-personal', 'Free for personal use 🏠', Grade::B],
        ['commercial', 'Commercial 🏦', Grade::A],
   ]),
   'pricing-model' => new OptionCrate(null, false, [
        ['one-time', 'One time purchase 💵', Grade::B],
        ['subscription', 'Subscription 🔄', Grade::A],
        ['both', 'Both 🤔', Grade::A_PLUS],
        ['ads', 'Ads 😠', Grade::C],
        
    ]),
    'i-would-recommend' => new OptionCrate(null, true, [
        ['absolutely', 'Absolutely 👍', Grade::A_PLUS],
        ['yes', 'Yes 😊', Grade::A],
        ['no', 'Nope 🤔', Grade::A_PLUS],
        ['angry', 'Hell no 👿', null],
    ]),
    'easy-to-use' => new OptionCrate(null, true, [
        ['very', 'Very easy 👍', Grade::A_PLUS],
        ['ok', 'It\'s allright 😊', Grade::A],
        ['mediocre', 'Mediocre 🤔', Grade::A],
        ['difficult', 'Difficult 🕖', Grade::C],
    ]),
    'ads' => new OptionCrate(null, true, [
        ['none', 'None 😊', Grade::A_PLUS],
        ['some', 'Yes, some 🤔', Grade::B],
        ['a-lot', 'Yes, a lot 👎', Grade::F],
        ['only-for-free', 'Only for free users 😎', Grade::A],
    ]),
    'time-consuming' => new OptionCrate(null, true, [
        ['none', 'Not at all 😊', Grade::A_PLUS],
        ['some', 'Mediocre 🤔', Grade::A],
        ['a-lot', 'Will die using this 👎', Grade::B],
    ]),
    'support' => new OptionCrate(null, true, [
        ['awesome', 'Awesome 😍', Grade::A_PLUS],
        ['okay', 'Okay 🤔', Grade::A],
        ['bad', 'Bad 👎', Grade::C],
    ]),
    'community' => new OptionCrate(null, true, [
        ['awesome', 'Awesome 😍', Grade::A_PLUS],
        ['okay', 'Okay 🤔', Grade::A],
        ['hostile', 'Hostile 👎', Grade::C],
    ]),
    'opensource' => new OptionCrate(null, false, [
        ['awesome', 'Yes 😀', Grade::A_PLUS],
        ['okay', 'No 😟', Grade::B], 
    ]),
    'environment' => new OptionCrate(null, false, [
        ['care', 'They care 🙉', Grade::A_PLUS],
        ['dont-care', 'Neutral 🤔', Grade::B],
        ['against', 'Questionable 💣', Grade::D],
    ]),
    'windows' => new OptionCrate(null, true, [
        ['great', 'Great support 😊', Grade::A_PLUS],
        ['okay', 'Okay 🙂', Grade::A],
        ['bad', 'Bad support 😠', Grade::C],
        ['none', 'No support 🏳️', null],
    ]),
    'osx' => new OptionCrate(null, true, [
        ['great', 'Great support 😊', Grade::A_PLUS],
        ['okay', 'Okay 🙂', Grade::A],
        ['bad', 'Bad support 😠', Grade::C],
        ['none', 'No support 🏳️', null],
    ]),
    'linux' => new OptionCrate(null, true, [
        ['great', 'Great support 😊', Grade::A_PLUS],
        ['okay', 'Okay 🙂', Grade::A],
        ['bad', 'Bad support 😠', Grade::C],
        ['none', 'No support 🏳️', null],
    ]),
    'ios' => new OptionCrate(null, true, [
        ['great', 'Great support 😊', Grade::A_PLUS],
        ['okay', 'Okay 🙂', Grade::A],
        ['bad', 'Bad support 😠', Grade::C],
        ['none', 'No support 🏳️', null],
    ]),
    'android' => new OptionCrate(null, true, [
        ['great', 'Great support 😊', Grade::A_PLUS],
        ['okay', 'Okay 🙂', Grade::A],
        ['bad', 'Bad support 😠', Grade::C],
        ['none', 'No support 🏳️', null],
    ]),
    'other-mobile' => new OptionCrate(null, true, [
        ['great', 'Great support 😊', Grade::A_PLUS],
        ['okay', 'Okay 🙂', Grade::A],
        ['bad', 'Bad support 😠', Grade::C],
        ['none', 'No support 🏳️', null],
    ]),
    'apple-tv' => new OptionCrate(null, false, [
        ['great', 'Great support 😊', Grade::A_PLUS],
        ['okay', 'Okay 🙂', Grade::A],
        ['bad', 'Bad support 😠', Grade::C],
        ['none', 'No support 🏳️', null],
    ]),
    'android-tv' => new OptionCrate(null, false, [
        ['great', 'Great support 😊', Grade::A_PLUS],
        ['okay', 'Okay 🙂', Grade::A],
        ['bad', 'Bad support 😠', Grade::C],
        ['none', 'No support 🏳️', null],
    ]),
    'cloud-features' => new OptionCrate(null, false, [
        ['cloud-based', 'Cloud based 🌤️', Grade::A],
        ['optional', 'Optional ⛅', Grade::A_PLUS],
        ['some', 'Some, but not all ☁️', Grade::B],
        ['none', 'No clouds 🌧️', Grade::C],
    ]),
    'who-owns-your-content' => new OptionCrate(null, true, [
        ['you', 'You 🙋', Grade::A_PLUS],
        ['they', 'They 🏢', Grade::F],
        ['neither', 'Neither', null],
    ]),
    'terms-and-conditions' => new OptionCrate(null, true, [
        ['friendly', 'Friendly 😉', Grade::A_PLUS],
        ['okay', 'Okay 😐', Grade::B],
        ['bad', 'Bad 😥', Grade::C],
    ]),
    'easy-migration' => new OptionCrate(null, true, [
        ['easy', 'Very easy 😉', Grade::A_PLUS],
        ['doable', 'Doable 😤', Grade::A],
        ['impossible', 'Bad 😥', Grade::D],
    ]),
];

