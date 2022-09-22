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

use Symfony\Component\Config\Definition\Builder\NodeBuilder;
use Symfony\Component\Config\Definition\Builder\NodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    /**
     * {@inheritdoc}
     */
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('workerman');

        $treeBuilder
            ->getRootNode()
            ->addDefaultsIfNotSet()
            ->children()
                ->arrayNode('server')->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('name')->defaultNull()->end()
                        ->scalarNode('listen')->defaultNull()->end()
                        ->scalarNode('count')->defaultNull()->end()
                        ->scalarNode('user')->defaultNull()->end()
                        ->scalarNode('group')->defaultNull()->end()
                        ->booleanNode('reloadable')->defaultTrue()->end()
                        ->booleanNode('reusePort')->defaultTrue()->end()
                        ->enumNode('transport')->values(['tcp', 'ssl'])->defaultValue('tcp')->end()
                        ->arrayNode('context')->arrayPrototype()->end()->defaultValue([])->end()
                        ->scalarNode('stopTimeout')->defaultValue(2)->end()
                        ->scalarNode('pidFile')->defaultValue('')->end()
                        ->scalarNode('logFile')->defaultValue('')->end()
                        ->scalarNode('statusFile')->defaultValue('')->end()
                        ->scalarNode('stdoutFile')->defaultValue('')->end()
                    ->end()
                ->end()
                ->arrayNode('process')->variablePrototype()->end()
            ->end();

        return $treeBuilder;
    }
}
