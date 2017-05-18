<?php defined("ALT_PATH") or die("No direct script access.");

class System_Config extends Alt_Dbo {

    public function __construct() {
        // call parent constructor
        parent::__construct();

        // define this class specific properties
        $this->pkey         = "field";
        $this->autoinc      = false;
        $this->table_name   = "sys_config";
        $this->table_fields = array(
            "field"             => "",
            "content"           => "",
            "caption"           => "",
            "modifiedtime"      => "",
            "modifieduser"      => "",
        );
    }

    public function retrieve($data, $returnsql = false){
        Alt_Validation::instance()
            ->rule(Alt_Validation::required($data["field"]), "Field belum diisi!")
            ->check();

        return parent::retrieve($data, $returnsql);
    }
}