<?php

namespace Integrator\Git;

/**l
 * Class Client
 * @package Integrator\Git
 */
class Client extends GitTasks
{
    /** @var  string */
    public $project;
    /** @var string */
    private $mergingBranch;
    /** @var  array */
    private $conflictingBranches;
    
    /**
     * GitTasks constructor.
     * From a config file (/config/$project.json):
     * - clone a new git repository if /state/$project.json is empty or doesn't have a branch set
     * - checkout branch set in config to work on it
     *
     * @param string $project Project name,  with the git repository name and the
     *                        corresponding config/$project.json file name for configuration file
     *
     * @throws \SebastianBergmann\Git\RuntimeException
     * @throws \RuntimeException
     * @throws \Exception
     * @throws \InvalidArgumentException
     */
    public function __construct($project)
    {
        $this->setConfigs($project);
        $state = new State($this);
        $state->createFolderIfDoesntExists($this->config->localPath);
        parent::__construct($this->config->localPath);
        
        $this->setState();
        // workarround for using clone and then changing path to cloned repo
        parent::__construct($this->getWorkingDir());
        $this->checkOrCreateBranches();
    }
    
    /**
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     *
     * @param $project
     */
    private function setConfigs($project)
    {
        $this->project = $project;
        date_default_timezone_set('America/Sao_Paulo');
        $this->logPath = __DIR__ . '/log';
        $jsonConfig = $this->readConfigFile($project);
        $this->config = json_decode($jsonConfig);
    }
    
    protected function setState()
    {
        $this->state = new State($this);
        if ($this->state->checkIfNewProject()) {
            $this->cloneNewProject();
        }
    }
    
    /**
     * @return string
     */
    public function getWorkingDir()
    {
        return "{$this->config->localPath}{$this->config->repository}";
    }
    
    private function checkOrCreateBranches()
    {
        $branch = $this->config->workingBranch;
        $revision = $this->getLastRevisionFromRemote($branch);
        
        if (!isset($revision)) {
            throw new \ErrorException("couldot work on branch: $branch");
        }
        
        $this->checkout($branch);
        $this->pull($branch);
    }
    
    /**
     * @param string $project
     *
     * @throws \InvalidArgumentException
     * @return string
     */
    private function readConfigFile($project)
    {
        $file = $this->getConfigFileName($project);
        if (!is_file($file)) {
            throw new \InvalidArgumentException(
                "$file.json not found. \n" .
                "You must set a config file for the Git Client.\n" .
                "(see Integrator\\Git\\config\\example.json)"
            );
        }
        
        return file_get_contents($file);
    }
    
    private function cloneNewProject()
    {
        try {
            $this->cloneRepository();
        } catch (\Exception $e) {
            $this->cleanProjectFiles();
            throw $e;
        }
    }
    
    /**
     * @param $branch
     *
     * @throws \SebastianBergmann\Git\RuntimeException
     * @throws \RuntimeException
     * @return array|string
     */
    private function getLastRevisionFromRemote($branch)
    {
        $revisionHash = $this->getRemoteRevision($branch);
        if (!isset($revisionHash)) {
            $this->createBranchFromMaster($branch);
            $revisionHash = $this->getRemoteRevision($branch);
        }
        
        return $revisionHash;
    }
    
    /**
     * @param $project
     *
     * @return string
     */
    private function getConfigFileName($project)
    {
        return __DIR__ . "/config/$project.json";
    }
    
    /**
     * @throws \SebastianBergmann\Git\RuntimeException
     * @throws \RuntimeException
     */
    private function cloneRepository()
    {
        $this->state->createFolderIfDoesntExists($this->config->localPath);
        $this->cloneDefaultRepository();
    }
    
    private function cleanProjectFiles()
    {
        $this->deleteLogFile();
        $this->state->deleteFromDisk();
    }
    
    /**
     * @param $branch
     *
     * @throws \SebastianBergmann\Git\RuntimeException
     * @throws \RuntimeException
     * @return mixed|null
     */
    private function getRemoteRevision($branch)
    {
        $revisionHash = null;
        $revision = $this->execute("ls-remote {$this->repoUrl()} refs/heads/$branch");
        
        if (isset($revision[0])) {
            $revisionHash = preg_replace('/\t.+/', '', $revision[0]);
        }
        
        return $revisionHash;
    }
    
    private function createBranchFromMaster($branch)
    {
        if ($branch != 'master') {
            $this->checkOrCreateMaster();
            $this->checkoutOrCreate($branch);
            $this->merge($branch, 'master');
            $this->pushBranch($branch);
        }
    }
    
    private function cloneDefaultRepository()
    {
        $repoUrl = $this->repoUrl();
        
        echo "Cloning $repoUrl...\n";
        $this->execute("clone $repoUrl");
    }
    
    /**
     * Clean any old logfile of the current project
     */
    private function deleteLogFile()
    {
        $this->state->deleteFile($this->getLogFileName());
    }
    
    /**
     * @return string
     */
    private function repoUrl()
    {
        return "{$this->config->url}/{$this->config->repository}.git";
    }
    
    /**
     * @throws \SebastianBergmann\Git\RuntimeException
     * @throws \RuntimeException
     */
    private function checkOrCreateMaster()
    {
        $revision = $this->getRemoteRevision('master');
        if (!isset($revision)) {
            $this->generateFirstCommit();
            $this->pushBranch('master');
        }
    }
    
    public function pushWorkingBranch()
    {
        return $this->pushBranch($this->config->workingBranch);
    }
    
    /**
     * @param string $branchName
     *
     * @throws \SebastianBergmann\Git\RuntimeException
     * @throws \RuntimeException
     */
    public function mergeFromOrigin($branchName)
    {
        $this->mergingBranch = $branchName;
        $this->tryToMerge();
    }
    
    /**
     * @return string
     * @throws \SebastianBergmann\Git\RuntimeException
     * @throws \RuntimeException
     */
    private function tryToMerge()
    {
        $this->fetch();
        try {
            $output = $this->merge("origin/{$this->mergingBranch}", $this->mergingBranch);
        } catch (\Exception $exception) {
            $output = $exception->getMessage();
        }
        
        $output = $this->parseExecReturn($output, $this->mergingBranch);
        $this->dealWithConflicts($output);
        
        return $output;
    }
    
    private function parseExecReturn($outputMessage, $mergedBranch)
    {
        $gitOutput = new Output($outputMessage, $this->lastCommand);
        $this->state->updateBranchStatus($mergedBranch, $gitOutput);
        $this->state->save();
        
        echo "Merge result for $mergedBranch: {$gitOutput->outputLabel}" . PHP_EOL;
        
        return $gitOutput->outputLabel;
    }
    
    /**
     * @throws \SebastianBergmann\Git\RuntimeException
     * @throws \RuntimeException
     *
     * @param string $output
     */
    private function dealWithConflicts($output)
    {
        if ($output == 'CONFLICT') {
            $files = $this->getConflictingFiles();
            $conflictingBranches = $this->checkConflictingBranches($files);
            $this->registerConflictingBranches($conflictingBranches);
            $this->rollback('HEAD');
        }
    }
    
    /**
     * @param array $conflictingBranches
     */
    private function registerConflictingBranches($conflictingBranches)
    {
        $this->conflictingBranches = [];
        
        foreach ($conflictingBranches as $branchRef) {
            $excludeArray = [$this->config->workingBranch, $this->mergingBranch];
            $branchName = preg_replace('/\*?\s*(\w+)\s*/i', '$1', $branchRef);
            
            if (!in_array($branchName, $excludeArray, true)) {
                $this->conflictingBranches[] = $branchName;
            }
        }
    }
    
    /**
     * @return array
     */
    public function getConflictingBranches()
    {
        return $this->conflictingBranches;
    }
    
    public function getBranchesState($issueKey)
    {
        return $this->state->currentStatus->branchesStatus->$issueKey;
    }
    
    public function isAcceptedState($issueNewState)
    {
        return $issueNewState == 'MERGED';
    }
    
    /**
     * @throws \SebastianBergmann\Git\RuntimeException
     * @throws \RuntimeException
     */
    public function mergeToDestination()
    {
        $this->fetch();
        $this->checkout($this->config->mergeToBranch);
        $this->pull($this->config->mergeToBranch);
        $this->mergingBranch = $this->config->workingBranch;
        $this->tryToMerge();
        $this->merge($this->config->workingBranch, 'deploy');
        $this->pushBranch($this->config->mergeToBranch);
        $this->checkout($this->config->workingBranch);
    }
    
    public function getDeployedBranches()
    {
        return $this->getMergedBranches('master');
    }
    
    private function getMergedBranches($branch)
    {
        $output = $this->execute("branch -r --merged origin/$branch");
        
        $branches = [];
        /** @var array $output */
        foreach ($output as $mergedRef) {
            if (!preg_match('/HEAD|dev|master|release/i', $mergedRef)) {
                $branches[] = trim(preg_replace('/origin\/(.+)/', '$1', $mergedRef));
            }
        }
        
        return $branches;
    }
}
