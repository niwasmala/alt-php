<?php defined("ALT_PATH") or die("No direct script access.");

class System_Userrole extends Alt_Dbo {

    public function __construct() {
        // call parent constructor
        parent::__construct();

        // define this class specific properties
        $this->pkey                 = "";
        $this->autoinc              = false;
        $this->table_name           = "sys_userrole";
        $this->table_fields         = array(
            "roleid"                => "",
            "userid"                => "",
            "projectid"             => "",
            "entrytime"             => "",
            "entryuser"             => "",
            "modifiedtime"          => "",
            "modifieduser"          => "",
            "deletedtime"           => "",
            "deleteduser"           => "",
            "isdeleted"             => "",
        );

        $this->view_name            = "view_sys_userrole";
        $this->view_fields          = array_merge($this->table_fields, array(
            "username"              => "",
            "rolelevel"             => "",
            "roledescription"       => "",
            "projectname"           => "",
        ));
    }

    public function insert($data, $returnsql = false) {
        Alt_Validation::instance()
            ->rule(Alt_Validation::required($data["userid"]), "User harus dipilih!")
            ->rule(Alt_Validation::required($data["roleid"]), "Peran harus dipilih!")
            ->rule(Alt_Validation::required($data["projectid"]), "Proyek harus dipilih!")
            ->check();

        return parent::insert($data, $returnsql);
    }

    public function delete($data, $returnsql = false) {
        Alt_Validation::instance()
            ->rule(Alt_Validation::required($data["roleid"]), "Peran harus dipilih!")
            ->rule(Alt_Validation::required($data["projectid"]), "Proyek harus dipilih!")
            ->check();

        $data["where"] = array(
            "roleid = " . $this->quote($data["roleid"]),
            "projectid = " . $this->quote($data["projectid"]),
        );
        if($data["userid"])
            $data["where"][] = "userid = " . $this->quote($data["userid"]);

        return parent::delete(array(
            "where" => implode(" and ", $data["where"])
        ), $returnsql);
    }
}