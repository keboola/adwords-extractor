<?php
/**
 * @package adwords-extractor
 * @copyright Keboola
 * @author Jakub Matejka <jakub@keboola.com>
 */
namespace Keboola\AdWordsExtractor;

use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class OAuthParametersDefinition implements ConfigurationInterface
{
    public function getConfigTreeBuilder()
    {
        $treeBuilder = new TreeBuilder();
        $rootNode = $treeBuilder->root('authorization');
        $rootNode
            ->children()
                ->arrayNode('oauth_api')
                    ->isRequired()
                    ->children()
                        ->arrayNode('credentials')
                            ->isRequired()
                            ->children()
                                ->scalarNode('appKey')->isRequired()->cannotBeEmpty()->end()
                                ->scalarNode('#appSecret')->isRequired()->cannotBeEmpty()->end()
                                ->scalarNode('#data')->isRequired()->cannotBeEmpty()->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end()
        ;
        return $treeBuilder;
    }
}
