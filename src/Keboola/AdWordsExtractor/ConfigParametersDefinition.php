<?php
/**
 * @package adwords-extractor
 * @copyright Keboola
 * @author Jakub Matejka <jakub@keboola.com>
 */
namespace Keboola\AdWordsExtractor;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class ConfigParametersDefinition implements ConfigurationInterface
{
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('parameters');
        $rootNode
            ->children()
                ->scalarNode('#developer_token')->isRequired()->cannotBeEmpty()->end()
                ->scalarNode('customer_id')->isRequired()->cannotBeEmpty()->end()
                ->arrayNode('queries')->isRequired()->prototype('array')
                    ->children()
                        ->scalarNode('name')->isRequired()->cannotBeEmpty()->end()
                        ->scalarNode('query')->isRequired()->cannotBeEmpty()->end()
                    ->end()
                ->end()
        ;
        return $treeBuilder;
    }
}
