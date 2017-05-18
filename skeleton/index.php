<?php if(!defined("__DIR__")) define("__DIR__", dirname(__FILE__));

define("DS", DIRECTORY_SEPARATOR);
define("ALT_PATH", __DIR__ . DS);

ini_set("xdebug.show_exception_trace", "Off");
error_reporting(E_ALL ^ E_NOTICE ^ E_WARNING);
date_default_timezone_set("Asia/Jakarta");
//setlocale(LC_ALL, 'id_ID.utf8');

// load framework
require __DIR__ . "/../src/Alt.php";
spl_autoload_register(array("Alt", "autoload"));

// composer autoload
if(file_exists(__DIR__ . '/vendor/autoload.php'))
    require __DIR__ . '/vendor/autoload.php';

// start application
Alt::start();
