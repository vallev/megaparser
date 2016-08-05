<?php
namespace parserbot\megaparser;

class Task {

    public $method;
    public $headers;
    public $proxy;
    public $cookie;
    public $url;
    public $completed;
    public $finished;
    public $failed;
    public $retry;
    public $callback;
    public $params;
    public $html;
    public $custom;
    public $concurrency;
    public $multipart;
    public $preserve_proxy;
    public $curl_options;
    public $verify;
    public $response = null;
    public $effective_url = '';
    public $base_url = false;
    public $options = [];

    public function __construct($url, $method = 'GET', $options = [])
    {
        $this->method = $method;
        $this->options = $options;
        $this->retry = isset($options['retry'])?$options['retry']:0;
        $this->completed = isset($options['completed'])?$options['completed']:false;
        $this->finished = isset($options['finished'])?$options['finished']:false;
        $this->failed = isset($options['failed'])?$options['failed']:false;
        $this->curl_options = isset($options['curl_options'])?$options['curl_options']:[];
        $this->verify = isset($options['verify'])?$options['verify']:true;

        $this->preserve_proxy = isset($options['preserve_proxy'])?$options['preserve_proxy']:false;

        if (isset($options['cookie'])) {
            $this->cookie = $options['cookie'];
        } elseif (isset($options['cookies'])) {
            $this->cookie = $options['cookies'];
        } else {
            $this->cookie = '';
        }

        $this->proxy = isset($options['proxy'])?$options['proxy']:'';
        $this->headers = isset($options['headers'])?$options['headers']:[];
        $this->callback = isset($options['callback'])?$options['callback']:false;
        $this->params = isset($options['params'])?$options['params']:[];
        $this->concurrency = isset($options['concurrency'])?$options['concurrency']:false;
        $this->custom = isset($options['custom'])?$options['custom']:[];
        $this->multipart = isset($options['multipart'])?$options['multipart']:[];
        $this->json = isset($options['json'])?$options['json']:[];
        $this->url = $url;

        $this->base_url = isset($options['base_url'])?$options['base_url']:'';
    }

    public function getCopy()
    {
        return new Task($this->url, $this->method, $this->options);
    }

    public function getOptions()
    {
        return $this->options;
    }

    public function setBaseUrl($url)
    {
        $this->base_url = $url;
    }

    public function getBaseUrl()
    {
        return $this->base_url;
    }

    public function setEffectiveUrl($url)
    {
        $this->effective_url = $url;
    }

    public function getEffectiveUrl()
    {
        return ($this->effective_url)?$this->effective_url:$this->url;
    }

    public function getResponse()
    {
        return $this->response;
    }

    public function setResponse($response)
    {
        $this->response = $response;
    }

    public function getVerify()
    {
        return $this->verify;
    }

    public function getConcurrency()
    {
        return $this->concurrency;
    }

    public function getCallback()
    {
        return $this->callback;
    }

    public function setCallback($callback)
    {
        $this->callback = $callback;
    }

    public function setHtml($html)
    {
        $this->html = $html;
    }

    public function getHtml()
    {
        return $this->html;
    }

    public function setCustom($name, $value)
    {
        $this->custom[$name] = $value;
    }

    public function getCustom($name, $default='')
    {
        if (isset($this->custom[$name])) {
            return $this->custom[$name];
        } else {
            return $default;
        }
    }

    public function getCustomAll()
    {
        return $this->custom;
    }

    public function getUrl()
    {
        return $this->url;
    }

    public function setFinished($finished)
    {
        $this->finished = $finished;
    }

    public function isPreserveProxy()
    {
        return $this->preserve_proxy;
    }

    public function isFinished()
    {
        return $this->finished;
    }

    public function isCompleted()
    {
        return $this->completed;
    }

    public function getProxy()
    {
        return $this->proxy;
    }

    public function setProxy($proxy)
    {
        $this->proxy = $proxy;
    }

    public function getCookies()
    {
        return $this->cookie;
    }

    public function setCookies($cookie)
    {
        $this->cookie = $cookie;
    }

    public function getHeaders()
    {
        return $this->headers;
    }

    public function setHeaders($headers)
    {
        $this->headers = $headers;
    }

    public function getMethod()
    {
        return $this->method;
    }

    public function getRetry()
    {
        return $this->retry;
    }

    public function incRetry()
    {
        $this->retry = $this->retry + 1;
    }

    public function complete()
    {
        $this->completed = true;
    }

    public function finish()
    {
        $this->finished = true;
    }

    public function fail()
    {
        $this->completed = true;
        $this->failed = true;
    }

    public function getParams()
    {
        return $this->params;
    }

    public function getCurlOptions()
    {
        return $this->curl_options;
    }

    public function getMultipart()
    {
        return $this->multipart;
    }

    public function getJson()
    {
        return $this->json;
    }

    public function getProxyString()
    {
        $proxy = $this->getProxy();
        if (is_object($proxy)) {
            $proxy = $proxy->proxy;
        }

        return $proxy;
    }

    function __destruct()
    {
        $this->destroy();
    }

    public function destroy()
    {
        foreach ($this as $k=>$v) {
            unset($this->{$k});
        }
    }

}