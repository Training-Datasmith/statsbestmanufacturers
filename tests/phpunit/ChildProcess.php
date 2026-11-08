<?php

class ChildProcess
{
    const DIAGNOSTIC_PATTERN = '/(?:^|\n)(?:PHP )?(Notice|Warning|Deprecated|Strict Standards|Parse error|Fatal error):/';

    public static function runPhpScript($scriptBody, array $env = array())
    {
        $dir = sys_get_temp_dir() . '/statsbest-child-' . bin2hex(substr(sha1($scriptBody . microtime(true)), 0, 8));
        if (!mkdir($dir, 0700, true) && !is_dir($dir)) {
            throw new RuntimeException('Cannot create child process temp dir.');
        }

        $scriptPath = $dir . '/child.php';
        file_put_contents($scriptPath, $scriptBody);

        $phpBin = getenv('PHP_BIN');
        if (!$phpBin) {
            $phpBin = PHP_BINARY;
        }

        $descriptorSpec = array(
            0 => array('pipe', 'r'),
            1 => array('pipe', 'w'),
            2 => array('pipe', 'w'),
        );

        $fullEnv = array_merge($_ENV, $env, array(
            'PHP_BIN' => $phpBin,
        ));

        $cmd = escapeshellarg($phpBin)
            . ' -d error_reporting=-1 -d display_errors=1 -d display_startup_errors=1'
            . ' -d html_errors=0 -d log_errors=0 '
            . escapeshellarg($scriptPath);

        $proc = proc_open($cmd, $descriptorSpec, $pipes, null, $fullEnv);
        if (!is_resource($proc)) {
            self::removeDir($dir);
            throw new RuntimeException('Failed to start child PHP process.');
        }

        fclose($pipes[0]);
        $stdout = stream_get_contents($pipes[1]);
        fclose($pipes[1]);
        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);
        $exitCode = proc_close($proc);

        self::removeDir($dir);

        if (preg_match(self::DIAGNOSTIC_PATTERN, $stdout) || preg_match(self::DIAGNOSTIC_PATTERN, $stderr)) {
            throw new RuntimeException(
                "Child PHP process emitted a diagnostic.\nSTDOUT:\n" . $stdout . "\nSTDERR:\n" . $stderr
            );
        }

        return array(
            'exitCode' => $exitCode,
            'stdout' => $stdout,
            'stderr' => $stderr,
        );
    }

    private static function removeDir($dir)
    {
        if (!is_dir($dir)) {
            return;
        }
        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }
            $path = $dir . '/' . $item;
            if (is_dir($path)) {
                self::removeDir($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }
}
