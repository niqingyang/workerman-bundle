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

/**
 * worker 配置
 */
class WorkerConfig
{
    /**
     * 进程名称
     * @var string
     */
    private string $name = '';

    /**
     * 监听的协议 ip 及端口 （可选）
     *
     * @var string
     */
    private string $listen = '';

    /**
     * 进程数 （可选，默认1）
     *
     * @var int
     */
    private ?int $count = null;

    /**
     * 进程运行用户 （可选，默认当前用户）
     *
     * @var string
     */
    private string $user = '';

    /**
     * 进程运行用户组 （可选，默认当前用户组）
     *
     * @var string
     */
    private string $group = '';

    /**
     * 当前进程是否支持reload （可选，默认true）
     *
     * @var bool
     */
    private bool $reloadable = true;

    /**
     * 是否开启reusePort （可选，此选项需要php>=7.0，默认为true）
     *
     * @var bool
     */
    private bool $reusePort = true;

    /**
     * transport (可选，当需要开启ssl时设置为ssl，默认为tcp)
     *
     * @var string
     */
    private string $transport = 'tcp';

    /**
     * context （可选，当transport为是ssl时，需要传递证书路径）
     *
     * @var array
     */
    private array $context = [];


    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @param string $name
     */
    public function setName(string $name): void
    {
        $this->name = $name;
    }

    /**
     * @return string
     */
    public function getListen(): string
    {
        return $this->listen;
    }

    /**
     * @param string $listen
     */
    public function setListen(string $listen): void
    {
        $this->listen = $listen;
    }

    /**
     * @return int
     */
    public function getCount(): ?int
    {
        return $this->count;
    }

    /**
     * @param int $count
     */
    public function setCount(?int $count): void
    {
        $this->count = $count;
    }

    /**
     * @return string
     */
    public function getUser(): string
    {
        return $this->user;
    }

    /**
     * @param string $user
     */
    public function setUser(string $user): void
    {
        $this->user = $user;
    }

    /**
     * @return string
     */
    public function getGroup(): string
    {
        return $this->group;
    }

    /**
     * @param string $group
     */
    public function setGroup(string $group): void
    {
        $this->group = $group;
    }

    /**
     * @return bool
     */
    public function isReloadable(): bool
    {
        return $this->reloadable;
    }

    /**
     * @param bool $reloadable
     */
    public function setReloadable(bool $reloadable): void
    {
        $this->reloadable = $reloadable;
    }

    /**
     * @return bool
     */
    public function isReusePort(): bool
    {
        return $this->reusePort;
    }

    /**
     * @param bool $reusePort
     */
    public function setReusePort(bool $reusePort): void
    {
        $this->reusePort = $reusePort;
    }

    /**
     * @return string
     */
    public function getTransport(): string
    {
        return $this->transport;
    }

    /**
     * @param string $transport
     */
    public function setTransport(string $transport): void
    {
        $this->transport = $transport;
    }

    /**
     * @return array
     */
    public function getContext(): array
    {
        return $this->context;
    }

    /**
     * @param array $context
     */
    public function setContext(array $context): void
    {
        $this->context = $context;
    }

    /**
     * 转换为数组
     *
     * @return array
     */
    public function toArray(): array
    {
        return get_object_vars($this);
    }
}
