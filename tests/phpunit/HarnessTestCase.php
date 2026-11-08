<?php

class HarnessTestCase extends PHPUnit_Framework_TestCase
{
    protected function setUp()
    {
        Module::$nextInstallResult = true;
        Module::$forceHookFailure = false;
        Module::$hookLog = array();
        $_GET = array();
        $_POST = array();
        if (isset($_SERVER['REQUEST_URI'])) {
            unset($_SERVER['REQUEST_URI']);
        }
    }

    protected function tearDown()
    {
        Module::$nextInstallResult = true;
        Module::$forceHookFailure = false;
        Module::$hookLog = array();
        $_GET = array();
        $_POST = array();
        if (isset($_SERVER['REQUEST_URI'])) {
            unset($_SERVER['REQUEST_URI']);
        }
    }
}
