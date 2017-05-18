<?php defined("ALT_PATH") OR die("No direct access allowed.");

return array (
    "app"                       => array(
        "id"                    => "finwis",
        "name"                  => "Financial Wisdom",
        "environment"           => getenv("ALT_ENVIRONMENT") ? getenv("ALT_ENVIRONMENT") : Alt::ENV_PRODUCTION,
    ),
    "log"                       => array(
        "level"                 => 5,
    ),
    "session"                   => array(
        "lifetime"              => 43200,
    ),
    "security"                  => array(
        "key"                   => "Lbia93l9gkadn20t",
        "iv"                    => "cva09Kdgal301fdk",
    ),
    "database"                  => array(
        "default"               => array(
            "type"              => "Mysql",
            "charset"           => "utf8",
            "connection"        => array(
                "hostname"      => "localhost",
                "database"      => "alt",
                "username"      => "root",
                "password"      => "",
                "persistent"    => FALSE,
            )
        ),
    )
);