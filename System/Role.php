<?php defined("ALT_PATH") or die("No direct script access.");

class System_Role extends Alt_Dbo {

    public function __construct() {
        // call parent constructor
        parent::__construct();

        // define this class specific properties
        $this->pkey         = "roleid";
        $this->table_name   = "sys_role";
        $this->table_fields = array(
            "roleid"        => "",
            "name"          => "",
            "description"   => "",
            "level"         => "",
        );
    }
}