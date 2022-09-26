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

namespace WellKit\WorkermanBundle\DependencyInjection;

use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\XmlFileLoader;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\Yaml\Yaml;

class WorkermanExtension extends Extension
{
    private const SERVICE_KEYWORDS = [
        'alias' => 'alias',
        'parent' => 'parent',
        'class' => 'class',
        'shared' => 'shared',
        'synthetic' => 'synthetic',
        'lazy' => 'lazy',
        'public' => 'public',
        'abstract' => 'abstract',
        'deprecated' => 'deprecated',
        'factory' => 'factory',
        'file' => 'file',
        'arguments' => 'arguments',
        'properties' => 'properties',
        'configurator' => 'configurator',
        'calls' => 'calls',
        'tags' => 'tags',
        'decorates' => 'decorates',
        'decoration_inner_name' => 'decoration_inner_name',
        'decoration_priority' => 'decoration_priority',
        'decoration_on_invalid' => 'decoration_on_invalid',
        'autowire' => 'autowire',
        'autoconfigure' => 'autoconfigure',
        'bind' => 'bind',
    ];

    /**
     * @throws \Exception
     */
    public function load(array $configs, ContainerBuilder $container)
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new XmlFileLoader($container, new FileLocator(__DIR__ . '/../Resources/config'));
        $loader->load('workerman.xml');

        $container->getDefinition('workerman.config_loader')->addMethodCall('setConfig', [$config]);

        // 加载 process services
        $this->loadProcessServices($container, $config);
    }

    /**
     * 加载自定义进程服务
     *
     * @param string $cacheDir
     * @param ContainerBuilder $container
     * @param array $config
     * @return void
     * @throws \Exception
     */
    private function loadProcessServices(ContainerBuilder $container, array $config)
    {
        $services = [];

        foreach ($config['process'] ?: [] as $id => $service) {
            foreach ($service as $key => $value) {
                if (isset(static::SERVICE_KEYWORDS[$key])) {
                    $services[$id][$key] = $value;
                }
            }

            if (!isset($services[$id]['public'])) {
                $services[$id]['public'] = true;
            }

            if (!isset($services[$id]['autowire'])) {
                $services[$id]['autowire'] = true;
            }
        }

        $content = Yaml::dump([
            'services' => $services
        ], 4);

        $file = $container->getParameter('kernel.cache_dir') . '/workerman/services.yaml';

        $this->writeFile($file, $content);

        if (empty($services)) {
            return;
        }

        $locator = new FileLocator();
        $loader = new YamlFileLoader($container, $locator, $container->getParameter('kernel.environment'));
        $loader->load($file);
    }

    /**
     * Writes cache.
     *
     * @param string $file The file path
     * @param string $content The content to write in the cache
     *
     * @throws \RuntimeException When cache file can't be written
     */
    private function writeFile(string $file, string $content)
    {
        $mode = 0666;
        $umask = umask();
        $filesystem = new Filesystem();
        $filesystem->dumpFile($file, $content);
        try {
            $filesystem->chmod($file, $mode, $umask);
        } catch (IOException) {
            // discard chmod failure (some filesystem may not support it)
        }

        if (\function_exists('opcache_invalidate') && filter_var(\ini_get('opcache.enable'), \FILTER_VALIDATE_BOOLEAN)) {
            @opcache_invalidate($this->file, true);
        }
    }
}
