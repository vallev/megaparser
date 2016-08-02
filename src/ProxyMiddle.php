<?php

namespace parserbot\megaparser;

class ProxyMiddle extends BaseParser{
    public function __construct($options)
    {
        parent::__construct($options);
        $this->proxy = Proxy::find()->where('blocked_till < NOW() OR ISNULL(blocked_till)')->orderBy("RAND()")->one();
    }

    public function proxyRenew($filename)
    {
        Proxy::deleteAll('id > 0');
        $proxies = file($filename);
        foreach ($proxies as $proxy) {
            $p = new Proxy();
            $p->proxy = $proxy;
            $p->save();
        }
    }

    public function changeProxy($block = false)
    {
        if ($block) {
            $date = new \DateTime('tomorrow');
            $this->proxy->blocked_till = $date->format('Y-m-d');
            $this->proxy->save();
        }
        $this->proxy = Proxy::getNext();
        $date = new \DateTime('NOW');
        $this->proxy->last_used = $date->format('Y-m-d H:i:s');
        $this->proxy->save();
    }

    public function blockAndChangeProxy()
    {
        $this->proxy->blocked_till = date("Y-m-d", strtotime("+1 day", strtotime(date("d.m.Y"))));
        $this->proxy->save();
        $this->proxy = Proxy::find()->where('blocked_till < NOW() OR ISNULL(blocked_till)')->orderBy("RAND()")->one();
    }

    protected function delayProxy($proxy)
    {
        return true;
    }

    public function getProxy()
    {
        /*$proxy = new Proxy();
        $proxy->proxy = 'tcp://127.0.0.1:8888';
        return $proxy;*/

        $proxy = Proxy::getNext();
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
    }
}