<?php
/**
 * This file is modified based from webman
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the MIT-LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @author    walkor<walkor@workerman.net>
 * @author    niqingyang<niqy@qq.com>
 * @copyright niqingyang<niqy@qq.com>
 * @link      https://github.com/niqingyang/workerman-bundle
 * @license   http://www.opensource.org/licenses/mit-license.php MIT License
 */


namespace WellKit\WorkermanBundle\Process;

use Symfony\Component\Filesystem\Path;
use Symfony\Component\Finder\Finder;
use Workerman\Timer;
use Workerman\Worker;

/**
 * Class FileMonitor
 *
 * @package process
 */
class Monitor
{
    protected ?string $basePath = null;

    protected array|string $resource = [];

    protected array|string $patterns = [];

    protected array|string $exclude = [];

    protected float|int|null $memoryLimit = null;

    /**
     * FileMonitor constructor.
     * @param array|string $resource
     * @param array|string $patterns
     * @param array|string $exclude
     * @param float|int|null $memoryLimit
     * @param string|null $basePath
     */
    public function __construct(array|string $resource, array|string $patterns = [], array|string $exclude = [], float|int|null $memoryLimit = null, string $basePath = null)
    {
        $this->exclude = (array)$exclude;
        $this->resource = (array)$resource;
        $this->patterns = $patterns;
        $this->basePath = $basePath;
        $this->memoryLimit = $memoryLimit;
    }

    public function onWorkerStart(): void
    {
        if (!Worker::getAllWorkers()) {
            return;
        }
        $disable_functions = explode(',', ini_get('disable_functions'));
        if (in_array('exec', $disable_functions, true)) {
            echo "\nMonitor file change turned off because exec() has been disabled by disable_functions setting in " . PHP_CONFIG_FILE_PATH . "/php.ini\n";
        } else {
            if (!Worker::$daemonize) {
                Timer::add(1, function () {
                    $this->checkFilesChange();
                });
            }
        }

        $memory_limit = $this->getMemoryLimit($this->memoryLimit);
        if ($memory_limit && DIRECTORY_SEPARATOR === '/') {
            Timer::add(60, [$this, 'checkMemory'], [$memory_limit]);
        }
    }

    /**
     * @return bool
     */
    public function checkFilesChange(): bool
    {
        if ($this->basePath) {
            foreach ($this->resource as $i => $path) {
                $this->resource[$i] = Path::makeAbsolute($path, $this->basePath);
            }
            foreach ($this->exclude as $i => $path) {
                $this->exclude[$i] = Path::makeAbsolute($path, $this->basePath);
            }
        }

        $finder = new Finder();
        $finder->files();

        if ($this->patterns) {
            $finder->name($this->patterns);
        }

        if ($this->resource) {
            $finder->in($this->resource);
        }

        if ($this->exclude) {
            $finder->exclude($this->exclude);
        }

        if ($finder->hasResults()) {

            static $last_mtime, $too_many_files_check;

            if (!$last_mtime) {
                $last_mtime = time();
            }

            clearstatcache();

            $count = 0;

            foreach ($finder as $file) {

                $count += 1;

                // check mtime
                if ($last_mtime < $file->getMTime()) {
                    $var = 0;
                    exec('"' . PHP_BINARY . '" -l ' . $file, $out, $var);
                    if ($var) {
                        $last_mtime = $file->getMTime();
                        continue;
                    }
                    $last_mtime = $file->getMTime();
                    echo $file . " update and reload\n";
                    // send SIGUSR1 signal to master process for reload
                    if (DIRECTORY_SEPARATOR === '/') {
                        posix_kill(posix_getppid(), SIGUSR1);
                    } else {
                        return true;
                    }
                    break;
                }
            }

            if (!$too_many_files_check && $count > 1000) {
                echo "Monitor: There are too many files ($count files) which makes file monitoring very slow\n";
                $too_many_files_check = 1;
            }
        }

        return false;
    }

    /**
     * @param $memory_limit
     * @return void
     */
    public function checkMemory($memory_limit): void
    {
        $ppid = posix_getppid();
        $children_file = "/proc/$ppid/task/$ppid/children";
        if (!is_file($children_file) || !($children = file_get_contents($children_file))) {
            return;
        }
        foreach (explode(' ', $children) as $pid) {
            $pid = (int)$pid;
            $status_file = "/proc/$pid/status";
            if (!is_file($status_file) || !($status = file_get_contents($status_file))) {
                continue;
            }
            $mem = 0;
            if (preg_match('/VmRSS\s*?:\s*?(\d+?)\s*?kB/', $status, $match)) {
                $mem = $match[1];
            }
            $mem = (int)($mem / 1024);
            if ($mem >= $memory_limit) {
                posix_kill($pid, SIGINT);
            }
        }
    }

    /**
     * Get memory limit
     * @return float|int
     */
    protected function getMemoryLimit($memory_limit): float|int
    {
        if ($memory_limit === 0) {
            return 0;
        }
        $use_php_ini = false;
        if (!$memory_limit) {
            $memory_limit = ini_get('memory_limit');
            $use_php_ini = true;
        }

        if ($memory_limit == -1) {
            return 0;
        }
        $unit = $memory_limit[strlen($memory_limit) - 1];
        if ($unit == 'G') {
            $memory_limit = 1024 * (int)$memory_limit;
        } else if ($unit == 'M') {
            $memory_limit = (int)$memory_limit;
        } else if ($unit == 'K') {
            $memory_limit = (int)($memory_limit / 1024);
        } else {
            $memory_limit = (int)($memory_limit / (1024 * 1024));
        }
        if ($memory_limit < 30) {
            $memory_limit = 30;
        }
        if ($use_php_ini) {
            $memory_limit = (int)(0.8 * $memory_limit);
        }
        return $memory_limit;
    }
}
