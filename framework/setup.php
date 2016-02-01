<?php
if (defined('FORMULAR_DEBUG') && FORMULAR_DEBUG) {
	error_reporting(-1);
	ini_set('display_errors', 1);
}

if (version_compare(PHP_VERSION, '5.3.0') < 0)
	die('Das Plugin benötigt PHP version 5.3.0 oder höher.');

define('COREPATH', realpath(pathinfo(__FILE__, PATHINFO_DIRNAME)).'/');
define('LIBPATH', COREPATH.'libraries/');

require_once(COREPATH.'config/config.php');

// Load singleton pattern
$filename = LIBPATH.'Singleton.php';
if (file_exists($filename)) {
	include_once $filename;
} elseif (file_exists(strtolower($filename))) {
	include_once strtolower($filename);
}

// Set autoload function
spl_autoload_register(function($class) {
    $filename = LIBPATH.$class.'.php';
	if (file_exists($filename)) {
		include_once $filename;
	} elseif (file_exists(strtolower($filename))) {
		include_once strtolower($filename);
	}	
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
