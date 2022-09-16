<?php
/**
 * This file is part of niqingyang/workerman-bundle.
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the MIT-LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @author    niqingyang<niqy@qq.com>
 * @copyright niqingyang<niqy@qq.com>
 * @link      https://github.com/niqingyang/workerman-bundle
 * @license   http://www.opensource.org/licenses/mit-license.php MIT License
 */

namespace WellKit\WorkermanBundle;

use Phar;
use Workerman\Worker;

class Utils
{
    /**
     * Copy dir.
     *
     * @param string $source
     * @param string $dest
     * @param bool $overwrite
     * @return void
     */
    public static function copyDir(string $source, string $dest, bool $overwrite = false)
    {
        if (\is_dir($source)) {
            if (!is_dir($dest)) {
                \mkdir($dest);
            }
            $files = \scandir($source);
            foreach ($files as $file) {
                if ($file !== "." && $file !== "..") {
                    static::copyDir("$source/$file", "$dest/$file");
                }
            }
        } else if (\file_exists($source) && ($overwrite || !\file_exists($dest))) {
            \copy($source, $dest);
        }
    }

    /**
     * Remove dir.
     *
     * @param string $dir
     * @return bool
     */
    public static function removeDir(string $dir)
    {
        if (\is_link($dir) || \is_file($dir)) {
            return \unlink($dir);
        }
        $files = \array_diff(\scandir($dir), array('.', '..'));
        foreach ($files as $file) {
            (\is_dir("$dir/$file") && !\is_link($dir)) ? static::removeDir("$dir/$file") : \unlink("$dir/$file");
        }
        return \rmdir($dir);
    }

    /**
     * @param Worker $worker
     * @param object $handler
     */
    public static function workerBind(Worker $worker, object|string $handler): void
    {
        $callback_map = [
            'onConnect',
            'onMessage',
            'onClose',
            'onError',
            'onBufferFull',
            'onBufferDrain',
            'onWorkerStop',
            'onWebSocketConnect'
        ];
        foreach ($callback_map as $name) {
            if (\method_exists($handler, $name)) {
                $worker->$name = [$handler, $name];
            }
        }
        if (\method_exists($handler, 'onWorkerStart')) {
            \call_user_func([$handler, 'onWorkerStart'], $worker);
        }
    }

    /**
     * @param string $process_name
     * @param array $config
     * @return void
     */
    public static function workerStart(string $process_name, array $config = []): void
    {
        $worker = new Worker($config['listen'] ?? null, $config['context'] ?? []);

        $property_map = [
            'count',
            'user',
            'group',
            'reloadable',
            'reusePort',
            'transport',
            'protocol',
        ];

        $worker->name = $process_name;

        foreach ($property_map as $property) {
            if (isset($config[$property])) {
                $worker->$property = $config[$property];
            }
        }

        $worker->onWorkerStart = function ($worker) use ($config) {
            foreach ($config['services'] ?? [] as $server) {
                if (!\class_exists($server['handler'])) {
                    echo "process error: class {$server['handler']} not exists\r\n";
                    continue;
                }
                $listen = new Worker($server['listen'] ?? null, $server['context'] ?? []);
                if (isset($server['listen'])) {
                    echo "listen: {$server['listen']}\n";
                }
                static::workerBind($listen, $server['handler']);
                $listen->listen();
            }

            if (isset($config['handler'])) {
                static::workerBind($worker, $config['handler']);
            }
        };
    }

    /**
     * Phar support.
     * Compatible with the 'realpath' function in the phar file.
     *
     * @param string $file_path
     * @return string
     */
    public static function getRealpath(string $file_path): string
    {
        if (str_starts_with($file_path, 'phar://')) {
            return $file_path;
        } else {
            return \realpath($file_path);
        }
    }

    /**
     * @return bool
     */
    public static function isPhar()
    {
        return \class_exists(\Phar::class, false) && Phar::running();
    }

    /**
     * @return int
     */
    public static function cpuCount()
    {
        // Windows does not support the number of processes setting.
        if (\DIRECTORY_SEPARATOR === '\\') {
            return 1;
        }
        $count = 4;
        if (\is_callable('shell_exec')) {
            if (\strtolower(PHP_OS) === 'darwin') {
                $count = (int)\shell_exec('sysctl -n machdep.cpu.core_count');
            } else {
                $count = (int)\shell_exec('nproc');
            }
        }
        return $count > 0 ? $count : 4;
    }

    /**
     * @param string $name
     * @param array $constructor
     * @return mixed
     * @throws Exception
     */
    public static function make(string $name, array $constructor = [])
    {
        if (!\class_exists($name)) {
            throw new \Exception("Class '$name' not found");
        }
        return new $name(... array_values($constructor));
    }
}
