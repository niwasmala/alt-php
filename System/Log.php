<?php defined("ALT_PATH") OR exit("No direct script access allowed");

class System_Log extends Alt_Dbo {

    public function __construct(){
        // call parent constructor
        parent::__construct();

        // define this class specific properties
        $this->pkey         = "logid";
        $this->table_name   = "sys_log";
        $this->table_fields = array(
            "logid"         => "",
            "ipaddress"     => "",
            "token"         => "",
            "useragent"     => "",
            "url"           => "",
            "request"       => "",
            "response"      => "",
            "datetime"      => "",
            "entrytime"     => "",
            "entryuser"     => "",
        );
    }

    public static function record($response = ""){
        $dbo = new System_Log();
        $request = $_REQUEST;
        unset($request["token"]);

        return $dbo->insert(array(
            "ipaddress" => $_SERVER["REMOTE_ADDR"],
            "token" => System_Auth::get_token(),
            "useragent" => $_SERVER["HTTP_USER_AGENT"],
            "url" => $_SERVER["REQUEST_URI"],
            "request" => json_encode($request),
            "response" => $response,
            "datetime" => date("YmdHis"),
        ));
    }
}