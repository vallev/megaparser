<?php

namespace parserbot\megaparser;

class Job extends ActiveRecord {

    public static $class = 'parserbot\\megaparser\\Job';
    const STATUS_FINISHED = 1;

}