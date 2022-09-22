# workerman-bundle

Symfony 的 workerman bundle，支持使用 `symfony runtime` 启动，并且支持通过 `service container` 自定义进程

## 安装

```bash
composer require wellkit/workerman-bundle
```

## 配置

1. 修改文件 `/config/bundles.php`

```php
return [
    // ...
    WellKit\WorkermanBundle\WorkermanBundle::class => ['all' => true],
];
```

2. 增加配置文件 `/config/packages/workerman.yaml`

```yaml
workerman:
    # 服务配置
    server:
        # 进程名称
        name: 'Symfony Workerman Server'
        # 监听的协议 ip 及端口 （可选）
        listen: http://0.0.0.0:8000
        # 进程数 （可选，生产环境下默认为 cpu核心数*2，其他环境下默认为1）
        count: ~
        # 进程运行用户 （可选，默认当前用户）
        user: ''
        # 进程运行用户组 （可选，默认当前用户组）
        group: ''
        # 当前进程是否支持reload （可选，默认true）
        reloadable: true
        # 是否开启reusePort （可选，此选项需要php>=7.0，默认为true）
        reusePort: true
        # transport (可选，当需要开启ssl时设置为ssl，默认为tcp)
        transport: tcp
        # context （可选，当transport为是ssl时，需要传递证书路径）
        context: []
        # After sending the stop command to the child process stopTimeout seconds,
        # if the process is still living then forced to kill.
        stopTimeout: 2
        # The file to store master process PID.
        pidFile: '%kernel.project_dir%/var/workerman.pid'
        # Log file.
        logFile: '%kernel.project_dir%/var/log/workerman.log'
        # The file used to store the master process status file.
        statusFile: '%kernel.project_dir%/var/log/workerman.status'
        # Stdout file.
        stdoutFile: '%kernel.project_dir%/var/log/workerman.stdout.log'

    # 自定义进程的 serviceId（功能与声明 `workerman.process` 标签相同）
    # 支持 service 相关配置参数：arguments、properties、factory 等
    # 支持 name、listen、count、user、group、reloadable、reusePort、transport、context 参数设置
    process:
        # 自定义进程的名称
        workerman.xxx:
            # 自定义进程的类，必须声明 onWorkerStart 方法
            class: App\Process\XXX

        # 监听文件变动自动重启
        workerman.monitor:
            class: WellKit\WorkermanBundle\Process\Monitor
            arguments:
                # the base dir
                $basePath: '%kernel.project_dir%'
                # the monitor dirs
                $resource: [ './src/', './config/', './public/', './templates/' ]
                # the file name patterns
                $patterns: [ '*.php', '*.yaml', '*.html', '*.htm', '*.twig' ]
                # the exclude dirs
                $exclude: []
```

## 启动

在项目根目录下执行

```bash
APP_RUNTIME=WellKit\\WorkermanBundle\\Runtime php ./public/index.php start
```

## 参考项目

- https://github.com/walkor/webman
- https://github.com/tourze/workerman-server-bundle
- https://github.com/manyou-io/workerman-symfony-runtime

## 许可证

MIT
