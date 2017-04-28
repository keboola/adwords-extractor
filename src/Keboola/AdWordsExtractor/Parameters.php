<?php
/**
 * @package adwords-extractor
 * @copyright Keboola
 * @author Jakub Matejka <jakub@keboola.com>
 */
namespace Keboola\AdWordsExtractor;

use Symfony\Component\Config\Definition\ConfigurationInterface;
use Symfony\Component\Config\Definition\Processor;

class Parameters
{
    private $parameters;

    public function __construct(ConfigurationInterface $definition, array $parameters)
    {
        $this->parameters = (new Processor)->processConfiguration($definition, [$parameters]);
    }

    public function getParameters()
    {
        return $this->parameters;
    }
}
