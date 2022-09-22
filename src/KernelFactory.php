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

use Closure;
use Symfony\Component\HttpKernel\KernelInterface;

/**
 * the kernel factory
 */
class KernelFactory
{
    public function __construct(private Closure $app, private array $args)
    {

    }

    public function createKernel(): KernelInterface
    {
        return call_user_func($this->app, ...$this->args);
    }
}
