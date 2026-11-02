<?php

/*
 * This file is part of alchemy/worker-component.
 *
 * (c) Alchemy <info@alchemy.fr>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Alchemy\Worker;

use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Process\Exception\RuntimeException as ProcessRuntimeException;

class WorkerInvoker implements LoggerAwareInterface
{
    /**
     * @var string
     */
    private $environment;

    /**
     * @var string
     */
    private $command = 'workers:run-worker';

    /**
     * @var string
     */
    private $binaryPath;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var ProcessPool
     */
    private $processPool;

    /**
     * @var bool
     */
    private $preservePayloads = false;

    public function __construct(ProcessPool $processPool, $environment = false)
    {
        $this->binaryPath = $_SERVER['SCRIPT_NAME'];
        $this->environment = $environment;
        $this->processPool = $processPool;
        $this->logger = new NullLogger();
    }

    public function preservePayloads()
    {
        $this->preservePayloads = true;
    }

    /**
     * Sets a logger instance on the object
     *
     * @param LoggerInterface $logger
     */
    public function setLogger(LoggerInterface $logger): void
    {
        $this->logger = $logger;
    }

    /**
     * @param string $messageType
     * @param string $payload
     */
    public function invokeWorker($messageType, $payload)
    {
        $args = [
            $this->binaryPath,
            $this->command,
            '-vv',
            $messageType,
            $this->createPayloadFile($payload)
        ];

        if ($this->environment) {
            $args[] = sprintf('--env=%s', $this->environment);
        }

        if ($this->preservePayloads) {
            $args[] = '--preserve-payload';
        }

        $process = $this->processPool->getWorkerProcess($this->withInheritedOutput($args), getcwd());
        // The dispatcher blocks while consuming and never reads the output of its workers:
        // a worker writing more than the size of a pipe would hang forever.
        $process->disableOutput();

        $this->logger->debug('Invoking shell command: ' . $process->getCommandLine());

        try {
            $process->start();
        } catch (ProcessRuntimeException $e) {
            $process->stop();

            throw new \RuntimeException(sprintf('Command "%s" failed: %s', $process->getCommandLine(),
                $e->getMessage()), 0, $e);
        }
    }

    /**
     * Sends the output of the worker to the output of the dispatcher, for the container logs.
     */
    private function withInheritedOutput(array $args)
    {
        $script = 'if [ -w /proc/$PPID/fd/2 ]; then exec "$@" >>/proc/$PPID/fd/1 2>>/proc/$PPID/fd/2; fi; exec "$@" >/dev/null 2>&1';

        return array_merge(['sh', '-c', $script, 'sh'], $args);
    }

    private function createPayloadFile($payload)
    {
        $path = tempnam(sys_get_temp_dir(), 'parade_wk_');

        if (file_put_contents($path, $payload) === false) {
            throw new \RuntimeException('Cannot write payload file to path: ' . $path);
        }

        return $path;
    }

}
