<?php

namespace parserbot\megaparser;

interface MessengerInterface {

    function addMessage($text, $type ='info');
    function getMessages();
    function __construct($options);

}