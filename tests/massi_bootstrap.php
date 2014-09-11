<?php
$date = date('m/d/Y h:i:s a', time());
print($date . "\n");

@ini_set("display_errors", "On");
@ini_set("display_startup_errors", "On");


if(!defined("TESTS_PATH"))  {
    define('TESTS_PATH', realpath(dirname(__FILE__)));
}

// some general pimcore definition overwrites
define("PIMCORE_ADMIN", true);
define("PIMCORE_DEBUG", true);
define("PIMCORE_DEVMODE", true);
define("PIMCORE_WEBSITE_VAR",  TESTS_PATH . "/tmp/var");

@mkdir(TESTS_PATH . "/output", 0777, true);

// include pimcore bootstrap
include_once(realpath(dirname(__FILE__)) . "/../pimcore/cli/startup.php");

// empty temporary var directory
recursiveDelete(PIMCORE_WEBSITE_VAR);
mkdir(PIMCORE_WEBSITE_VAR, 0777, true);

$includePathBak = get_include_path();
$includePaths = array(get_include_path());
$includePaths[] = TESTS_PATH . "/TestSuite";
array_unshift($includePaths, "/lib");
set_include_path(implode(PATH_SEPARATOR, $includePaths));

// add the tests, which still reside in the original development unit, not in pimcore_phpunit to the include path
$includePaths = array(
    get_include_path()
);

$includePaths[] = TESTS_PATH;
$includePaths[] = TESTS_PATH . "/TestSuite";
$includePaths[] = TESTS_PATH . "/lib";

set_include_path(implode(PATH_SEPARATOR, $includePaths));

// register the tests namespace
$autoloader = Zend_Loader_Autoloader::getInstance();
$autoloader->registerNamespace('Test');
$autoloader->registerNamespace('Rest');
$autoloader->registerNamespace('TestSuite');

print("include path: " . get_include_path() . "\n");
print("bootstrap    done\n");

