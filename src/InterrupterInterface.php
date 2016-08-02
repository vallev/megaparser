<?php

namespace parserbot\megaparser;

interface InterrupterInterface {

    function checkStopFlag();
    function __construct($options);

}