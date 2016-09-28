<?php defined("ALT_PATH") or die("No direct script access.");

class System_User extends Alt_Dbo {

    public function __construct() {
        // call parent constructor
        parent::__construct();

        // define this class specific properties
        $this->pkey             = "userid";
        $this->table_name       = "sys_user";
        $this->table_fields     = array(
            "userid"            => "",
            "username"          => "",
            "password"          => "",
            "name"              => "",
            "email"             => "",
            "address"               => "",
            "phone"             => "",
            "usergroupid"       => "",
            "isenabled"         => "",
        );

        $this->view_name            = "view_sys_user";
        $this->view_fields          = array_merge($this->table_fields, array(
            "isdisplayed"           => "",
            "isallowregistration"   => "",
            "usergroupname"         => "",
            "usergroupdescription"  => "",
            "userlevel"             => "",
            "roleid"                => "",
            "rolename"              => "",
            "rolelevel"             => "",
            "roledescription"       => "",
            "projectid"             => "",
            "projectname"           => "",
        ));
    }

    public function retrieve($data, $returnsql = false){
        Alt_Validation::instance()
            ->rule(Alt_Validation::required($data["userid"]), "User harus dipilih!")
            ->check();

        $res = parent::retrieve($data, $returnsql);
        $res["role"] = array(
            "roleid" => $res["roleid"],
            "description" => $res["roledescription"],
        );

        return $res;
    }

    public function chpasswd($data){
        Alt_Validation::instance()
            ->rule(Alt_Validation::required($data["userid"]), "User harus dipilih!")
            ->rule(Alt_Validation::required($data["password"]), "Password harus diisi!")
            ->rule(Alt_Validation::required($data["password2"]), "Konfirmasi password harus diisi!")
            ->rule(Alt_Validation::equals($data["password"], $data["password2"]), "Konfirmasi password tidak sesuai!")
            ->check();

        $data["password"] = md5($data["password"]);
        return $this->update($data);
    }
}