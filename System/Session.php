<?php defined('ALT_PATH') or die('No direct script access.');

class System_Session extends Alt_Dbo {

    public function __construct() {
        // call parent constructor
        parent::__construct();

        // define this class specific properties
        $this->pkey         = "sessionid";
        $this->table_name   = "sys_session";
        $this->table_fields = array(
            "sessionid"     => "",
            "userid"        => "",
            "token"         => "",
            "ipaddress"     => "",
            "useragent"     => "",
            "entrytime"     => "",
        );
    }
}