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

use League\MimeTypeDetection\FinfoMimeTypeDetector;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use Psr\Log\LoggerAwareTrait;
use Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Bridge\PsrHttpMessage\HttpFoundationFactoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Runtime\RunnerInterface;
use Workerman\Connection\TcpConnection;
use Workerman\Protocols\Http;
use Workerman\Psr7\ServerRequest;
use Workerman\Worker;
use function Workerman\Psr7\response_to_string;

class Runner implements RunnerInterface
{
    use LoggerAwareTrait;

    public int $maxRequest = 2000;

    private PsrHttpFactory $psrHttpFactory;
    private HttpFoundationFactoryInterface $httpFoundationFactory;
    private Psr17Factory $psr17Factory;
    private FinfoMimeTypeDetector $detector;
    private ?string $environment = null;

    public function __construct(
        private HttpKernelInterface $kernel,
        // the master config
        private WorkerConfig        $config,
        private string              $pidFile,
        private string              $logFile,
        private string              $statusFile,
        private string              $stdoutFile,
    )
    {
        $this->psr17Factory = new Psr17Factory();
        $this->httpFoundationFactory = new HttpFoundationFactory();
        $this->psrHttpFactory = new PsrHttpFactory($this->psr17Factory, $this->psr17Factory, $this->psr17Factory, $this->psr17Factory);

        $this->detector = new FinfoMimeTypeDetector();

        Http::requestClass(ServerRequest::class);

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
        $this->kernel->boot();

        /**
         * @var ContainerInterface $container
         */
        $container = $this->kernel->getContainer();

        $this->environment = $container->getParameter('kernel.environment');
        $config = $this->getMasterConfig($container->getParameter('workerman.server'));

        Worker::$pidFile = $config['pidFile'];
        Worker::$logFile = $config['logFile'];
        Worker::$stdoutFile = $config['stdoutFile'];
        Worker::$eventLoopClass = $config['eventLoop'];
        TcpConnection::$defaultMaxPackageSize = $config['maxPackageSize'];

        if (property_exists(Worker::class, 'statusFile')) {
            Worker::$statusFile = $config['status_file'] ?? '';
        }
        if (property_exists(Worker::class, 'stopTimeout')) {
            Worker::$stopTimeout = $config['stop_timeout'] ?? 2;
        }

        $worker = new Worker($config['listen'], $config['context']);

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
            if (isset($config[$property])) {
                $worker->$property = $config[$property];
            }
        }

        $worker->onWorkerStart = function (Worker $worker): void {
            $worker->onMessage = [$this, 'onMessage'];
        };

        // Windows does not support custom processes.
        if (\DIRECTORY_SEPARATOR === '/') {
            // 获取涉及 workerman 进程的 service id
            $processIds = $container->getParameter('workerman.processIds');

            foreach ($processIds ?? [] as $id) {

                $handler = $container->get($id);

                $config = [];

                if ($handler instanceof ProcessInterface) {
                    $workerConfig = $handler->getWorkerConfig();
                    $config = $workerConfig ? $workerConfig->toArray() : [];
                }

                $config['handler'] = $handler;

                Utils::workerStart($config['name'] ?: $id, $config);
            }
        }

        Worker::runAll();

        return 0;
    }

    /**
     * @throws \Exception
     */
    public function onMessage(TcpConnection $connection, ServerRequest $psrRequest)
    {
        $checkFile = "{$this->kernel->getProjectDir()}/public{$psrRequest->getUri()->getPath()}";
        $checkFile = str_replace('..', '/', $checkFile);

        if (is_file($checkFile)) {
            $code = file_get_contents($checkFile);
            $psrResponse = new Response(200, [
                'Content-Type' => $this->detector->detectMimeType($checkFile, $code),
                'Last-Modified' => gmdate('D, d M Y H:i:s', filemtime($checkFile)) . ' GMT',
            ], $code);
            $connection->send(response_to_string($psrResponse), true);
            return;
        }

        $this->kernel->boot();

        // 将PSR规范的请求，转换为Symfony请求进行处理，最终再转换成PSR响应进行返回
        $symfonyRequest = $this->httpFoundationFactory->createRequest($psrRequest);
        $symfonyResponse = $this->kernel->handle($symfonyRequest);
        $psrResponse = $this->psrHttpFactory->createResponse($symfonyResponse);

        // 注意，下面的意思是直接格式化整个HTTP报文，做得很彻底喔
        $connection->send(response_to_string($psrResponse), true);

        // 这里做最终的环境变量收集和处理
        $this->kernel->terminate($symfonyRequest, $symfonyResponse);

        // 设置单进程请求量达到额定时重启，防止代码写得不好产生OOM
        static $maxRequest;
        if (++$maxRequest > $this->maxRequest) {
            // $output->writeln("max request {$maxRequest} reached and reload");
            // send SIGUSR1 signal to master process for reload
            posix_kill(posix_getppid(), SIGUSR1);
        }
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
