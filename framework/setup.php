<?php
if (version_compare(PHP_VERSION, '5.3.0') < 0)
	die('Das Plugin benötigt PHP version 5.3.0 oder höher.');

define('COREPATH', realpath(pathinfo(__FILE__, PATHINFO_DIRNAME)).'/');
define('LIBPATH', COREPATH.'libraries/');

require_once(COREPATH.'config/config.php');

// Load singleton pattern
include_once LIBPATH.'Singleton.php';

// Set autoload function
spl_autoload_register(function($class) {
    include LIBPATH.$class.'.php';
});

// Set timezone
date_default_timezone_set(Config::get('timezone'));

// Set locale
setlocale(LC_ALL, Config::get('locale'));

// Set the character encoding
mb_internal_encoding(Config::get('charset'));

// Set input
Input::set();

// Start session
Session::set();
