<?php defined("ALT_PATH") or die("No direct script access.");

abstract class Alt_Api {

    public $url = "";
    public $route = "";
    public $data = array();
    public $header = array();
    public $security = null;
    public $environment = Alt::ENV_PRODUCTION;

    public function __construct($url = "", $route = ""){
        $this->url = $url != "" ? $url : $this->url;
        $this->route = $route != "" ? $route : $this->route;
    }

    public function count($data = array(), $header = array()){
        return $this->connect("count", $data, $header);
    }

    public function retrieve($data = array(), $header = array()){
        return $this->connect("retrieve", $data, $header);
    }

    public function get($data = array(), $header = array()){
        return $this->connect("list", $data, $header);
    }

    public function keyvalues($data = array(), $header = array()){
        return $this->connect("keyvalues", $data, $header);
    }

    public function table($data = array(), $header = array()){
        return $this->connect("table", $data, $header);
    }

    public function insert($data = array(), $header = array()){
        return $this->connect("insert", $data, $header);
    }

    public function update($data = array(), $header = array()){
        return $this->connect("update", $data, $header);
    }

    public function delete($data = array(), $header = array()){
        return $this->connect("delete", $data, $header);
    }

    public function set_header($header = array()){
        if(Alt_Auth::get_token())
            $header[] = "Authorization: Bearer " . Alt_Auth::get_token();

        return array_union($header, $this->header);
    }

    public function set_body($data = array()){
        $data = array_union($data, $this->data);

        $body = "";
        if(function_exists("http_build_query")){
            $body = http_build_query($data);
        }else{
            foreach($data as $key=>$value) {
                $body .= $key."=".$value."&";
            }
            rtrim($body, "&");
        }

        // encrypt
        if($this->environment != Alt::ENV_DEVELOPMENT && isset($this->security)){
            $body = Alt_Security::encrypt($body, $this->security);
        }

        return $body;
    }

    public function parse_response($response = "", $url = ""){
        // encrypt
        if($this->environment != Alt::ENV_DEVELOPMENT && isset($this->security)){
            $response = Alt_Security::decrypt($response, $this->security);
        }

        $response = json_decode($response, true);

        if(!isset($response["s"]) || $response["s"] != Alt::STATUS_OK)
            throw new Alt_Exception(isset($response["m"]) ? $response["m"] : "Tidak dapat terhubung ke " . $this->url . $url);

        return $response["d"];
    }

    public function connect($url, $data = array(), $header = array()){
        // url-ify the data for the POST
        $body = $this->set_body($data);
        $header = $this->set_header($header);

        // open connection
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_VERBOSE, $this->environment === Alt::ENV_DEVELOPMENT);
        curl_setopt($ch, CURLOPT_HEADER, 1);

        // set http header
        if(count($header) > 0){
            curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        }

        // set the url, number of POST vars, POST data
        curl_setopt($ch,CURLOPT_URL, $this->url . $this->route . $url);
        if(count($data) > 0) {
            curl_setopt($ch, CURLOPT_POST, count($data));
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        // execute post
        $response = curl_exec($ch);
        $header_size = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $header = substr($response, 0, $header_size);
        $body = substr($response, $header_size);

        // close connection
        curl_close($ch);

        return $this->parse_response($body, $url);
    }

    public function quote($string){
        $db = Alt_Db::instance();
        return $db->quote($string);
    }
}