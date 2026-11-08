<?php

require_once __DIR__ . '/HarnessTestCase.php';
require_once __DIR__ . '/ChildProcess.php';

class DirectoryGuardTest extends HarnessTestCase
{
    /**
     * @dataProvider indexGuardProvider
     */
    public function testIndexGuardExitsCleanly($relativePath)
    {
        $root = dirname(dirname(__DIR__));
        $target = $root . '/' . $relativePath;
        $script = '<?php' . "\n"
            . '$target = ' . var_export($target, true) . ';' . "\n"
            . 'require $target;' . "\n"
            . "echo 'SENTINEL';" . "\n";

        $result = ChildProcess::runPhpScript($script);
        $this->assertSame(0, $result['exitCode']);
        $this->assertSame('', $result['stdout']);
        $this->assertSame('', $result['stderr']);
    }

    public function indexGuardProvider()
    {
        return array(
            array('index.php'),
            array('tests/index.php'),
            array('tests/phpstan/index.php'),
            array('translations/index.php'),
            array('upgrade/index.php'),
        );
    }

    public function testModuleFileExitsWhenVersionConstantMissing()
    {
        $moduleFile = dirname(dirname(__DIR__)) . '/statsbestmanufacturers.php';
        $script = '<?php' . "\n"
            . 'abstract class Module {}' . "\n"
            . 'abstract class ModuleGrid extends Module {}' . "\n"
            . 'require ' . var_export($moduleFile, true) . ';' . "\n"
            . "echo 'SENTINEL';" . "\n";

        $result = ChildProcess::runPhpScript($script);
        $this->assertSame(0, $result['exitCode']);
        $this->assertSame('', $result['stdout']);
        $this->assertSame('', $result['stderr']);
    }
}
