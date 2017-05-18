<?php defined("ALT_PATH") OR die("No direct access allowed.");

use Alt\Alt;

// heroku database
$url = getenv('JAWSDB_MARIA_URL');
$dbparts = parse_url($url);

$env = getenv('ALT_ENVIRONMENT');

return array (
    "app"                       => array(
        "id"                    => "finwis",
        "name"                  => "Financial Wisdom",
        "environment"           => $env ? $env : Alt::ENV_DEVELOPMENT,
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
                "hostname"      => $dbparts['host'] ? $dbparts['host']                      : 'localhost',
                "database"      => $dbparts['path'] ? ltrim($dbparts['path'],'/')   : 'api.financialwisdom.id',
                "username"      => $dbparts['user'] ? $dbparts['user']                      : 'root',
                "password"      => $dbparts['pass'] ? $dbparts['pass']                      : '',
                "persistent"    => FALSE,
            )
        ),
    )
);