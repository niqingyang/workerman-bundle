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

namespace WellKit\WorkermanBundle\DependencyInjection\Compiler;

use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class ProcessPass implements CompilerPassInterface
{

    public function process(ContainerBuilder $container)
    {
        // TODO: Implement process() method.
        $serviceIds = $container->findTaggedServiceIds('workerman.process');

        $serviceIds = array_keys($serviceIds);

        foreach ($serviceIds as $id) {
            // 强制设置 public 为 true
            $container->getDefinition($id)->setPublic(true);
        }

        $processIds = $container->getParameter('workerman.processIds') ?? [];
        $processIds = array_values(array_unique(array_merge($processIds, $serviceIds)));

        $container->setParameter('workerman.processIds', $processIds);
    }
}
