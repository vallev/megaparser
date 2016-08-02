<?php

namespace parserbot\megaparser;

class ActiveRecord {

    public static $class = 'parserbot\\megaparser\\ActiveRecord';

    public function save()
    {
        return true;
    }

    public function getErrors()
    {
        return false;
    }

    public static function find()
    {
        return new Query(self::$class);
    }

    public static function findOne($condition)
    {

        return self::find()->one();
    }

    public static function deleteAll($condition)
    {
        return true;
    }

}