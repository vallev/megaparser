<?php

namespace parserbot\parser\tests;

use GuzzleHttp\Cookie\CookieJar;
use GuzzleHttp\Cookie\FileCookieJar;
use parserbot\megaparser\MegaParser;
use parserbot\megaparser\Task;

class Test extends MegaParser
{
    public $fields;
    public $hrefs;
    public $site;
    public $industries;
    public $prof_areas;
    public $areas;

    public function __construct($options = [])
    {

        if (!isset($options['proxer'])) {
            $options['proxer'] = 'parserbot\\parser\\tests\\Proxer';
        }

        if (!isset($options['proxy_class'])) {
            $options['proxy_class'] = 'parserbot\\parser\\tests\\Proxy';
        }

        if (!isset($options['messenger'])) {
            $options['messenger'] = 'parserbot\\parser\\tests\\Messenger';
        }

        parent::__construct($options);
        $this->fields = [];
        $this->config['base_url'] = 'https://maps.yandex.ru';
        if (isset($options['concurrency'])) {
            $this->concurrency = $options['concurrency'];
        } else {
            $this->concurrency = 5;
        }

    }

    public function taskGenerator()
    {

        $city = "New York";
        $state = "";
        $country = "USA";

        $address = urlencode($city . " " . $state . " " . $country);

        yield new Task("http://maps.google.com/maps/api/geocode/json?address=$address&sensor=false&language=en", 'GET', ['callback'=>'Geo', 'custom'=>['skip'=>true]]);
    }

    public function processGeo(Task $task)
    {

        $task->complete();

        $json = json_decode($task->getHtml());
        $sw = $json->results[0]->geometry->bounds->southwest;
        $ne = $json->results[0]->geometry->bounds->northeast;
        $loc = $json->results[0]->geometry->location;
        //print_r($json->results[0]->geometry);die;

        $lt = $loc->lat;
        $ln = $loc->lng;
        //$zoom = '1';
        //$z1 = '7649.5552958143835';
        //$z1 = '1912.25';
        $z1 = '1541.6495913634844'; //zoom 18
        $dln = 0.00736;
        $dlt = 0.003118;
        $q = urlencode('cafe');

        $limit = 250;
        $skip = 0;

        //print_r($ne);die;
        for ($ln = $sw->lng; $ln < $ne->lng; $ln+=$dln) {
            for ($lt = $sw->lat; $lt < $ne->lat; $lt+=$dlt) {
                yield new Task("https://www.google.ru/search?tbm=map&fp=1&authuser=0&hl=en&pb=!4m12!1m3!1d$z1!2d$ln!3d$lt!2m3!1f0!2f0!3f0!3m2!1i1389!2i739!4f13.1!7i$limit!8i$skip!10b1!37m1!1e81&q=$q",
                    'GET', ['callback' => 'Map']);
                //break;
            }
        }
        //return $tasks;
    }

    public function processMap(Task $task)
    {
        $task->complete();
        $tasks = [];

        //die;
        $body = $task->getHtml();
        $body = substr($body,5);
        $json = json_decode($body);
        $result = $json[0][1];
        //print_r($json[0][1]);
        $total = count($result)-1;

        echo 'Total: ' . $total . "\n";
        unset($task);
        for ($i = 1; $i<count($result);$i++) {
            $item = [];
            //$item['street'] = $result[$i][14][2][0];
            //$item['city'] = $result[$i][14][2][1];
            $item['url'] = $result[$i][14][10];
            $item['name'] = $result[$i][14][11];
            //$item['type2'] = $result[$i][14][13];
            $item['address'] = $result[$i][14][39];
            $item['types'] = $result[$i][14][76];
            $item['phones'] = $result[$i][14][3];
            $item['web'] = $result[$i][14][7];
            $item['position'] = $result[$i][14][9];
            $item['address2'] = $result[$i][14][82];
            $item['worktime'] = $result[$i][14][34][1];
            //print_r($result[$i][14][76]);
            //print_r($item);
            //print_r($item['address']);
            //echo "\n";
            //echo isset($item['address2'][3])?$item['address2'][3]:print_r($item['address2'],1);
            //echo " ";
            //echo "\n";
            //echo $item['name'] . " ";
            //$this->export->exportItem($item);
        }

        return $tasks;
    }

}