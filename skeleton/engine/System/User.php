<?php defined("ALT_PATH") or die("No direct script access.");

class System_User extends Alt_Dbo {

    public function __construct() {
        // call parent constructor
        parent::__construct();

        // define this class specific properties
        $this->pkey                 = "userid";
        $this->table_name           = "sys_user";
        $this->table_fields         = array(
            "userid"                => "",
            "username"              => "",
            "password"              => "",
            "name"                  => "",
            "email"                 => "",
            "phone"                 => "",
            "address"               => "",
            "description"           => "",
            "photo"                 => "",
            "usergroupid"           => "",
            "isenabled"             => "",
            "vouchercode"           => "",
        );

        $this->view_name            = "view_sys_user";
        $this->view_fields          = array_merge($this->table_fields, array(
            "isdisplayed"           => "",
            "isallowregistration"   => "",
            "usergroupname"         => "",
            "usergroupdescription"  => "",
            "userlevel"             => "",
        ));
    }

    public function insert($data, $returnsql = false) {
        Alt_Validation::instance()
            ->rule(Alt_Validation::required($data["username"]), "Username harus diisi!")
            ->rule(Alt_Validation::required($data["email"]), "Email harus diisi!")
            ->rule(Alt_Validation::required($data["password"]), "Password harus diisi!")
            ->check();

        // check if username exist
        $count = $this->count(array(
            "username" => "= " . $this->quote($data["username"]),
        ));
        if($count > 0)
            throw new Alt_Exception("Username sudah dipergunakan, silahkan ganti dengan username lain!");

        // check if phone exist
        $count = $this->count(array(
            "phone" => "= " . $this->quote($data["phone"]),
        ));
        if($count > 0)
            throw new Alt_Exception("Nomor telepon telah terdaftar!");

        // check if email exist
        $count = $this->count(array(
            "email" => "= " . $this->quote($data["email"]),
        ));
        if($count > 0)
            throw new Alt_Exception("Email telah terdaftar!");

        $userid = parent::insert($data, $returnsql);
        $user = $this->retrieve(array(
            "userid" => $userid,
        ));

        return $userid;
    }

    public function chpasswd($data){
        $userdata = Alt_Auth::get_userdata();
        $data["userid"] = $data["userid"] ? $data["userid"] : $userdata["userid"];

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