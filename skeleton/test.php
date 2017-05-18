<?php if(!defined("__DIR__")) define("__DIR__", dirname(__FILE__));

define("DS", DIRECTORY_SEPARATOR);
define("ALT_PATH", __DIR__ . DS);

ini_set("xdebug.show_exception_trace", "Off");
error_reporting(E_ALL ^ E_NOTICE);
date_default_timezone_set("Asia/Jakarta");

// load framework
require ALT_PATH . "engine" . DS . "Alt.php";
spl_autoload_register(array("Webservice", "autoload"));

// composer autoload
if(file_exists(__DIR__ . '/vendor/autoload.php'))
    require __DIR__ . '/vendor/autoload.php';

// sentry
if(class_exists("Raven_Client")) {
    $client = new Raven_Client('https://b7309c529d46402b8657226e56fe3548:151de7da1d2d4b509027361dbdc765aa@sentry.io/159558');
    $error_handler = new Raven_ErrorHandler($client);
    $error_handler->registerExceptionHandler();
    $error_handler->registerErrorHandler();
    $error_handler->registerShutdownFunction();
}

// start application
Webservice::test();
