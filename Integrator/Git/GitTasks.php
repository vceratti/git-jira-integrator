<?php

namespace Integrator\Git;

use \SebastianBergmann\Git\Git;

class GitTasks extends Git
{
    /** Configs loaded as object from the config/projectname.json
     * @var \stdClass configs loaded from
     */
    public $config;
    /**
     * @var State
     */
    public $state;
    /**
     * @var string
     */
    protected $logPath;
    /** @var string */
    protected $lastCommand;
    
    public function commit($message)
    {
        $this->execute("commit -m '$message'");
    }
    
    /** Logs and execute a git comand
     *
     * @param string $command
     *
     * @throws \SebastianBergmann\Git\RuntimeException
     * @throws \RuntimeException
     * @return string|array
     */
    protected function execute($command)
    {
        $this->lastCommand = "git $command";
        $this->logCommand($command);
        //echo "\n----$this->lastCommand\n";
        $output = parent::execute($command);
        $this->state->save();
        
        return $output;
    }
    
    /**
     *  Logs a command into log/projectname.logtarget
     *
     * @param $command
     *
     * @throws \RuntimeException
     */
    protected function logCommand($command)
    {
        $logfile = $this->getLogFileName();
        
        $log = '[' . date('m/d/Y H:i:s') . ']' . " $command\n";
        
        $this->appendToFile($logfile, $log);
    }
    
    /** Returns logfile path for current project
     * @return string
     */
    protected function getLogFileName()
    {
        return "{$this->logPath}/{$this->config->repository}.log";
    }
    
    /**
     * @param string $logfile
     * @param string $logContents
     *
     * @throws \RuntimeException
     */
    protected function appendToFile($logfile, $logContents)
    {
        $this->state->createFolderIfDoesntExists($this->logPath);
        
        file_put_contents($logfile, $logContents, FILE_APPEND);
    }
    
    /**
     * @throws \SebastianBergmann\Git\RuntimeException
     * @throws \RuntimeException
     * @return bool
     */
    public function notGitRepository()
    {
        $output = $this->execute('log');
        
        return preg_match('/Not a git repository/i', $output) > 0;
    }
    
    protected function rollback($commit)
    {
        $this->execute("reset --hard {$commit}");
    }
    
    /**
     * @throws \SebastianBergmann\Git\RuntimeException
     * @throws \RuntimeException
     * @return string
     */
    protected function getConflictingFiles()
    {
        $conflicting = $this->execute('diff --name-only --diff-filter=U');
        
        return implode(',', $conflicting);
    }
    
    /**
     * @param $files
     *
     * @throws \SebastianBergmann\Git\RuntimeException
     * @throws \RuntimeException
     * @return array|string
     */
    protected function checkConflictingBranches($files)
    {
        $getBranches = "log --all --format=%H -- $files | ";
        $getBranches .= 'while read f; do git branch --contains $f; done | sort -u';
        
        return $this->execute($getBranches);
    }
    
    /**
     * @param string $branch
     * @param string $message
     *
     * @throws \SebastianBergmann\Git\RuntimeException
     * @throws \RuntimeException
     * @return string|array
     */
    protected function merge($branch, $message)
    {
        if ($message != '') {
            $message = "-m '$message'";
        }
        
        return $this->execute("merge --no-ff $branch $message");
    }
    
    /**
     * @throws \SebastianBergmann\Git\RuntimeException
     * @throws \RuntimeException
     *
     * @param $branch
     */
    protected function checkoutOrCreateBranch($branch)
    {
        try {
            $this->fetch();
            $this->getRevisions();
            $this->execute('log');
        } catch (\Exception $e) {
            $this->createFirstCommit($branch, $e);
            $this->pushBranch($branch);
        }
        
        echo "Checking out $branch\n";
        $this->checkoutOrCreate($branch);
//      $this->pushBranch($branch);
        $this->pull($branch);
    }
    
    /**
     * @return string
     * @throws \SebastianBergmann\Git\RuntimeException
     * @throws \RuntimeException
     */
    public function fetch()
    {
        $this->lastCommand = 'git fetch';
        
        return $this->execute('fetch');
    }
    
    /**
     * @param string     $branch
     * @param \Exception $exception
     *
     * @throws \SebastianBergmann\Git\RuntimeException
     * @throws \RuntimeException
     */
    public function createFirstCommit($branch, \Exception $exception)
    {
        $commitDoesntExists = "'$branch' does not have any commits yet";
        if (preg_match("/$commitDoesntExists/", $exception->getMessage())) {
            $this->generateFirstCommit();
        }
    }
    
    /**
     * @param  string $branch
     * @return string
     * @throws \SebastianBergmann\Git\RuntimeException
     * @throws \RuntimeException
     */
    public function pull($branch)
    {
        $this->lastCommand = 'git pull';
        echo "Pulling changes from {$branch}\n";
        
        try {
            $this->execute("pull origin $branch");
        } catch (\Exception $e) {
            $this->execute("branch --set-upstream-to=origin/$branch");
        }
        
        return $this->execute('pull');
    }
    
    /**
     * @param string $branch
     *
     * @throws \SebastianBergmann\Git\RuntimeException
     * @throws \RuntimeException
     * @return string
     */
    protected function pushBranch($branch)
    {
        return $this->execute("push -u origin {$branch}");
    }
    
    /**
     * @throws \SebastianBergmann\Git\RuntimeException
     * @throws \RuntimeException
     */
    protected function generateFirstCommit()
    {
        $this->emptyCommit('Integration first commit');
    }
    
    /**
     * @param string $message
     *
     * @throws \SebastianBergmann\Git\RuntimeException
     * @throws \RuntimeException
     */
    protected function emptyCommit($message)
    {
        if ($message != '') {
            $message = "-m '$message'";
        }
        $this->execute("commit --allow-empty $message");
    }
    
    /**
     * @throws \SebastianBergmann\Git\RuntimeException
     * @throws \RuntimeException
     *
     * @param string $branch
     */
    protected function checkoutOrCreate($branch)
    {
        $this->execute("checkout -B $branch");
    }
}
