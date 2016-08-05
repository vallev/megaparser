<?php

namespace parserbot\megaparser;

use php_rutils\RUtils;

class ParserUtils {
    public static function translit($text) {
        $text = trim($text);
        //$text = preg_replace('/[^\s0-9-а-яА-Я]/iu','',$text);
        $text = RUtils::translit()->slugify($text);
        return $text;
    }
}