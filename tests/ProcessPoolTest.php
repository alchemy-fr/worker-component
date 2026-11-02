<?php

namespace Alchemy\Worker\Tests;

use Alchemy\Worker\ProcessPool;
use PHPUnit\Framework\TestCase;
use Alchemy\Worker\WorkerProcess;

class ProcessPoolTest extends TestCase
{
    public function testWorkerProcessIsBuiltFromArguments()
    {
        $pool = new ProcessPool(2);
        $output = tempnam(sys_get_temp_dir(), 'worker');
        $process = $pool->getWorkerProcess([PHP_BINARY, '-r', 'file_put_contents($argv[1], getcwd());', $output], sys_get_temp_dir());

        $this->assertInstanceOf(WorkerProcess::class, $process);

        $process->start();

        while ($process->isRunning()) {
            usleep(10000);
        }

        $this->assertSame(realpath(sys_get_temp_dir()), file_get_contents($output));
        unlink($output);
    }

    public function testWorkingDirectoryDefaultsToCurrentDirectory()
    {
        $pool = new ProcessPool();
        $process = $pool->getWorkerProcess([PHP_BINARY, '-v']);

        $this->assertInstanceOf(WorkerProcess::class, $process);
    }
}
