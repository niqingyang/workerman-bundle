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

use Psr\Log\LoggerAwareTrait;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\Runtime\RunnerInterface;
use Workerman\Connection\TcpConnection;
use Workerman\Worker;

class Runner implements RunnerInterface
{
    use LoggerAwareTrait;

    private ?string $environment = null;

    public function __construct(
        private KernelFactory $kernelFactory,
        // the master config
        private WorkerConfig  $config,
        private string        $pidFile,
        private string        $logFile,
        private string        $statusFile,
        private string        $stdoutFile,
    )
    {
        Worker::$onMasterReload = function () {
            if (function_exists('opcache_get_status')) {
                if ($status = \opcache_get_status()) {
                    if (isset($status['scripts']) && $scripts = $status['scripts']) {
                        foreach (array_keys($scripts) as $file) {
                            \opcache_invalidate($file, true);
                        }
                    }
                }
            }
        };
    }

    public function run(): int
    {
        $kernel = $this->kernelFactory->createKernel();

        $this->environment = $kernel->getEnvironment();

        $configLoader = new ConfigLoader($kernel->getCacheDir(), $kernel->isDebug());

        $config = $configLoader->getConfig();

        // 主服务配置
        $server = $config['server'];
        // 自定义进程
        $process = $config['process'];

        $server = $this->getMasterConfig($server);

        Worker::$pidFile = $server['pidFile'];
        Worker::$logFile = $server['logFile'];
        Worker::$stdoutFile = $server['stdoutFile'];
        Worker::$eventLoopClass = $server['eventLoop'];
        TcpConnection::$defaultMaxPackageSize = $server['maxPackageSize'];

        if (property_exists(Worker::class, 'statusFile')) {
            Worker::$statusFile = $server['status_file'] ?? '';
        }
        if (property_exists(Worker::class, 'stopTimeout')) {
            Worker::$stopTimeout = $server['stop_timeout'] ?? 2;
        }

        $worker = new Worker($server['listen'], $server['context']);

        $property_map = [
            'name',
            'count',
            'user',
            'group',
            'reusePort',
            'transport',
            'protocol'
        ];

        foreach ($property_map as $property) {
            if (isset($server[$property])) {
                $worker->$property = $server[$property];
            }
        }

        $worker->onWorkerStart = function (Worker $worker): void {
            $worker->onMessage = [new RequestHandler($this->kernelFactory->createKernel()), 'onMessage'];
        };

        // Windows does not support custom processes.
        if (\DIRECTORY_SEPARATOR === '/') {
            // 获取涉及 workerman 进程的 service id
            foreach ($process ?: [] as $id => $config) {

                $config['handler'] = function () use ($id) {
                    $kernel = $this->kernelFactory->createKernel();
                    $kernel->boot();
                    return $kernel->getContainer()->get($id);
                };

                if (empty($config['name'])) {
                    $config['name'] = $id;
                }

                Utils::workerStart($config['name'], $config);
            }
        }

        Worker::runAll();

        return 0;
    }

    /**
     * the master server config
     *
     * @param array $config
     * @return array
     */
    public function getMasterConfig(array $config): array
    {
        $options = $this->config->toArray();

        foreach ($config as $key => $value) {
            if (!empty($options[$key])) {
                $config[$key] = $options[$key];
            }
        }

        $config['name'] = $config['name'] ?: 'Symfony Workerman Server';
        $config['listen'] = $config['listen'] ?: 'http://0.0.0.0:8000';

        if ($this->environment === 'prod') {
            $config['count'] = $config['count'] ?: Utils::cpuCount() * 2;
        } else {
            $config['count'] = 1;
        }

        $config['stopTimeout'] = $config['stopTimeout'] ?: 2;
        $config['context'] = $config['context'] ?: [];
        $config['pidFile'] = $this->pidFile ?: $config['pidFile'];
        $config['logFile'] = $this->logFile ?: $config['logFile'];
        $config['statusFile'] = $this->logFile ?: $config['statusFile'];
        $config['stdoutFile'] = $this->logFile ?: $config['stdoutFile'];
        $config['eventLoop'] = $config['eventLoop'] ?? '';
        $config['maxPackageSize'] = $config['maxPackageSize'] ?? 10 * 1024 * 1024;

        return $config;
    }
}
