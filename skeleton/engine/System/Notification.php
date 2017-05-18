<?php defined("ALT_PATH") or die("No direct script access.");

class System_Notification extends Alt_Dbo {

    public function __construct() {
        // call parent constructor
        parent::__construct();

        // define this class specific properties
        $this->pkey         = "notificationid";
        $this->table_name   = "sys_notification";
        $this->table_fields = array(
            "notificationid"    => "",
            "userid"            => "",
            "title"             => "",
            "image"             => "",
            "description"       => "",
            "content"           => "",
            "publishedtime"     => "",
            "publisheduser"     => "",
            "ispublished"       => "",
            "entrytime"         => "",
            "entryuser"         => "",
            "modifiedtime"      => "",
            "modifieduser"      => "",
            "deletedtime"       => "",
            "deleteduser"       => "",
            "isdeleted"         => "",
        );
    }

    public function get($data = array(), $returnsql = false) {
        $userdata = Alt_Auth::get_userdata();

        if(Alt_Auth::check(3)){
            $data["userid"] = $data["userid"] ? $data["userid"] : $userdata["userid"];
            $data["userid"] = "in ('0', " . $this->quote($data["userid"]) . ")";
        }

        return parent::get($data, $returnsql);
    }
}