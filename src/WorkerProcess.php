<?php

namespace Alchemy\Worker;

/**
 * A worker running in the background, writing directly to the output of the
 * dispatcher. The dispatcher blocks while consuming and could not read a pipe:
 * a worker writing more than the size of a pipe would hang forever.
 */
class WorkerProcess
{
    /**
     * @var resource|null
     */
    private $handle;

    public function __construct(private array $command, private string $workingDirectory)
    {
    }

    public function start(): void
    {
        $descriptors = [
            0 => ['file', '/dev/null', 'r'],
            1 => fopen('php://stdout', 'w'),
            2 => fopen('php://stderr', 'w'),
        ];

        $handle = proc_open($this->command, $descriptors, $pipes, $this->workingDirectory);

        if ($handle === false) {
            throw new \RuntimeException(sprintf('Command "%s" could not be started.', $this->getCommandLine()));
        }

        $this->handle = $handle;
    }

    public function isRunning(): bool
    {
        return $this->handle !== null && proc_get_status($this->handle)['running'];
    }

    public function getCommandLine(): string
    {
        return implode(' ', array_map('escapeshellarg', $this->command));
    }
}
