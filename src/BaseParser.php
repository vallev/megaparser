<?php

namespace parserbot\megaparser;

use GuzzleHttp\Client;
use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Stream;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class BaseParser
{
    protected $headers;
    protected $proxy;
    protected $cookies;
    protected $client;
    protected $config;
    protected $timeout;
    protected $concurrency;
    protected $use_proxies;
    protected $num_retries;
    private $ip;
    protected $counter;
    protected $retry_delay;
    public $proxer;
    public $interrupter;
    public $messenger;

    public function __construct(array $options = [])
    {

        $this->options = $options;

        $this->timeout = 10;
        if (array_key_exists('timeout', $options)) {
            $this->timeout = $options['timeout'];
        }

        $this->concurrency = 5;
        if (array_key_exists('concurrency', $options)) {
            $this->concurrency = $options['concurrency'];
        }

        $this->use_proxies = true;
        if (array_key_exists('use_proxies', $options)) {
            $this->use_proxies = $options['use_proxies'];
        }

        $this->num_retries = 3;
        if (array_key_exists('num_retries', $options)) {
            $this->num_retries = $options['num_retries'];
        }

        $this->retry_delay = 3;
        if ($this->use_proxies) {
            $this->retry_delay = 0;
        }

        if (array_key_exists('retry_delay', $options)) {
            $this->retry_delay = $options['retry_delay'];
        }

        $this->counter = 0;

        $this->proxy_class = array_key_exists('proxy_class', $options)?$options['proxy_class']:'\\parserbot\\megaparser\\Proxy';
        $class = $this->proxy_class;
        $this->proxy = new $class();
        $proxy = '';
        if ($this->proxy) {
            $proxy = $this->proxy->proxy;
        }

        if (array_key_exists('proxer', $options)) {
            $this->proxer = new $options['proxer']($options);
        } else {
            $this->proxer = new Proxer($options);
        }

        if (array_key_exists('messenger', $options)) {
            $this->messenger = new $options['messenger']($options);
        } else {
            $this->messenger = new Messenger($options);
        }

        if (array_key_exists('interrupter', $options)) {
            $this->interrupter = new $options['interrupter']($options);
        } else {
            $this->interrupter = new Interrupter($options);
        }

        $this->resetClient();
    }

    public function getOption($key, $default = '')
    {
        return isset($this->options[$key])?$this->options[$key]:$default;
    }

    /*
     * Reset client, to close all connections - need to prevent keep-alive
     *
     */
    public function resetClient()
    {
        if ($this->proxy) {
            $proxy = $this->proxy->proxy;
        } else {
            $proxy = '';
        }

        $this->cookies = new CookieJar();
        $this->headers = [
            'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.9; rv:37.0) Gecko/20100101 Firefox/37.0',
            'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language' => 'ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
        ];

        $stack = HandlerStack::create();
        $stack->push($this->forceUtf8());

        $this->client = new Client(['headers'=>$this->headers, 'proxy'=>$proxy,
            'cookies'=>$this->cookies, 'handler'=>$stack]);
        $config = $this->client->getConfig();
        $this->client = new Client($config);
    }

    public function disableForceUtf8()
    {
        $config = $this->client->getConfig();
        if (isset($config['handler'])) {
            unset($config['handler']);
        }

        $stack = HandlerStack::create();
        $config['handler'] = $stack;

        $this->client = new Client($config);
    }

    public function enableForceUtf8()
    {

        $config = $this->client->getConfig();
        if (!isset($config['handler'])) {
            $stack = HandlerStack::create();
            $stack->push($this->forceUtf8());
            $config['handler'] = $stack;
        }
        $this->client = new Client($config);
    }

    public function setConfig($config)
    {
        foreach ($config as $k=>$v) {
            $this->config[$k] = $v;
        }
    }

    public function getConfig($name, $default = '')
    {
        return array_key_exists($name, $this->config)?$this->config[$name]:$default;
    }

    public function useIp($ip)
    {
        $this->ip = $ip;
    }

    public function setProxy()
    {
        return $this->proxer->setProxy();
    }

    public function proxyRenew($filename)
    {
        return $this->proxer->proxyRenew($filename);
    }

    public function changeProxy($block = false)
    {
        return $this->proxer->changeProxy($block = false);
    }

    public function blockAndChangeProxy()
    {
        return $this->proxer->blockAndChangeProxy();
    }

    public function getRandomProxy()
    {
        return $this->proxer->changeProxy();
    }

    public static function prepareUrl($url)
    {
        $purl = self::parse_utf8_url($url);
        if (is_array($purl) && $purl) {
            if (array_key_exists('query', $purl)) {
                parse_str($purl['query'], $qstring);
                $query = http_build_query($qstring);
                $url = $purl['scheme'].'://'.$purl['host'].$purl['path'].'?'.$query;
            } else {
                $url = $purl['scheme'].'://'.$purl['host'].$purl['path'];
            }
        } else {
            $url = '';
        }

        return $url;
    }

    public static function parse_utf8_url($url)
    {
        static $keys = array('scheme'=>0,'user'=>0,'pass'=>0,'host'=>0,'port'=>0,'path'=>0,'query'=>0,'fragment'=>0);
        if (is_string($url) && preg_match(
                '~^((?P<scheme>[^:/?#]+):(//))?((\\3|//)?(?:(?P<user>[^:]+):(?P<pass>[^@]+)@)?(?P<host>[^/?:#]*))(:(?P<port>\\d+))?' .
                '(?P<path>[^?#]*)(\\?(?P<query>[^#]*))?(#(?P<fragment>.*))?~u', $url, $matches))
        {
            foreach ($matches as $key => $value)
                if (!$value || !array_key_exists($key, $keys)) {
                    unset($matches[$key]);
                }
            return $matches;
        }
        return false;
    }

    protected function absoluteUrl($url, $base_url=false) {

        $url = explode('#', $url)[0];

        if (substr($url,0,5) !== 'http:' && substr($url,0,6) !== 'https:' ) {

            if (!$base_url) {
                $base_url = $this->config['base_url'];
            }

            $base_url = rtrim($base_url, "/");

            /*if($base_url[strlen($base_url)-1] === '/') {
                unset($base_url[strlen($base_url)-1]);
            }*/

            if (isset($url[0]) && $url[0] !== '/') {
                $url = '/'.$url;
            }

            $url = $base_url.$url;
        }

        return $url;
    }

    public function findBetween($start, $end, $content){
        $r = explode($start, $content);
        if (array_key_exists(1,$r)){
            $r = explode($end, $r[1]);
            return $r[0];
        }
        return '';
    }

    public function clientGet($url, $body = true, $num_retries = 3, $sleep = 4)
    {
        $url = $this->absoluteUrl($url);
        $retry = 0;
        while (true) {
            try {

                $curl = [];
                if ($this->ip) {
                    $curl = [
                        CURLOPT_INTERFACE => $this->ip
                    ];
                }

                $result = $this->client->get($url, [
                    'cookies' => $this->cookies,
                    'proxy' => $this->proxy->proxy,
                    'headers' => $this->headers,
                    'timeout' => $this->timeout,
                    'curl' => $curl,
                ]);

                if ($body) {
                    return $result->getBody();
                } else {
                    return $result;
                }
            } catch (\Exception $e) {
                $retry++;
                $this->setProxy();
            }

            if ($retry > $num_retries) {
                throw new \Exception("Количество попыток ($num_retries) превышено. Url: $url");

            } else {
                $sleep *= 1.5;
                sleep($sleep);
            }
        }

    }

    public function clientPost($url, $params, $body = true, $num_retries = 3, $sleep = 4)
    {
        $url = $this->absoluteUrl($url);
        $retry = 0;
        while (true) {
            try {
                $curl = [];
                if ($this->ip) {
                    $curl = [
                        CURLOPT_INTERFACE => $this->ip
                    ];
                }

                $result = $this->client->post($url, [
                    'cookies' => $this->cookies,
                    'proxy' => $this->proxy->proxy,
                    'headers' => $this->headers,
                    'form_params' => $params,
                    'timeout' => $this->timeout,
                    'curl' => $curl,
                ]);
                if ($body) {
                    return $result->getBody();
                } else {
                    return $result;
                }
            } catch (\Exception $e) {
                $retry++;
                $this->setProxy();
                //error_log($e->getMessage() . "\n");

            }

            if ($retry > $num_retries) {
                throw new \Exception("Количество попыток ($num_retries) превышено. Url: $url");
            } else {
                $sleep *= 1.5;
                sleep($sleep);
            }

        }
    }

    protected function delayProxy($proxy)
    {
        return $this->proxer->delayProxy($proxy);
    }

    public function getProxy()
    {
        return $this->proxer->getProxy();
    }

    public function blockProxy($proxy)
    {
        return $this->proxer->blockProxy($proxy);
    }

    public function resetTasks($tasks)
    {
        $return_tasks = [];
        foreach ($tasks as $task) {
            $return_task = $task;
            if (!$task->isCompleted()) {
                $return_task->setFinished(false);
            }
            $return_tasks[] = $return_task;
        }

        return $return_tasks;
    }

    public static function splitTasks($tasks)
    {
        $chunks = [];
        foreach ($tasks as $task) {
            if (isset($task->concurrency)) {
                $chunks[(int)$task->getConcurrency()][] = $task;
            }
        }
        return $chunks;
    }

    public function downloadHtmls($tasks, $concurrency = 5, $num_retries = 3, $delay = 1)
    {
        $return_tasks = [];
        $concurrency_groups = self::splitTasks($tasks);

        // Нужно отсортировать задачи по конкурентности
        // Затем разделить на части - по конкурентности
        // И уже отдельные части резать на части = concurrency
        foreach ($concurrency_groups as $k=>$grouped_tasks) {

            $real_concurrency = $k;
            if ($k === 0) {
                $real_concurrency = $concurrency;
            }

            $chunks = array_chunk($grouped_tasks, $real_concurrency);
            foreach ($chunks as $chunk) {
                $return_tasks = array_merge($this->downloadHtmlsRaw($chunk, $real_concurrency, $num_retries), $return_tasks);
                $this->counter += count($return_tasks);
                if ($this->counter > $concurrency) {
                    sleep($delay);
                    $this->counter = 0;
                }

            }
        }


        return $return_tasks;
    }

    public function downloadHtmlsRaw($tasks, $concurrency = 5, $num_retries = 3, $delay = 0)
    {

        if (!$concurrency) {
            $concurrency = 1;
        }

        $retry_tasks = $this->resetTasks($tasks);
        //Загружаем все начальные страницы для данных pcodes
        //Загружаем параллельно с помощью Guzzle Pool
        $return_tasks = [];
        $start = microtime(true);
        // Пока есть задачи для повторения продолжаем
        // Здесь мы должны разбить задачи на пачки по $concurency, между пачками можно сделать паузу - delay
        while(count($retry_tasks)) {
            $promises = [];
            foreach ($retry_tasks as $key => $task) {
                if ($this->checkStopFlag()) {
                    $this->addMessage('Завершаем парсинг принудительно');
                    throw new \Exception('Парсинг завершён принудительно');
                }
                if (!$task->isFinished() && !$task->isCompleted()) {
                    $method = $task->getMethod();
                    $proxy = $task->getProxy();
                    if (is_object($proxy)) {
                        $proxy = $proxy->proxy;
                    }

                    if ($method === Task::GET) {

                        //$client = new Client();

                        $promise = $this->client->getAsync($this->absoluteUrl($task->getUrl(), $task->getBaseUrl()), [
                            'proxy' => $proxy,
                            'headers' => $task->getHeaders(),
                            'config' => ['key' => $key, 'start' => microtime(true)],
                            'timeout' => $this->timeout,
                            'cookies' => $task->getCookies(),
                            'curl' => $task->getCurlOptions(),
                            'verify' => $task->getVerify(),
                            'json' => $task->getJson()?:null,
                            'allow_redirects' => [
                                'track_redirects' => true
                            ]
                        ]);

                        $promises[$key] = $promise;

                        $this->addMessage("GET " . $task->getUrl(), 'level7');


                    } else {

                        $promises[$key] = $this->client->postAsync($this->absoluteUrl($task->getUrl(), $task->getBaseUrl()), [
                            'proxy' => $proxy,
                            'headers' => $task->getHeaders(),
                            'config' => ['key' => $key, 'start' => microtime(true)],
                            'timeout' => $this->timeout,
                            'form_params' => $task->getParams()?:null,
                            'multipart' => $task->getMultipart()?:null,
                            'json' => $task->getJson()?:null,
                            'cookies' => $task->getCookies(),
                            'curl' => $task->getCurlOptions(),
                            'verify' => $task->getVerify(),
                            'allow_redirects' => [
                                'track_redirects' => true
                            ]
                        ]);

                        $this->addMessage("POST " . $task->getUrl(), 'level7');
                    }

                } else {
                    $return_task = $task;
                    $return_tasks[] = $return_task;
                }

                unset($retry_tasks[$key]);
            }

            $results = [];

            $this->addMessage('Starting total ' . count($promises) . ' downloads.', 'level7');

            foreach ($promises as $k=>$promise) {
                $promise->then(
                    function (ResponseInterface $res) use (&$retry_tasks, &$results, $k, &$tasks, &$return_tasks){

                        // Чтобы не запускать снова
                        $tasks[$k]->finish();
                        $tasks[$k]->setHtml($res->getBody());

                        $url = explode(',',$res->getHeaderLine('X-Guzzle-Redirect-History'))[0];
                        $tasks[$k]->setEffectiveUrl($url);

                        $return_task = $tasks[$k];
                        $return_tasks[] = $return_task;
                        $this->addMessage($return_task->getUrl() . ' ! - ' . $return_task->getCallback());
                        //$this->addMessage(memory_get_usage(), 'level7');
                    },
                    function (\Exception $e) use (&$retry_tasks, &$results, $k, &$tasks, $num_retries, &$return_tasks){

                        $this->addMessage('Ошибка: ' . $e->getMessage() . ' ' . $tasks[$k]->getRetry() . ' ' . $tasks[$k]->getProxyString());
                        // TODO retry с новым прокси
                        $retry_task = $tasks[$k];
                        if ($retry_task->getRetry() < $num_retries) {
                            $tasks[$k]->incRetry();

                            try {
                                sleep($this->retry_delay);
                                if ($this->use_proxies && !$tasks[$k]->isPreserveProxy()) {
                                    $proxy = $tasks[$k]->getProxy();
                                    if (is_object($proxy)) {
                                        $retry_task->setProxy($this->getProxy());
                                    } else {
                                        $retry_task->setProxy($this->getProxy());
                                    }

                                }
                            } catch (\Exception $e) {
                                $this->addMessage($e->getMessage());
                            }


                            $this->addMessage('Retrying ' . $tasks[$k]->getUrl() . ' ' . $tasks[$k]->getProxyString());

                            $retry_tasks[$k] = $retry_task;
                        } else {
                            $retry_task->fail();
                            $this->addMessage('Пропускаем: количество попыток закончилось для url:' . $retry_task->getUrl());
                            $return_tasks[] = $retry_task;
                        }
                    }
                );
            }

            $promise = \GuzzleHttp\Promise\each_limit($promises, $concurrency, function(\GuzzleHttp\Psr7\Response $value, $idx){
                $this->addMessage('ok ' . $idx . ' ' . print_r($value->getStatusCode(),1), 'level7');
            }, function($value, $idx) {
                $this->addMessage('fail ' . $idx. ' ' . print_r($value->getMessage(),1), 'level7');
            });
            try {
                $promise->wait();
            } catch (\Exception $e) {
                $this->addMessage($e->getMessage());
            }

            sleep($delay);

        }

        $this->addMessage('time: ' . (microtime(true)-$start), 'debug');


        return $return_tasks;  // Возвращаем и неудачные попытки также
        //return ['success'=>$return_tasks, 'fail' => $fail_tasks];
        //Возвращаем массив html страниц
    }

    protected function recognize($filename, $apikey, $is_verbose = true, $domain='rucaptcha.com', $rtimeout = 2, $mtimeout = 120, $is_phrase = 0, $is_regsense = 0, $is_numeric = 0, $min_len = 0, $max_len = 0, $language = 0) {

        if (!file_exists($filename)) {
            if ($is_verbose) {
                echo "file $filename not found\n";
            }
            return false;
        }

        $file = new \CurlFile($filename);

        $postdata = array(
            'method'    => 'post',
            'key'       => $apikey,
            'file'      => $file,
            'phrase'    => $is_phrase,
            'regsense'  => $is_regsense,
            'numeric'   => $is_numeric,
            'min_len'   => $min_len,
            'max_len'   => $max_len,
            'language'  => $language
        );

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "http://$domain/in.php");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $postdata);
        $result = curl_exec($ch);

        if (curl_errno($ch))  {
            if ($is_verbose) {
                $this->addMessage($domain.': ' . 'CURL returned error: '.curl_error($ch));
            }
            return false;
        }
        curl_close($ch);

        if (strpos($result, 'ERROR')!==false) {
            if ($is_verbose) {
                $this->addMessage($domain.': ' . "server returned error: $result");
            }

            return false;
        } else {
            $ex = explode('|', $result);
            $captcha_id = isset($ex[1])?$ex[1]:"";
            if ($is_verbose) {
                $this->addMessage($domain.': ' . $result);
                $this->addMessage($domain.': ' . "captcha sent, got captcha ID $captcha_id");
            }
            $waittime = 0;
            if ($is_verbose) {
                $this->addMessage($domain.': ' . "waiting for $rtimeout seconds");
            }
            sleep($rtimeout);
            while(true) {

                try {
                    $result = file_get_contents("http://$domain/res.php?key=".$apikey.'&action=get&id='.$captcha_id);
                } catch (\Exception $e) {
                    $result = 'ERROR: ' . $e->getMessage();
                }

                if (strpos($result, 'ERROR')!==false) {
                    if ($is_verbose) {
                        $this->addMessage($domain.': ' . "server returned error: $result");
                    }
                    return false;
                }
                if ($result === 'CAPCHA_NOT_READY') {
                    if ($is_verbose) {
                        $this->addMessage($domain.': ' . "captcha is not ready yet");
                    }
                    $waittime += $rtimeout;
                    if ($waittime>$mtimeout)  {
                        if ($is_verbose) {
                            $this->addMessage($domain.': ' . "timelimit ($mtimeout) hit");
                        }
                        break;
                    }
                    if ($is_verbose) {
                        $this->addMessage($domain.': ' . "waiting for $rtimeout seconds");
                    }
                    sleep($rtimeout);
                } else {
                    $ex = explode('|', $result);
                    if (trim($ex[0]) === 'OK') {
                        return trim($ex[1]);
                    }
                }
            }
        }

        return false;
    }

    private function forceUtf8 ()
    {
        return function (callable $handler)  {
            return function (RequestInterface $request,
                array $options
            ) use ($handler) {
                $promise = $handler($request, $options);
                return $promise->then(
                    function (ResponseInterface $response) {

                        $stream = $response->getBody();
                        if ($stream == null) {
                            return $response;
                        }


                        $headers = $response->getHeaders();
                        $do = false;
                        foreach ($response->getHeader('Content-type') as $header) {
                            if (strpos($header, 'text/html')!== false) {
                                $do = true;
                            }
                        }

                        if (!$do) {
                            return $response;
                        }



                        $charset = '';

                        foreach ($headers['Content-Type'] as $values) {

                            $vals = explode('; ', $values);
                            foreach ($vals as $val) {
                                if (preg_match('/charset=(.*)/is', $val, $m)) {
                                    //print_r($m);
                                    $charset = $m[1];
                                }
                            }
                        }



                        foreach ($headers as $name=>$values) {
                            $headers[$name] = implode("; ", $values);
                        }

                        $content = $stream->__toString();

                        if (preg_match('#<META(.*?)charset=\s*[\'\"]{0,1}([^\'\"\s/>]*)[\'\"]{0,1}(.*?)>#is', $content, $m)) {
                            if (in_array($charset, mb_list_encodings())) {
                                $aliases = mb_encoding_aliases($charset);
                                if (!in_array($m[2], $aliases)) {
                                    if (in_array($m[2], mb_list_encodings())) {
                                        $content = iconv($m[2],'UTF-8', $content);
                                    }
                                }
                            }
                        }

                        $content = preg_replace('#<meta(.*?)>#is', '', $content);

                        $converter = new EncodingConverter('utf-8');
                        $result = $converter->convert($headers, $content);
                        if ($result != null) {
                            $body = new Stream(fopen('php://temp', 'r+'));
                            $response = $response->withBody($body);

                            $content = self::repairUtf8($result->getTargetContent());

                            $body->write($content);
                            foreach ($result->getTargetHeaders() as $k=>$v){
                                $response = $response->withHeader($k,$v);
                            }

                            return $response;
                        } else {
                            $body = new Stream(fopen('php://temp', 'r+'));
                            $response = $response->withBody($body);
                            $content = self::repairUtf8($content);
                            $body->write($content);
                            return $response;
                        }
                    }
                );
            };
        };
    }

    private static function repairUtf8($content) {
        $regex = <<<'END'
/
  (
    (?: [\x00-\x7F]                 # single-byte sequences   0xxxxxxx
    |   [\xC0-\xDF][\x80-\xBF]      # double-byte sequences   110xxxxx 10xxxxxx
    |   [\xE0-\xEF][\x80-\xBF]{2}   # triple-byte sequences   1110xxxx 10xxxxxx * 2
    |   [\xF0-\xF7][\x80-\xBF]{3}   # quadruple-byte sequence 11110xxx 10xxxxxx * 3
    ){1,100}                        # ...one or more times
  )
| .                                 # anything else
/x
END;

        return preg_replace($regex, '$1', $content);
    }

    public function addMessage($message, $type = 'info') {
        return $this->messenger->addMessage($message, $type = 'info');
    }

    public function checkStopFlag() {
        return $this->interrupter->checkStopFlag();
    }
}