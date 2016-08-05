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

    public static function findBetween($start, $end, $content){
        $r = explode($start, $content);
        if (array_key_exists(1,$r)){
            $r = explode($end, $r[1]);
            return $r[0];
        }
        return '';
    }

    public static function encodeURI($uri)
    {
        return preg_replace_callback("{[^0-9a-z_.!~*'();,/?:@&=+$#-]}i", function ($m) {
            return sprintf('%%%02X', ord($m[0]));
        }, $uri);
    }
}