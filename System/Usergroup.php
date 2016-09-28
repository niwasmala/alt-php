<?php defined("ALT_PATH") or die("No direct script access.");

class System_Usergroup extends Alt_Dbo {

    public function __construct() {
        // call parent constructor
        parent::__construct();

        // define this class specific properties
        $this->pkey                 = "usergroupid";
        $this->table_name           = "sys_usergroup";
        $this->table_fields         = array(
            "usergroupid"           => "",
            "name"                  => "",
            "description"           => "",
            "level"                 => "",
            "isdisplayed"           => "",
            "isallowregistration"   => "",
        );
    }
}