<?php

require_once __DIR__ . '/HarnessTestCase.php';

class InstallAndUpgradeTest extends HarnessTestCase
{
    public function testInstallSucceedsAndRegistersDisplayHook()
    {
        $module = new statsbestmanufacturers();
        $this->assertTrue($module->install());
        $this->assertSame(
            array(
                array('action' => 'register', 'hook' => 'displayAdminStatsModules'),
            ),
            Module::$hookLog
        );
    }

    public function testInstallAbortsWhenParentInstallFails()
    {
        Module::$nextInstallResult = false;
        $module = new statsbestmanufacturers();
        $this->assertFalse($module->install());
        $this->assertSame(array(), Module::$hookLog);
    }

    public function testInstallFailsWhenHookRegistrationFails()
    {
        Module::$forceHookFailure = true;
        $module = new statsbestmanufacturers();
        $this->assertFalse($module->install());
    }

    public function testUpgradeRewiresLegacyHook()
    {
        require_once dirname(dirname(__DIR__)) . '/upgrade/upgrade-2.0.1.php';
        $module = new statsbestmanufacturers();
        $this->assertTrue(upgrade_module_2_0_1($module));
        $this->assertSame(
            array(
                array('action' => 'unregister', 'hook' => 'AdminStatsModules'),
                array('action' => 'register', 'hook' => 'displayAdminStatsModules'),
            ),
            Module::$hookLog
        );
    }
}
