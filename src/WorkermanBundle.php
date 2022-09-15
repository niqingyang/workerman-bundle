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

use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use WellKit\WorkermanBundle\DependencyInjection\Compiler\ProcessPass;
use WellKit\WorkermanBundle\DependencyInjection\WorkermanExtension;


class WorkermanBundle extends Bundle
{
    public function build(ContainerBuilder $container)
    {
        parent::build($container);

        $container->addCompilerPass(new ProcessPass());
    }

    public function getContainerExtension(): ?ExtensionInterface
    {
        return new WorkermanExtension();
    }
}
