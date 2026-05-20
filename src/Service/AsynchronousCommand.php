<?php

/**
 * This file is part of a Spipu Bundle
 *
 * (c) Laurent Minguet
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Spipu\CoreBundle\Service;

use Spipu\CoreBundle\Exception\AsynchronousCommandException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Exception\RuntimeException;
use Symfony\Component\Process\Process;

class AsynchronousCommand
{
    private ProcessFactory $processFactory;
    private Filesystem $filesystem;
    private string $projectDir;
    private string $logsDir;
    private string $phpBin;
    private string $logFilename;
    private bool $disableLog = false;

    /**
     * AsynchronousCommand constructor.
     * @param ProcessFactory $processFactory
     * @param Filesystem $filesystem
     * @param string $projectDir
     * @param string $logsDir
     * @param string $phpBin
     * @param string $logFilename
     */
    public function __construct(
        ProcessFactory $processFactory,
        Filesystem $filesystem,
        string $projectDir,
        string $logsDir,
        string $phpBin = 'php',
        string $logFilename = 'asynchronous-command.log'
    ) {
        $this->processFactory = $processFactory;
        $this->filesystem = $filesystem;
        $this->projectDir = $projectDir;
        $this->logsDir = $logsDir;
        $this->phpBin = $phpBin;
        $this->logFilename = $logFilename;
    }

    public function setDisableLog(bool $disableLog): self
    {
        $this->disableLog = $disableLog;

        return $this;
    }

    public function create(string $command, array $parameters): Process
    {
        // Escape parameters.
        foreach ($parameters as &$parameter) {
            if (is_string($parameter)) {
                $parameter = escapeshellarg($parameter);
            }
        }

        // Build Command Line.
        $cmd = array_merge(
            [$this->phpBin, 'bin/console', escapeshellarg($command)],
            $parameters
        );

        // Define LogFile.
        $logFile = '/dev/null';
        if (!$this->disableLog) {
            $logFile = $this->logsDir . DIRECTORY_SEPARATOR . $this->logFilename;
            $content = '[' . date('Y-m-d H:i:d') . '] ' . implode(' ', $cmd) . "\n";

            $this->filesystem->appendToFile($logFile, $content);
        }

        // Add LogFile to Command Line.
        $cmd[] = '>> ' . $logFile;
        $cmd[] = '2>&1';

        // Launch the process.
        return $this->processFactory->create(
            implode(' ', $cmd),
            $this->projectDir . DIRECTORY_SEPARATOR
        );
    }

    public function execute(string $command, array $parameters): bool
    {
        $process = $this->create($command, $parameters);
        $process->setOptions(['create_new_console' => true]);
        $process->setEnv($this->buildRequestEnvOverride());
        try {
            $process->start();
        } catch (RuntimeException $e) {
            if ($e->getMessage() === 'Unable to launch a new process.') {
                throw new AsynchronousCommandException($e->getMessage());
            }

            throw $e;
        }

        return true;
    }

    /**
     * Build the env override that strips CGI/HTTP variables inherited from a PHP-FPM/Apache
     * request, so the child CLI process is not detected as running in a web context.
     *
     * @return array<string, false>
     */
    private function buildRequestEnvOverride(): array
    {
        $cgiVars = [
            'QUERY_STRING', 'REQUEST_METHOD', 'REQUEST_URI', 'REQUEST_SCHEME',
            'REQUEST_TIME', 'REQUEST_TIME_FLOAT', 'CONTENT_TYPE', 'CONTENT_LENGTH',
            'SCRIPT_NAME', 'SCRIPT_FILENAME', 'PATH_INFO', 'PATH_TRANSLATED',
            'DOCUMENT_ROOT', 'DOCUMENT_URI', 'CONTEXT_DOCUMENT_ROOT', 'CONTEXT_PREFIX',
            'GATEWAY_INTERFACE', 'AUTH_TYPE', 'FCGI_ROLE', 'HTTPS',
            'SERVER_PROTOCOL', 'SERVER_SOFTWARE', 'SERVER_NAME', 'SERVER_ADDR',
            'SERVER_PORT', 'SERVER_ADMIN', 'SERVER_SIGNATURE',
            'REMOTE_ADDR', 'REMOTE_PORT', 'REMOTE_USER', 'REMOTE_HOST', 'REMOTE_IDENT',
            'PHP_AUTH_USER', 'PHP_AUTH_PW', 'PHP_AUTH_DIGEST',
            'UNIQUE_ID',
        ];
        $prefixes = ['HTTP_', 'REDIRECT_'];

        $override = [];
        foreach (array_keys($_SERVER) as $key) {
            if (in_array($key, $cgiVars, true)) {
                $override[$key] = false;
                continue;
            }
            foreach ($prefixes as $prefix) {
                if (strpos($key, $prefix) === 0) {
                    $override[$key] = false;
                    break;
                }
            }
        }

        return $override;
    }
}
