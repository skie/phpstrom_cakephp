<?php

function fileExistsInIncludePath($file) {
    if (file_exists($file)) {
        return realpath($file);
    }

    $paths = explode(PATH_SEPARATOR, get_include_path());

    foreach ($paths as $path) {
        $fullpath = $path . DIRECTORY_SEPARATOR . $file;

        if (file_exists($fullpath)) {
            return realpath($fullpath);
        }
    }

    return FALSE;
}

if (!fileExistsInIncludePath("/PHPUnit")) {
    print("PHPUnit is missing from include paths.\nPlease adjust ''include_path'' variable in your ''php.ini'' configuration file.");
    exit;
}

$global_phpunit_request_start = true;

function getErrorMessageFromException(Exception $e) {
    $message = "";
    if (strlen(get_class($e)) != 0) {
        $message = $message . get_class($e);
    }
    if (strlen($message) != 0 && strlen($e->getMessage()) != 0) {
        $message = $message . " : ";
    }
    $message = $message . $e->getMessage();

    return $message;
}

function getLocation($_path) {
    return $_path;
}


function getErrorMessage($errno) {
    switch ($errno) {
        case E_ERROR:
            return "E_ERROR";
        case E_WARNING:
            return "E_WARNING";
        case E_PARSE:
            return "E_PARSE";
        case E_NOTICE:
            return "E_NOTICE";
        case E_CORE_ERROR:
            return "E_CORE_ERROR";
        case E_CORE_WARNING:
            return "E_CORE_WARNING";
        case E_COMPILE_ERROR:
            return "E_COMPILE_ERROR";
        case E_COMPILE_WARNING:
            return "E_COMPILE_WARNING";
        case E_USER_ERROR:
            return "E_USER_ERROR";
        case E_USER_WARNING:
            return "E_USER_WARNING";
        case E_USER_NOTICE:
            return "E_USER_NOTICE";
        case E_ALL:
            return "E_ALL";
        case E_STRICT:
            return "E_STRICT";
        case E_RECOVERABLE_ERROR:
            return "E_RECOVERABLE_ERROR";
        case E_DEPRECATED:
            return "E_DEPRECATED";
        /*
              case E_USER_DEPRECATED:
                  return "E_USER_DEPRECATED";
        */
        default:
            return "Unknown error type";
    }
}

function composeOneLineTraceMessage($index, $fileName, $line, $functionName, $args) {
    $result = "#" . $index . " ";
    if (!empty($fileName)) {
        $result .= $fileName;
        if ($line) {
            $result .= "(" . $line . ")";
        }
        if (!empty($functionName)) {
            $result .= ": " . $functionName . "()";
/*            for ($i = 0; $i < count($args); $i++) {
                $type = gettype($args[$i]);
                if ($type == "object") {
                    $type = get_class($args[$i]);
                }
                $result .= $type;
                if ($i + 1 < count($args)) {
                    $result .= ", ";
                }
            }
            $result .= ")";*/
        }
    }
    $result .= "\n";
    return $result;
}

// #0 /home/conf/limb_rep/limb/debug.php(8): zoo()
function getTraceMessage(array $trace, $filename) {
    $result = "";
    $index = 0;

    foreach ($trace as $traceline) {
        if (empty($traceline["file"])) {
            continue;
        }
        $result .= composeOneLineTraceMessage($index,
                                              isset($traceline["file"]) ? $traceline["file"] : null,
                                              isset($traceline["line"]) ? $traceline["line"] : null,
                                              isset($traceline["function"]) ? $traceline["function"] : null,
                                              isset($traceline["args"]) ? $traceline["args"] : null);
        $index++;
    }
    return $result;
}

function myExceptionHandler(Exception $e) {
    $output = getErrorMessageFromException($e) . "\n" . getTraceMessage($e->getTrace(), getLocation($e->getFile()));
    flush();
}

$doConvertErrorToExceptions = false;
// $user_error_reporting_level = 0;

function myErrorHandler_DirectReport($errno, $errstr, $errfile, $errline) {
    global $doConvertErrorToExceptions;

    // $errstr = $errstr . " FH";

    if (/*!$doConvertErrorToExceptions && */!($errno & error_reporting())) {
//         print("Returned TRUE from error_handler\n");
        return FALSE;
    }
//    print("error_handler prints error\n");

    if (version_compare(PHP_VERSION, '5.2.5', '>=')) {
        $trace = debug_backtrace(FALSE);
    }
    else {
        $trace = debug_backtrace();
    }

    array_shift($trace);

    foreach ($trace as $frame) {
        if ($frame['function'] == '__toString') {
            return FALSE;
        }
    }

    $exceptionWasThrown = false;
    if ($doConvertErrorToExceptions) {
        if ($errno == E_NOTICE || $errno == E_STRICT) {
            if (PHPUnit_Framework_Error_Notice::$enabled === TRUE) {
                $exceptionWasThrown = true;
                $exception = 'PHPUnit_Framework_Error_Notice';
            }
        } else {
            if ($errno == E_WARNING) {
                if (PHPUnit_Framework_Error_Warning::$enabled === TRUE) {
                    $exceptionWasThrown = true;
                    $exception = 'PHPUnit_Framework_Error_Warning';
                }
            } else {
                $exceptionWasThrown = true;
                $exception = 'PHPUnit_Framework_Error';
            }
        }

        if ($exceptionWasThrown) {
            $errstr = getErrorMessage($errno) . ": " .
                      $errstr . "\n" . composeOneLineTraceMessage(0, getLocation($errfile), $errline, null, null);

            throw new $exception($errstr, $errno, $errfile, $errline, $trace);
        }
    }
    if (!$exceptionWasThrown) {
        $errstr = getErrorMessage($errno) . ": " .
                  $errstr;

        print($errstr . "\n" . getTraceMessage($trace, getLocation($errfile)));
        return TRUE;
    }
}

function myFatalErrorHandler() {
    global $global_phpunit_request_start;
    if (isset($global_phpunit_request_start) && $global_phpunit_request_start) {
        print("PHPUnit is not configured properly");
        flush();
        die();
    }
    $last_error = error_get_last();
    if (!empty($last_error) && ($last_error['type'] & error_reporting())) {
        print(getErrorMessage($last_error['type']) . ": " .
              $last_error['message'] . "\n" .
              composeOneLineTraceMessage(0, $last_error['file'], $last_error['line'], null, null));
    }
}

$isPhpUnit3_5 = fileExistsInIncludePath('PHPUnit/Autoload.php');

// set_exception_handler('myExceptionHandler');

// E_WARNING is responsible for expected PHPUnit_Framework_Error
// $user_error_reporting_level = error_reporting(E_ALL & (~(E_ERROR | E_PARSE | E_CORE_ERROR | E_CORE_WARNING | E_COMPILE_ERROR | E_COMPILE_WARNING | E_STRICT)));
// error suppression is added because of xdebug, which prints errors in html format, when run remotely
$my_initial_error_handler = set_error_handler('myErrorHandler_DirectReport', E_ALL | E_STRICT/*, $my_direct_error_reporting_level*/);

register_shutdown_function('myFatalErrorHandler');

$global_phpunit_request_start = false;

$config_file = "/*config_xml*/";
$working_directory = "/*working_directory*/";
$system_delimiter = "/*system_delimiter*/";
// mappings have system independent slashes
$serverToClientMappings = array(/*mappings*/);

if ($isPhpUnit3_5 == false) {
    require_once 'PHPUnit/Framework.php';
} else {
    require_once 'PHPUnit/Autoload.php';
}
require_once 'PHPUnit/TextUI/TestRunner.php';

require_once 'PHPUnit/Framework/TestResult.php';
require_once 'PHPUnit/Framework/TestListener.php';
require_once 'PHPUnit/Framework/Warning.php';

require_once 'PHPUnit/Runner/StandardTestSuiteLoader.php';
require_once 'PHPUnit/Runner/IncludePathTestCollector.php';

require_once 'PHPUnit/Util/Filter.php';

function escape($text) {
    $text = str_replace("|", "||", $text);
    $text = str_replace("'", "|'", $text);
    $text = str_replace("\n", "|n", $text);
    $text = str_replace("\r", "|r", $text);
    $text = str_replace("]", "|]", $text);

    return $text;
}

function traceCommand($command, $param1Name, $param1Value,
                      $param2Name = null, $param2Value = null,
                      $param3Name = null, $param3Value = null) {
    $line = "\n##teamcity[" . $command . " " . $param1Name . "='" . escape($param1Value) . "'";
    if ($param2Name != null) {
        $line = $line . " " . $param2Name . "='" . escape($param2Value) . "'";
    }
    if ($param3Name != null) {
        $line = $line . " " . $param3Name . "='" . escape($param3Value) . "'";
    }

    $line = $line . "]\n";
    return $line;
}

class SimpleTestListener extends PHPUnit_Util_Printer implements PHPUnit_Framework_TestListener {
    var $LOCATION_PROTOCOL;

    var $myfilename;

    var $depth;

    public function __construct($filename = "") {
        $this->LOCATION_PROTOCOL = "php_qn://";
        $this->myfilename = getLocation(realpath((string)$filename));
        $this->depth = 0;
    }

    public function flush() {
    }

    public function incrementalFlush() {
    }

    public function write($buffer) {
    }

    public function getAutoFlush() {
        return $this->autoFlush;
    }

    public function setAutoFlush($autoFlush) {
    }

    public function addError(PHPUnit_Framework_Test $test, Exception $e, $time) {
        print(traceCommand("testFailed", "name", $test->getName(), "message", getErrorMessageFromException($e),
                           "details", getTraceMessage($e->getTrace(), $this->myfilename)));
        flush();
    }

    public function addFailure(PHPUnit_Framework_Test $test, PHPUnit_Framework_AssertionFailedError $e, $time) {
        if ($test instanceof PHPUnit_Framework_Warning) {
            return;
        }
        print(traceCommand("testFailed", "name", $test->getName(), "message", getErrorMessageFromException($e),
                           "details", /*getPrettyTrace($e->getTraceAsString())*/
                           getTraceMessage($e->getTrace(), $this->myfilename)));
        flush();
    }

    public function addIncompleteTest(PHPUnit_Framework_Test $test, Exception $e, $time) {
        if (!is_null($e)) {
            print(traceCommand("testIgnored", "name", $test->getName(), "message", getErrorMessageFromException($e),
                               "details", getTraceMessage($e->getTrace(), $this->myfilename)/*,
                "locationHint", "php_qn://" . $this->myfilename . "::" . $test->toString()*/));
        }
        else {
            print(traceCommand("testIgnored", "name", $test->getName(), "message", ""));
        }
        // print(traceCommand("testIgnored", "name", $test->getName()));
        flush();
    }

    public function addSkippedTest(PHPUnit_Framework_Test $test, Exception $e, $time) {
        print(traceCommand("testIgnored", "name", $test->getName(), "message", getErrorMessageFromException($e)));
        flush();
    }

    public function startTest(PHPUnit_Framework_Test $test) {
        if ($test instanceof PHPUnit_Framework_Warning) {
            $pieces = explode('"', $test->getMessage());
            if (count($pieces) == 3) {
                $testName = $pieces[1];
                $shownTestName = "Warning (" . $testName . ")";

                print(traceCommand("testStarted", "name", $shownTestName, "locationHint",
                                   "php_qn://" . $this->myfilename . "::::" . $testName));
                print(traceCommand("testIgnored", "name", $shownTestName, "message", $test->getMessage()));
                print(traceCommand("testFinished", "name", $shownTestName, "duration", 0));
                return;
            }
        }
        print(traceCommand("testStarted", "name", $test->getName(), "locationHint",
                           "php_qn://" . $this->myfilename . "::" . $test->toString()));
        flush();
    }

    public function endTest(PHPUnit_Framework_Test $test, $time) {
        print(traceCommand("testFinished", "name", $test->getName(), "duration", (int)(round($time, 2) * 1000)));
        flush();
    }

    public function startTestSuite(PHPUnit_Framework_TestSuite $suite) {
        $this->depth++;
        if ($this->depth >= 2) {
            return;
        }
        $location = $this->LOCATION_PROTOCOL . $this->myfilename . "::" . $suite->toString();
        print(traceCommand("testSuiteStarted", "name", $suite->getName() . " (" . basename($this->myfilename) . ")",
                           "locationHint", $location));
        flush();
    }

    public function endTestSuite(PHPUnit_Framework_TestSuite $suite) {
        $this->depth--;
        if ($this->depth >= 1) {
            return;
        }
        print(traceCommand("testSuiteFinished", "name", $suite->getName() . " (" . basename($this->myfilename) . ")"));
        flush();
    }
}

class MyTestRunner {
    private static function runTest(PHPUnit_Framework_TestSuite $test, $filename, $arguments) {
        global $doConvertErrorToExceptions;
        $doConvertErrorToExceptions = true;
        try {
            if (isset($arguments['loader'])) {
                $runner = new PHPUnit_TextUI_TestRunner($arguments['loader']);
            }
            else {
                $runner = new PHPUnit_TextUI_TestRunner();
            }
            $arguments['printer'] = new SimpleTestListener($filename);

			$fixture = self::getFixtureManager($arguments);
			foreach ($test->getIterator() as $_test) {
				if ($_test instanceof CakeTestCase) {
					$fixture->fixturize($_test);
					$_test->fixtureManager = $fixture;
				}
			}
			
            $result = $runner->doRun($test, $arguments);
			$fixture->shutdown();
        } catch (PHPUnit_Framework_Error $e) {
            myExceptionHandler($e);

        } catch (Exception $e) {
            myExceptionHandler($e);
        }
        $doConvertErrorToExceptions = false;
    }
	
/**
 * Get the fixture manager class specified or use the default one.
 *
 * @return instance of a fixture manager.
 */
	public static function getFixtureManager($arguments) {
		if (isset($arguments['fixtureManager'])) {
			App::uses($arguments['fixtureManager'], 'TestSuite');
			if (class_exists($arguments['fixtureManager'])) {
				return new $arguments['fixtureManager'];
			}
			throw new RuntimeException(__d('cake_dev', 'Could not find fixture manager %s.', $arguments['fixtureManager']));
		}
		App::uses('AppFixtureManager', 'TestSuite');
		if (class_exists('AppFixtureManager')) {
			return new AppFixtureManager();
		}
		return new CakeFixtureManager();
	}	
	

    public static function collectTestsFromFile($filename) {
        PHPUnit_Util_Class::collectStart();
        PHPUnit_Util_Fileloader::checkAndLoad($filename, FALSE);
        $newClasses = PHPUnit_Util_Class::collectEnd();
        $baseName = str_replace('.php', '', basename($filename));

        foreach ($newClasses as $className) {
            if (substr($className, 0 - strlen($baseName)) == $baseName) {
                $newClasses = array($className);
                break;
            }
        }
        $tests = array();
        // of PHPUnit_Framework_Test
        foreach ($newClasses as $className) {
            $class = new ReflectionClass($className);

            if (!$class->isAbstract()) {
                if ($class->hasMethod(PHPUnit_Runner_BaseTestRunner::SUITE_METHODNAME)) {
                    $method = $class->getMethod(
                        PHPUnit_Runner_BaseTestRunner::SUITE_METHODNAME
                    );

                    if ($method->isStatic()) {
                        $newTest = $method->invoke(NULL, $className);
                        // of type PHPUnit_Framework_Test
                        $tests[] = $newTest;
                    }
                }

                else {
                    if ($class->implementsInterface('PHPUnit_Framework_Test')) {
                        $tests[] = new PHPUnit_Framework_TestSuite($class);
                    }
                }
            }
        }
        return $tests;
    }

    /*
    --foo --bar=baz
      ["foo"]   => true
      ["bar"]   => "baz"

    test.php -abc
      ["a"]     => true
      ["b"]     => true
      ["c"]     => true

    test.php arg1 arg2 arg3
      [0]       => "arg1"
      [1]       => "arg2"
      [2]       => "arg3"
    */
    private static function parseLocalArguments() {
        $argv = $_SERVER['argv'];
        array_shift($argv);
        $out = array();
        foreach ($argv as $arg){
            if (substr($arg,0,2) == '--'){
                $eqPos = strpos($arg,'=');
                if ($eqPos === false){
                    $key = substr($arg,2);
                    $out[$key] = isset($out[$key]) ? $out[$key] : true;
                } else {
                    $key = substr($arg,2,$eqPos-2);
                    $out[$key] = substr($arg,$eqPos+1);
                }
            } else if (substr($arg,0,1) == '-'){
                if (substr($arg,2,1) == '='){
                    $key = substr($arg,1,1);
                    $out[$key] = substr($arg,3);
                } else {
                    $chars = str_split(substr($arg,1));
                    foreach ($chars as $char){
                        $key = $char;
                        $out[$key] = isset($out[$key]) ? $out[$key] : true;
                    }
                }
            } else {
                $out[] = $arg;
            }
        }
        return $out;
    }

    private static function parseRemoteArguments() {
        $out = array();
        global $config_file;
        // NB! Concatenation here is crucial.
        // Replace (which is faster) is used instead of replaceFirst in PhpUnitRemoteUtil
        if (strcmp($config_file, "") != 0 && strcmp($config_file, "/*config"."_xml*/") != 0) {
            $out["config"] = $config_file;
            $out["mode"] = "x";
        }

        if (isset($_GET["groups"])) {
            $out["groups"] = $_GET["groups"];
        }
        if (isset($_GET["exclude_groups"])) {
            $out["excludeGroups"] = $_GET["exclude_groups"];
        }
        if (isset($_GET["mode"])) {
            $out["mode"] = $_GET["mode"];
        }
        if (isset($_GET["file"])) {
            $out["file"] = $_GET["file"];
            if (isset($_GET["class"])) {
                $out["class"] = $_GET["class"];
                if (isset($_GET["method"])) {
                    $out["method"] = $_GET["method"];
                }
            }
        }
        if ($out["mode"] == "d") {
            $out["file"] = dirname(__FILE__);
        }

        return $out;
    }

    private static function parseArguments() {
        $isLocal = !isset($_SERVER['HTTP_USER_AGENT']);
        return $isLocal ? self::parseLocalArguments() : self::parseRemoteArguments();
    }

    private static function handleLoader($loaderClass, $loaderFile = '')
    {
        if (!class_exists($loaderClass, FALSE)) {
            if ($loaderFile == '') {
                $loaderFile = PHPUnit_Util_Filesystem::classNameToFilename(
                    $loaderClass
                );
            }

            $loaderFile = PHPUnit_Util_Filesystem::fileExistsInIncludePath(
                $loaderFile
            );

            if ($loaderFile !== FALSE) {
                require $loaderFile;
            }
        }

        if (class_exists($loaderClass, FALSE)) {
            $class = new ReflectionClass($loaderClass);

            if ($class->implementsInterface('PHPUnit_Runner_TestSuiteLoader') &&
                $class->isInstantiable()
            ) {
                $loader = $class->newInstance();
            }
        }

        if (!isset($loader)) {
            PHPUnit_TextUI_TestRunner::showError(
                sprintf(
                    'Could not use "%s" as loader.',

                    $loaderClass
                )
            );
        }

        return $loader;
    }

    public static function main() {
        $arguments = array(
            'listGroups' => FALSE,
            'loader' => NULL,
            'useDefaultConfiguration' => TRUE
        );

        $loader = NULL;
        // $startPos = 1;
        $canCountTest = true;
        global $working_directory;
        if (strcmp($working_directory, "") != 0 &&
             // NB! Concatenation here is crucial.
             // Replace (which is faster) is used instead of replaceFirst in PhpUnitRemoteUtil
            strcmp($working_directory, "/*working"."_directory*/") != 0) {
            chdir($working_directory);
        }
        $args = self::parseArguments();
        if (isset($args["config"])) {
            // check if configuration specified
            $canCountTest = false;
            $arguments['configuration'] = $args["config"];
            // $startPos = 3;
            $configuration = PHPUnit_Util_Configuration::getInstance($args["config"]);
            $phpunit = $configuration->getPHPUnitConfiguration();

            $configuration->handlePHPConfiguration();
			
            $phpunitConfiguration = $configuration->getPHPUnitConfiguration();

            if (isset($phpunitConfiguration['bootstrap'])) {
                PHPUnit_Util_Fileloader::load($phpunitConfiguration['bootstrap']);
            }
			
            if (isset($phpunit['testSuiteLoaderClass'])) {
                if (isset($phpunit['testSuiteLoaderFile'])) {
                    $file = $phpunit['testSuiteLoaderFile'];
                }
                else {
                    $file = '';
                }

                $loader = self::handleLoader($phpunit['testSuiteLoaderClass'], $file);
                $arguments['loader'] = $loader;
            }


            $browsers = $configuration->getSeleniumBrowserConfiguration();

            if (!empty($browsers)) {
                require_once 'PHPUnit/Extensions/SeleniumTestCase.php';
                PHPUnit_Extensions_SeleniumTestCase::$browsers = $browsers;
            }
        }

        if (isset($args['groups'])) {
            $arguments['groups'] = explode(',', $args['groups']);
        }
        if (isset($args['excludeGroups'])) {
            $arguments['excludeGroups'] = $args['excludeGroups'];
        }

        if ($args["mode"] == "c") {
            $suiteClassName = $args["class"];
            $suiteClassFile = $args["file"];
            try {
                // $testClass = ();

                if ($loader == NULL) {
                    $loader = new PHPUnit_Runner_StandardTestSuiteLoader;
                }
                $testClass = $loader->load($suiteClassName, $suiteClassFile, FALSE);
            }
            catch (Exception $e) {
                myExceptionHandler($e);
                return;
            }
            try {
                // if class is a suite
                $suiteMethod = $testClass->getMethod('suite');

                if ($suiteMethod->isAbstract() ||
                    !$suiteMethod->isPublic() ||
                    !$suiteMethod->isStatic()) {
                    return;
                }

                try {
                    // ?? suite does not have testName argument
                    $test = $suiteMethod->invoke(NULL, $testClass->getName());
                    $test->setName($suiteClassName);
                    if ($canCountTest) {
                        print(traceCommand("testCount", "count", (string)sizeof($test)));
                    }
                    self::runTest($test, $suiteClassFile, $arguments);
                }
                catch (ReflectionException $e) {
                    myExceptionHandler($e);
                    return;
                }
            }
            catch (ReflectionException $e) {
                $test = new PHPUnit_Framework_TestSuite($testClass);
                if ($canCountTest) {
                    print(traceCommand("testCount", "count", (string)sizeof($test)));
                }
                self::runTest($test, $suiteClassFile, $arguments);
            }
        }
        else {
            if ($args["mode"] == "d") {
                // if run directory
                // in remote case we put this script in the test directory
                $suiteDirName = $args["file"];
                if (is_dir($suiteDirName) && !is_file($suiteDirName . '.php')) {
                    $testCollector = new PHPUnit_Runner_IncludePathTestCollector(array($suiteDirName));

                    // $test = new PHPUnit_Framework_TestSuite($suiteDirName);
                    $filenames = $testCollector->collectTests();
                    $number = 0;
                    $alltests = array();
                    foreach ($filenames as $filename) {
                        $tests = self::collectTestsFromFile($filename);

                        foreach ($tests as $currenttest) {
                            $number += sizeof($currenttest);
                            $alltests[] = $currenttest;
                            $alltests[] = $filename;
                        }
                    }
                    if ($canCountTest) {
                        print(traceCommand("testCount", "count", (string)$number));
                    }
                    for ($i = 0; $i < count($alltests); $i += 2) {
                        self::runTest($alltests[$i], $alltests[$i + 1], $arguments);
                    }
                    return;
                }
            }
            else {
                if ($args["mode"] == 'f') {
                    // if run all in file
                    $filename = $args["file"];
                    $tests = self::collectTestsFromFile($filename);

                    $test = new PHPUnit_Framework_TestSuite();
                    $number = 0;
                    foreach ($tests as $currenttest) {
                        if ($tests) {
                            $test->addTest($currenttest);
                            $number += sizeof($currenttest);
                        }
                    }
                    if ($canCountTest) {
                        print(traceCommand("testCount", "count", $number));
                    }

                    foreach ($tests as $currentTest) {
                        self::runTest($currentTest, $filename, $arguments);
                    }
                    return;
                }
                else {
                    if ($args["mode"] == 'm') {
                        $suiteMethodName = $args["method"];
                        $suiteClassName = $args["class"];
                        $suiteClassFile = $args["file"];
                        try {
                            $testClass = (new PHPUnit_Runner_StandardTestSuiteLoader);
                            $testClass = $testClass->load($suiteClassName, $suiteClassFile, FALSE);
                        }
                        catch (Exception $e) {
                            myExceptionHandler($e);
                            return;
                        }
                        try {
                            // if class is a suite
                            $suiteMethod = $testClass->getMethod($suiteMethodName);

                            if ($suiteMethodName == 'suite') {
                                if (($suiteMethod->isAbstract() ||
                                     !$suiteMethod->isPublic() ||
                                     !$suiteMethod->isStatic())) {
                                    return;
                                }

                                try {
                                    $test = $suiteMethod->invoke(NULL, $testClass->getName());

                                    $test->setName($suiteClassName);
                                    if ($canCountTest) {
                                        print(traceCommand("testCount", "count", (string)sizeof($test)));
                                    }
                                    self::runTest($test, $suiteClassFile, $arguments);
                                }
                                catch (ReflectionException $e) {
                                    myExceptionHandler($e);
                                    return;
                                }
                            }
                            else {
                                $test = PHPUnit_Framework_TestSuite::createTest($testClass, $suiteMethodName);
                                $testSuite = new PHPUnit_Framework_TestSuite();
                                $testSuite->addTest($test);
                                $testSuite->setName($suiteClassName);
                                if ($canCountTest) {
                                    print(traceCommand("testCount", "count", (string)sizeof($test)));
                                }
                                self::runTest($testSuite, $suiteClassFile, $arguments);
                            }
                        }
                        catch (ReflectionException $e) {
                            myExceptionHandler($e);
                            return;
                        }
                    }
                    else {
                        if ($args["mode"] == 'x') {
                            $testSuite = $configuration->getTestSuiteConfiguration();
                            self::runTest($testSuite, "", $arguments);
                        }
                    }
                }
            }
        }
    }
}

if ($isPhpUnit3_5 == false) {
    PHPUnit_Util_Filter::addFileToFilter(__FILE__, 'PHPUNIT');
}
else {
    PHP_CodeCoverage_Filter::getInstance()->addFileToBlacklist(__FILE__, 'PHPUNIT');
}

// try {

MyTestRunner::main();
/*}
catch (Exception $e) {
    myExceptionHandler($e);
}*/

// restore_exception_handler();
?>
