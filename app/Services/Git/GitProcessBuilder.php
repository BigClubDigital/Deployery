<?php

namespace App\Services\Git;

use App\Exceptions\Git\GitException;
use App\Exceptions\Git\GitInvalidBranchException;
use Symfony\Component\Process\Process;

/**
 * Get Git Info for a repo and branch.
 */
class GitProcessBuilder
{

    private $repo;
    private $branch;
    private $args = [];
    private $env = [];

    /**
     * Password
     * @var String
     */
    private $password;

    public function __construct(string $repo, $branch = 'master')
    {
        $this->repo = $repo;
        $this->branch = $branch;
        $this->args = ['/usr/bin/git'];
    }

    public function getProcess(int $timeout=100) : Process {
        return (new Process($this->args, $this->repo))
           ->setEnv($this->env)
           ->setTimeout($timeout);
    }

    /**
     * Update the ProcessBuilder to use a specific public key.
     * @param  string|null $pub_key [description]
     * @return [type]               [description]
     */
    public function withPubKey(string $pub_key=null)
    {
        if ($pub_key) {
            $ssh_cmd = "ssh -i {$pub_key} -o StrictHostKeyChecking=no";
            $this->setEnv("GIT_SSH_COMMAND", $ssh_cmd);
        }
        return $this;
    }


    public function withPassword(string $password)
    {
        $this->password = $password;
        return $this;
    }

    public function add($arg) {
        $this->args[] = $arg;
        return $this;
    }

    private function setEnv($key, $value)
    {
        $this->env[$key] = $value;
        return $this;
    }

    /**
     * Update the ProcessBuilder's args using a string.
     *
     * @param string $task [description]
     */
    public function setTask(string $task)
    {
        $args = explode(' ', $task);
        foreach ($args as $arg) {
            $this->add($arg);
        }
        return $this;
    }
}
