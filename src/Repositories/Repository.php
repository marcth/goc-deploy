<?php

namespace Marcth\GocDeploy\Repositories;

use Marcth\GocDeploy\Exceptions\ConnectionRefusedException;
use Marcth\GocDeploy\Exceptions\GitMergeConflictException;
use Marcth\GocDeploy\Exceptions\InvalidGitBranchException;
use Marcth\GocDeploy\Exceptions\InvalidGitReferenceException;
use Marcth\GocDeploy\Exceptions\InvalidGitRepositoryException;
use Marcth\GocDeploy\Exceptions\InvalidPathException;
use Marcth\GocDeploy\Exceptions\ProcessException;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Exception\ProcessTimedOutException;
use Symfony\Component\Process\Process;
use Symfony\Component\Process\Exception\RuntimeException;

abstract class Repository
{

    /**
     * @param string $command
     * @param string $cwd
     * @return string
     * @throws GitMergeConflictException
     * @throws InvalidGitBranchException
     * @throws InvalidGitReferenceException
     * @throws InvalidGitRepositoryException
     * @throws InvalidPathException
     * @throws ProcessException
     * @throws ConnectionRefusedException
     */
    protected function execute(string $command, string $cwd): string
    {
        return trim($this->process($command, $cwd)->getOutput());
    }

    /**
     * Process is a thin wrapper around proc_* functions to easily start independent PHP processes.
     *
     * @param string $command The command to run and its arguments
     * @param string|null $cwd The working directory or null to use the working dir of the current PHP process
     * @return Process
     *
     * @throws InvalidPathException
     * @throws ProcessException
     * @throws InvalidGitBranchException
     * @throws InvalidGitReferenceException
     * @throws InvalidGitRepositoryException
     * @throws GitMergeConflictException
     * @throws ConnectionRefusedException
     *
     * @see vendor/symfony/process/Process.php
     */
    protected function process(string $command, string $cwd): Process
    {
        $env = null;
        $input = null;
        $timeout = 60;

        $process = new Process(explode(' ', $command), $cwd, $env, $input, $timeout);

        try {
            $process->run();

            if (!$process->isSuccessful()) {
                throw new ProcessFailedException($process);
            }

        } catch (ProcessTimedOutException $e) {
            throw new ProcessException($e->getMessage(), $e->getCode());

        } catch (ProcessFailedException $e) {
            if(0 < 1) {
//                print __METHOD__ . ':' . __LINE__ . ":\n";
//                print '$e->getCode() = ' . $e->getCode();
//                print '$e->getMessage() = ' . $e->getMessage();
//                print '$e->getProcess()->getExitCode() = ' . $e->getProcess()->getExitCode();
//                print '$e->getProcess()->getErrorOutput() = ' . $e->getProcess()->getErrorOutput();

                //The command "'git' 'merge' '--no-ff' '--no-edit' 'develop'" failed.
                if(str_contains($e->getMessage(), 'Automatic merge failed')) {
                    throw new GitMergeConflictException(null, null, $e);
                }
            }

            if ($e->getProcess()->getExitCode() == 1) {
                // error: ...'development' did not match any file(s) known to git
                if(str_contains($e->getProcess()->getErrorOutput(), "did not match any file(s) known to git")) {
                    throw new InvalidGitBranchException(null, null, $e);
                }
            }

            if($e->getProcess()->getExitCode() == 128) {
                // fatal: 'refs/heads/development' - not a valid ref
                if (str_contains($e->getProcess()->getErrorOutput(), 'not a valid ref')) {
                    throw new InvalidGitReferenceException(null, null, $e);
                }

                // Fatal: not a git repository (or any of the parent directories): .git
                if (str_contains($e->getProcess()->getErrorOutput(), "Fatal: not a git repository")) {
                    throw new InvalidGitRepositoryException(null, null, $e);
                }

                //fatal: unable to access '{url}': Failed to connect to {host} port 443: Connection refused
                if (str_contains($e->getProcess()->getErrorOutput(), "Connection refused")) {
                    throw new ConnectionRefusedException($e->getProcess()->getErrorOutput(), null, $e);
                }
            }

            throw new ProcessException($e->getProcess()->getErrorOutput(), $e->getProcess()->getExitCode(), $e);
        } catch (RuntimeException $e) {
            if (str_contains($e->getMessage(), 'The provided cwd ')) {
                $message = str_replace('cwd', 'working directory', $e->getMessage());
                throw new InvalidPathException($message, $e->getCode());
            }

            throw $e;

        }

        return $process;
    }
}
