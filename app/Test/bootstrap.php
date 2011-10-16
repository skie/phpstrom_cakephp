<?php

set_time_limit(0);
ini_set('display_errors', 1);
/**
 * Use the DS to separate the directories in other defines
 */
	if (!defined('DS')) {
		define('DS', DIRECTORY_SEPARATOR);
	}
/**
 * These defines should only be edited if you have cake installed in
 * a directory layout other than the way it is distributed.
 * When using custom settings be sure to use the DS and do not add a trailing DS.
 */

/**
 * The full path to the directory which holds "app", WITHOUT a trailing DS.
 *
 */
	if (!defined('ROOT')) {
		define('ROOT', dirname(dirname(dirname(__FILE__))));
	}
/**
 * The actual directory name for the "app".
 *
 */
	if (!defined('APP_DIR')) {
		define('APP_DIR', basename(dirname(dirname(__FILE__))));
	}

/**
 * The absolute path to the "Cake" directory, WITHOUT a trailing DS.
 *
 * For ease of development CakePHP uses PHP's include_path.  If you
 * need to cannot modify your include_path, you can set this path.
 *
 * Leaving this constant undefined will result in it being defined in Cake/bootstrap.php
 */
	//define('CAKE_CORE_INCLUDE_PATH', ROOT . DS . 'lib');

/**
 * Editing below this line should not be necessary.
 * Change at your own risk.
 *
 */
if (!defined('WEBROOT_DIR')) {
	define('WEBROOT_DIR', 'webroot');
}
if (!defined('WWW_ROOT')) {
	define('WWW_ROOT', ROOT . DS . APP_DIR . DS . 'webroot' . DS);
}

if (!defined('CAKE_CORE_INCLUDE_PATH')) {
	if (function_exists('ini_set')) {
		ini_set('include_path', ROOT . DS . 'lib' . PATH_SEPARATOR . ini_get('include_path'));
	}
	if (!include('Cake' . DS . 'bootstrap.php')) {
		$failed = true;
	}
} else {
	if (!include(CAKE_CORE_INCLUDE_PATH . DS . 'Cake' . DS . 'bootstrap.php')) {
		$failed = true;
	}
}
if (!empty($failed)) {
	trigger_error("CakePHP core could not be found.  Check the value of CAKE_CORE_INCLUDE_PATH in APP/webroot/index.php.  It should point to the directory containing your " . DS . "cake core directory and your " . DS . "vendors root directory.", E_USER_ERROR);
}

if (Configure::read('debug') < 1) {
	die(__d('cake', 'Debug setting does not allow access to this url.'));
}

require_once CAKE . 'TestSuite' . DS . 'CakeTestSuiteDispatcher.php';
#require_once CAKE . 'bootstrap.php';

App::uses('CakeTestCase', 'TestSuite');

App::uses('CakeRequest', 'Network');
App::uses('CakeResponse', 'Network');
App::uses('ClassRegistry', 'Utility');
App::uses('CakeTestRunner', 'TestSuite');
App::uses('CakeTestLoader', 'TestSuite');
App::uses('CakeTestSuite', 'TestSuite');
App::uses('CakeTestCase', 'TestSuite');
App::uses('ControllerTestCase', 'TestSuite');
App::uses('CakeTestModel', 'TestSuite/Fixture');
App::uses('TagFixture', 'Test/Fixture');
 
require_once CAKE . 'TestSuite' . DS . 'CakeTestLoader.php';
restore_error_handler();

