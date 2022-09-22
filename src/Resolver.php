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

use Symfony\Component\Runtime\ResolverInterface;

class Resolver implements ResolverInterface
{
    public static $config = [];

    public function __construct(private ResolverInterface $resolver)
    {

    }

    public function resolve(): array
    {
        // Called in "autoload_runtime.php"
        [$app, $args] = $this->resolver->resolve();

        return [
            static function () use ($app, $args): KernelFactory {
                // App instantiator as an app
                return new KernelFactory($app, $args);
            },
            [],
        ];
    }
}
