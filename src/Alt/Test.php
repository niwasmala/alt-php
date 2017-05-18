<?php

// php5.2 support
if(!function_exists("array_union")){
    function array_union($array1, $array2) {
        $args = func_get_args();

        $array1 = is_array($args[0]) ? $args[0] : array();
        $union = $array1;
        if(count($args) > 1) for($i=1; $i<count($args); $i++){
            $array2 = $args[$i];
            foreach ($array2 as $key => $value) {
                if (false === array_key_exists($key, $union)) {
                    $union[$key] = $value;
                }
            }
        }

        return $union;
    }
}

abstract class Alt_Test extends PHPUnit_Framework_TestCase {
    public $url = "";
    public $route = "";
    public $api;

    public function connect($url, $data = array()){
        $this->api = new Alt_Api($this->url, $this->route);
        $this->api->url = $this->url;
        $this->api->route = $this->route;
        return $this->api->connect($url, $data);
    }
}