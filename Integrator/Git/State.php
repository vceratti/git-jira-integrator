<?php

namespace Integrator\Git;

/**
 * Class State
 * @package Integrator\Git
 */
class State
{
    /** Mapping from jSon state file
     * @var \stdClass
     */
    public $currentStatus;
    /** Path for project state file (state/projectState.json)
     * @var string
     */
    private $jsonStateFile;
    /** True if there is no state/projectState.json or it exists but has not 'branch' set in it
     * @var bool
     */
    private $newProject;
    /** @var  string date() */
    private $lastUpdate;
    
    /**
     * GitState constructor.
     *
     * @param Client $gitTasks
     *
     * @throws \RuntimeException
     */
    public function __construct($gitTasks)
    {
        $this->currentStatus = new \stdClass();
        $this->gitTasks = $gitTasks;
        $statePath = __DIR__ . '/currentStatus';
        $this->createFolderIfDoesntExists($statePath);
        $this->jsonStateFile = "{$statePath}/{$this->gitTasks->project}.json";
        
        $this->loadStateFromDisk();
    }
    
    /**
     * @param string $path
     *
     * @throws \RuntimeException
     */
    public function createFolderIfDoesntExists($path)
    {
        // @  silence operator is never good, but it is needed in this case
        // the if condition below is used to avoid a race condition, but when folder already exists,
        //   mkdir will throw a php warning,
        // see https://github.com/symfony/symfony/issues/11626
        if (!@mkdir($path, 0777, true) && !is_dir($path)) {
            throw new \RuntimeException();
        }
    }
    
    /** Reads json state file for the current project into private property
     * @see State::__construct() for jsonStateFile
     */
    private function loadStateFromDisk()
    {
        if (is_file($this->jsonStateFile)) {
            $jsonState = json_decode(file_get_contents($this->jsonStateFile));
        }
        if (!isset($jsonState)) {
            $jsonState = new \stdClass();
        }
        
        $this->currentStatus = $jsonState;
    }
    
    /**
     * From the state loaded from disk, tests if the current project already exists or its a new one
     * 'branch' must be set on state to consider the project an existing on
     * @see State::loadStateFromDisk()
     * @throws \SebastianBergmann\Git\RuntimeException
     * @throws \RuntimeException
     */
    public function checkIfNewProject()
    {
        $newProject = false;
        if (!isset($this->currentStatus, $this->currentStatus->workingBranch)
            || !is_dir($this->gitTasks->getWorkingDir())
        ) {
            $this->deleteFromDisk();
            $this->removeWorkingDir();
            $newProject = true;
            
            $this->currentStatus->workingBranch = $this->gitTasks->config->workingBranch;
        }
        
        return $newProject;
    }
    
    /**
     * Erase json state file for current project
     */
    public function deleteFromDisk()
    {
        $this->deleteFile($this->jsonStateFile);
    }
    
    private function removeWorkingDir()
    {
        $path = $this->gitTasks->getWorkingDir();
        if (is_dir($path)) {
            rename($path, "$path-" . date('m-d- H:i:s'));
        }
    }
    
    public function deleteFile($filename)
    {
        if (is_file($filename)) {
            unlink($filename);
        }
    }
    
    /**
     * Returns true if new
     */
    public function isNewProject()
    {
        return $this->newProject;
    }
    
    /**
     * Saves the current state into $this->jsonStateFile
     * @see State::__construct for jsonStateFile location
     */
    public function save()
    {
        $this->currentStatus->lastUpdate = date('m/d/Y H:i:s');
        $data = $this->jsonFromObject();
        file_put_contents($this->jsonStateFile, $data);
    }
    
    /**
     * Turns the object properties into a json, removing uneeded control vars
     */
    private function jsonFromObject()
    {
        $state = get_object_vars($this->currentStatus);
        
        return json_encode($state, JSON_PRETTY_PRINT);
    }
    
    public function updateBranchStatus($branch, Output $gitOutput)
    {
        $this->clearBranchStatus();
        $this->setBranchStatusByGitOutput($branch, $gitOutput);
    }
    
    /**
     * @param $branch
     */
    private function clearBranchStatus()
    {
        unset($this->currentStatus->branchesStatus);
        $this->currentStatus->branchesStatus = new \stdClass();
    }
    
    /**
     * @param string $branch
     * @param Output $gitOutput
     */
    private function setBranchStatusByGitOutput($branch, Output $gitOutput)
    {
        $branchObject = new \stdClass();
        $branchObject->label = $gitOutput->outputLabel;
        if (isset($this->destinationBranch)) {
            $branchObject->label = 'RELEASE';
        }
        
        $branchObject->outputText = $gitOutput->outputText;
        $branchObject->execCommand = $gitOutput->executedCommand;
        $branchObject->execTime = date('m/d/Y H:i:s');
        
        $this->currentStatus->branchesStatus->$branch = $branchObject;
    }
}
