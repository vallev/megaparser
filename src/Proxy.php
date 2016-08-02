<?php

namespace parserbot\megaparser;
class Proxy extends ActiveRecord{
    public $proxy = '';

    public static function getNext() {
        return new Proxy();
    }
}