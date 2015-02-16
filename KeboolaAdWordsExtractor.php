<?php

namespace Keboola\AdWordsExtractor;

use Keboola\AdWordsExtractor\DependencyInjection\Extension;

class KeboolaAdWordsExtractor extends \Symfony\Component\HttpKernel\Bundle\Bundle
{
    public function getContainerExtension()
    {
        return new Extension();
    }
}
