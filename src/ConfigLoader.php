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

use Symfony\Component\Config\ConfigCache;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;

class ConfigLoader implements CacheWarmerInterface
{
    private array $config = [];
    private ConfigCache $cache;

    public function __construct(string $cacheDir, bool $isDebug)
    {
        $this->cache = new ConfigCache(sprintf('%s/workerman/config.cache.php', $cacheDir), $isDebug);
    }

    /**
     * get config
     *
     * @return array
     */
    public function getConfig()
    {
        if (!$this->cache->isFresh()) {
            $this->warmUp('');
        }

        return require $this->cache->getPath();
    }

    /**
     * set config
     *
     * @param array $config
     * @return void
     */
    public function setConfig(array $config)
    {
        $this->config = $config;
        $this->warmUp('');
    }

    /**
     * @return bool
     */
    public function isOptional(): bool
    {
        return true;
    }

    /**
     * @param string $cacheDir
     * @return void
     */
    public function warmUp(string $cacheDir)
    {
        $this->cache->write(
            sprintf('<?php return %s;', var_export($this->config, true)),
            []
        );
    }
}
