<?php

// Basic stuff we need to know
$baseUrl = "http://killwhore.com";
$baseDir = dirname(__FILE__);

spl_autoload_register("kwautoload");
require_once "config.php";
require_once "eve_tools.php";
require_once "display.php";

$p = processParameters();

function kwautoload($class_name)
{
    $baseDir = dirname(__FILE__);
    $fileName = "$baseDir/classes/$class_name.php";
    if (file_exists($fileName)) {
        require_once $fileName;
        return;
    }
    $fileName = "$baseDir/pages/$class_name.php";
    if (file_exists($fileName)) {
        require_once $fileName;
        return;
    }
}

function processParameters()
{
    global $argv, $argCount;
    $cle = "cli" == php_sapi_name(); // Command Line Execution?
    $p = array();

    if ($cle) {
        foreach ($argv as $arg) {
            if ($argCount >= 0) $p[] = $arg;
        }
        array_shift($p);
    } else {
        $parameters = isset($_GET['p']) ? explode("/", $_GET['p']) : array();
        foreach ($parameters as $param) {
            if (strlen(trim($param)) > 0) $p[] = $param;
        }
    }

    return $p;
}
