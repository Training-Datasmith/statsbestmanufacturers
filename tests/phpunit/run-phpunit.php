<?php

error_reporting(-1);
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
ini_set('html_errors', '0');
ini_set('log_errors', '0');

if (PHP_VERSION !== '5.6.40') {
    fwrite(STDERR, 'Expected PHP 5.6.40, got ' . PHP_VERSION . PHP_EOL);
    exit(1);
}

if (!in_array('pdo_mysql', get_loaded_extensions(), true)) {
    fwrite(STDERR, "pdo_mysql extension is required.\n");
    exit(1);
}

$repoRoot = dirname(dirname(__DIR__));
$phpunitPhar = getenv('PHPUNIT_PHAR');
if (!$phpunitPhar || !is_file($phpunitPhar)) {
    fwrite(STDERR, "PHPUNIT_PHAR must point to a readable phpunit phar.\n");
    exit(1);
}

$relativeFiles = array(
    'tests/phpunit/DirectoryGuardTest.php',
    'tests/phpunit/HookDisplayTest.php',
    'tests/phpunit/InstallAndUpgradeTest.php',
    'tests/phpunit/ModuleMetadataTest.php',
    'tests/phpunit/SalesQueryTest.php',
);

$found = glob($repoRoot . '/tests/phpunit/*Test.php');
sort($found);
$normalizedFound = array();
foreach ($found as $path) {
    $normalizedFound[] = str_replace($repoRoot . '/', '', $path);
}
sort($normalizedFound);
$expectedRelative = $relativeFiles;
sort($expectedRelative);
if ($normalizedFound !== $expectedRelative) {
    fwrite(STDERR, "Test file list drift detected.\n");
    exit(1);
}

$expectedTestCount = 30;
$files = array(
    '/src/tests/phpunit/DirectoryGuardTest.php',
    '/src/tests/phpunit/HookDisplayTest.php',
    '/src/tests/phpunit/InstallAndUpgradeTest.php',
    '/src/tests/phpunit/ModuleMetadataTest.php',
    '/src/tests/phpunit/SalesQueryTest.php',
);

$shuffle = getenv('PHPUNIT_SHUFFLE');
if ($shuffle === '1') {
    srand(20261108);
    shuffle($files);
}

$tmpBase = sys_get_temp_dir() . '/statsbest-phpunit-' . getmypid();
if (!is_dir($tmpBase)) {
    mkdir($tmpBase, 0700, true);
}
$configPath = $tmpBase . '/phpunit.xml';
$junitPath = $tmpBase . '/junit.xml';

$xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
$xml .= '<phpunit bootstrap="/src/tests/phpunit/bootstrap.php"' . "\n";
$xml .= '         convertErrorsToExceptions="true"' . "\n";
$xml .= '         convertNoticesToExceptions="true"' . "\n";
$xml .= '         convertWarningsToExceptions="true"' . "\n";
$xml .= '         backupGlobals="false"' . "\n";
$xml .= '         backupStaticAttributes="false">' . "\n";
$xml .= '  <testsuites>' . "\n";
$xml .= '    <testsuite name="module-tests">' . "\n";
foreach ($files as $file) {
    $xml .= '      <file>' . htmlspecialchars($file, ENT_QUOTES, 'UTF-8') . '</file>' . "\n";
}
$xml .= "    </testsuite>\n";
$xml .= "  </testsuites>\n";
$xml .= "</phpunit>\n";
file_put_contents($configPath, $xml);

$phpBin = PHP_BINARY;
$cmd = escapeshellarg($phpBin)
    . ' -d error_reporting=-1 -d display_errors=1 -d display_startup_errors=1'
    . ' -d html_errors=0 -d log_errors=0 '
    . escapeshellarg($phpunitPhar)
    . ' --configuration ' . escapeshellarg($configPath)
    . ' --log-junit ' . escapeshellarg($junitPath);

$descriptorSpec = array(
    0 => array('pipe', 'r'),
    1 => array('pipe', 'w'),
    2 => array('pipe', 'w'),
);

$proc = proc_open($cmd, $descriptorSpec, $pipes);
if (!is_resource($proc)) {
    fwrite(STDERR, "Failed to start PHPUnit.\n");
    exit(1);
}

fclose($pipes[0]);
$stdout = stream_get_contents($pipes[1]);
fclose($pipes[1]);
$stderr = stream_get_contents($pipes[2]);
fclose($pipes[2]);
$exitCode = proc_close($proc);

fwrite(STDOUT, $stdout);
fwrite(STDERR, $stderr);

if ($exitCode !== 0) {
    exit($exitCode);
}

if ($stderr !== '') {
    fwrite(STDERR, "PHPUnit stderr was not empty.\n");
    exit(1);
}

if (!preg_match('/OK \\(' . $expectedTestCount . ' tests, (\\d+) assertions\\)/', $stdout, $matches)) {
    fwrite(STDERR, "PHPUnit summary missing expected OK line.\n");
    exit(1);
}

if (preg_match('/\\b(Skipped|Incomplete|Risky)\\b/', $stdout)) {
    fwrite(STDERR, "PHPUnit reported skipped/incomplete/risky tests.\n");
    exit(1);
}

exit(0);
