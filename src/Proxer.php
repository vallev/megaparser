<?php

namespace parserbot\megaparser;

class Proxer implements ProxerInterface {
    public function __construct($options)
    {

        $this->proxy_class = isset($options['proxy_class'])?$options['proxy_class']:'parserbot\\megaparser\\Proxy';

        $class = $this->proxy_class;
        $this->proxy = $class::find()->where('blocked_till < NOW() OR ISNULL(blocked_till)')->orderBy("RAND()")->one();
    }

    public function proxyRenew($filename)
    {
        $class = $this->proxy_class;
        $class::deleteAll('id > 0');
        $proxies = file($filename);
        foreach ($proxies as $proxy) {
            $p = new Proxy();
            $p->proxy = $proxy;
            $p->save();
        }

        return true;
    }

    public function changeProxy($block = false)
    {
        if ($block) {
            $date = new \DateTime('tomorrow');
            $this->proxy->blocked_till = $date->format('Y-m-d');
            $this->proxy->save();
        }

        $this->proxy = $this->getProxy();

        return true;
    }

    public function blockAndChangeProxy()
    {
        $class = $this->proxy_class;
        $this->proxy->blocked_till = date("Y-m-d", strtotime("+1 day", strtotime(date("d.m.Y"))));
        $this->proxy->save();

        $this->proxy = $this->getProxy();

        return true;
    }

    public function delayProxy($proxy)
    {
        return true;
    }

    public function getProxy()
    {
        $class = $this->proxy_class;
        $proxy = $class::find()->where('blocked_till < NOW() OR ISNULL(blocked_till)')->orderBy(['last_used'=>SORT_ASC])->one();
        $date = new \DateTime('NOW');
        $proxy->last_used = $date->format('Y-m-d H:i:s');
        $proxy->save();

        return $proxy;
    }

    public function blockProxy($proxy)
    {
        $date = new \DateTime('tomorrow');
        $proxy->blocked_till = $date->format('Y-m-d');
        $proxy->save();
        return true;
    }

    public function setProxy()
    {
        return true;
    }

}