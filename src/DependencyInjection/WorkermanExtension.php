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
use Symfony\Component\Config\Resource\ResourceInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\DependencyInjection\Extension;
use Symfony\Component\Yaml\Yaml;
use WellKit\WorkermanBundle\ConfigLoader;

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

        $cacheDir = $container->getParameter('kernel.cache_dir');
        $isDebug = $container->getParameter('kernel.debug');

        // 加载 process services
        $this->loadServices($cacheDir, $container, $config);

        // 换成配置
        $configLoader = new ConfigLoader($cacheDir, $isDebug);
        $configLoader->setConfig($config);
    }

    private function loadServices(string $cacheDir, ContainerBuilder $container, array $config)
    {
        $services = [];

        foreach ($config['process'] ?: [] as $id => $service) {
            foreach ($service as $key => $value) {
                if (isset(static::SERVICE_KEYWORDS[$key])) {
                    $services[$id][$key] = $value;
                }
            }

            $services[$id]['public'] = true;
            $services[$id]['autowire'] = true;
        }

        $content = Yaml::dump([
            'services' => $services
        ], 4);

        $serviceYamlFile = $cacheDir . '/workerman/services.yaml';

        $this->writeFile($serviceYamlFile, $content);

        $locator = new FileLocator();
        $loader = new YamlFileLoader($container, $locator, $container->getParameter('kernel.environment'));
        $loader->load($serviceYamlFile);
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
