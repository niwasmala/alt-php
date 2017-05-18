<?php defined("ALT_PATH") OR exit("No direct script access allowed");

class System_Application extends Alt_Dbo {

    public function __construct(){
        // call parent constructor
        parent::__construct();

        // define this class specific properties
        $this->pkey         = "applicationid";
        $this->table_name   = "sys_application";
        $this->table_fields = array(
            "applicationid" => "",
            "code"          => "",
            "name"          => "",
            "url"           => "",
            "secret"        => "",
            "iv"            => "",
            "isenabled"     => "",
            "entrytime"     => "",
            "entryuser"     => "",
            "modifiedtime"  => "",
            "modifieduser"  => "",
            "deletedtime"   => "",
            "deleteduser"   => "",
            "isdeleted"     => "",
        );
    }
}