<?php
/**
 * @package adwords-extractor
 * @copyright Keboola
 * @author Erik Zigo <erik.zigo@keboola.com>
 */
namespace Keboola\AdWordsExtractor\Tests;

use Keboola\AdWordsExtractor\RunCommand;
use Symfony\Component\Process\Process;

class EntrypointTest extends \PHPUnit\Framework\TestCase
{
    public function testFailureDueDeprecation()
    {
        $scriptPath = __DIR__ . '/../../../src/app.php';
        $dataDir = __DIR__ . '/../../data';

        $process = new Process('php ' . $scriptPath . ' run ' . $dataDir);
        $process->setTimeout(10);
        $process->run();

        $this->assertEquals(1, $process->getExitCode());
        $this->assertEmpty($process->getErrorOutput());
        $this->assertContains(RunCommand::ERROR_DEPRECATED_COMPONENT, $process->getOutput());
    }
}
