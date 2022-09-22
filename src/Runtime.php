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

use ReflectionFunction;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\Runtime\ResolverInterface;
use Symfony\Component\Runtime\RunnerInterface;
use Symfony\Component\Runtime\SymfonyRuntime;

class Runtime extends SymfonyRuntime
{
    private string $pidFile;
    private string $logFile;
    private string $statusFile;
    private string $stdoutFile;
    private WorkerConfig $config;

    public function __construct(array $options = [])
    {
        $this->config = new WorkerConfig();

        if (isset($options['listen']) && $options['listen']) {
            $this->config->setListen($options['listen']);
        }

        if (isset($options['count']) && intval($options['count'])) {
            $this->config->setCount(intval($options['count']));
        }

        $this->pidFile = $options['pidFile'] ?? "";
        $this->logFile = $options['logFile'] ?? "";
        $this->statusFile = $options['statusFile'] ?? "";
        $this->stdoutFile = $options['stdoutFile'] ?? "";

        parent::__construct($options);
    }

    public function getRunner(?object $application): RunnerInterface
    {
        return new Runner($application, $this->config, $this->pidFile, $this->logFile, $this->statusFile, $this->stdoutFile);
    }

    public function getResolver(callable $callable, ?ReflectionFunction $reflector = null): ResolverInterface
    {
        $resolver = parent::getResolver($callable, $reflector);

        return new Resolver($resolver);
    }
}
