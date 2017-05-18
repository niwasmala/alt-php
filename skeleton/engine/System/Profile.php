<?php defined("ALT_PATH") or die("No direct script access.");

class System_Profile extends Alt_Dbo {

    public function __construct() {
        // call parent constructor
        parent::__construct();

        // define this class specific properties
        $this->pkey                 = "profileid";
        $this->table_name           = "sys_profile";
        $this->table_fields         = array(
            "profileid"             => "",
            "userid"                => "",
            "birth_date"            => "",
            "annual_income"         => "",
            "ismarried"             => "",
            "children"              => "",
            "age_pension"           => "",
            "risk_tolerance"        => "",
            "emergency_fund"        => "",
        );
    }
}