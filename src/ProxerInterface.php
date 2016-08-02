<?php

namespace parserbot\megaparser;

interface ProxerInterface {

    function blockProxy($proxy);
    function getProxy();
    function delayProxy($proxy);
    function blockAndChangeProxy();
    function changeProxy($block = false);
    function proxyRenew($filename);
    function __construct($options);

}