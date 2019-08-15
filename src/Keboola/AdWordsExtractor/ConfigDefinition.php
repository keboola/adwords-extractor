<?php

declare(strict_types=1);

namespace Keboola\AdWordsExtractor;

use Keboola\Component\Config\BaseConfigDefinition;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;

class ConfigDefinition extends BaseConfigDefinition
{
    protected function getParametersDefinition(): ArrayNodeDefinition
    {
        $parametersNode = parent::getParametersDefinition();
        // @formatter:off
        /** @noinspection NullPointerExceptionInspection */
        $parametersNode
            ->children()
            ->scalarNode('customerId')->isRequired()->cannotBeEmpty()->end()
            ->scalarNode('since')->end()
            ->scalarNode('until')->end()
            ->scalarNode('bucket')->end()
            ->arrayNode('queries')->isRequired()->cannotBeEmpty()->arrayPrototype()
            ->children()
                ->scalarNode('name')->isRequired()->cannotBeEmpty()->end()
                ->scalarNode('query')->isRequired()->cannotBeEmpty()->end()
                ->arrayNode('primary')->scalarPrototype()->end()->end()
            ->end()
            ->end()
        ;
        // @formatter:on
        return $parametersNode;
    }
}
