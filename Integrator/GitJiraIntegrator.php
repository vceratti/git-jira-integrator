<?php

namespace Integrator;

class GitJiraIntegrator
{
    /**
     * @var Git\Client
     */
    private $gitClient;
    /**
     * @var Jira\Client
     */
    private $jiraClient;
    
    /**
     * GitJiraIntegrator constructor.
     *
     * @param string $project
     *
     * @throws \SebastianBergmann\Git\RuntimeException
     * @throws \RuntimeException
     * @throws \Exception
     * @throws \InvalidArgumentException
     */
    public function __construct($project)
    {
        $this->gitClient = new Git\Client($project);
        $this->jiraClient = new Jira\Client($project);
    }
    
    public function integrateDoneTasks()
    {
        $doneTasks = $this->jiraClient->getReadyTaksFromCurrentSprint();
        
        $taskKeys = array_keys($doneTasks);
        foreach ($taskKeys as $issueKey) {
            if ($this->canMergeBranch($issueKey)) {
                $this->gitClient->mergeFromOrigin($issueKey);
                
                $issueNewState = $this->gitClient->getBranchesState($issueKey);
                $issueAccepted = $this->gitClient->isAcceptedState($issueNewState->label);
                $this->jiraClient->updateJenkinsLabel($issueKey, $issueNewState->label);
                $this->moveDoneIssue($issueAccepted, $issueKey);
                $this->jiraClient->updateDependencies($issueKey, $this->gitClient->getConflictingBranches());
            }
        }
    }
    
    /** If issue is in Done status, moves it to 'accepted' or 'rejected' status
     *
     * @param bool   $issueAccepted
     * @param string $issueKey
     */
    private function moveDoneIssue($issueAccepted, $issueKey)
    {
        $isDoneTask = array_key_exists($issueKey, $this->jiraClient->doneIssuesList);
        if ($isDoneTask) {
            if ($issueAccepted) {
                $this->jiraClient->sendToAccepted($issueKey);
            }
            if (!$issueAccepted) {
                $this->jiraClient->sendToRejected($issueKey);
            }
        }
    }
    
    public function integrateReleaseTasks()
    {
        $mergedTasks = $this->mergeNewReleaseTasks();
        if (count($mergedTasks) > 0) {
            //$this->gitClient->commit('create rc');
            $this->gitClient->pushWorkingBranch();
            $this->gitClient->mergeToDestination();
            
            foreach ($mergedTasks as $key) {
                $this->jiraClient->updateJenkinsLabel($key, 'RELEASE');
            }
        }
    }
    
    /**
     * @return array Keys of merged release
     * @throws \Error
     * @throws \SebastianBergmann\Git\RuntimeException
     * @throws \RuntimeException
     */
    private function mergeNewReleaseTasks()
    {
        $releaseTasks = $this->jiraClient->getReleasedTasks();
        $taskKeys = array_keys($releaseTasks);
        
        foreach ($taskKeys as $issueKey) {
            $this->gitClient->mergeFromOrigin($issueKey);
            $issueNewState = $this->gitClient->getBranchesState($issueKey);
            $issueAccepted = $this->gitClient->isAcceptedState($issueNewState->label);
            
            if (!$issueAccepted) {
                throw new \Error('Inconsistent repository state: invalid task sent to release!');
            }
        }
        
        return $taskKeys;
    }
    
    public function deployLatestRelease()
    {
        $this->gitClient->mergeToDestination();
        $branches = $this->gitClient->getDeployedBranches();
        $this->jiraClient->getReleasedTasks();
        
        foreach ($branches as $key) {
            $this->moveReleasedIssue($key);
        }
    }
    
    private function moveReleasedIssue($issueKey)
    {
        $isReleaseTask = array_key_exists($issueKey, $this->jiraClient->releaseIssueList);
        if ($isReleaseTask) {
            $this->jiraClient->moveToDeployed($issueKey);
        }
    }
    
    private function canMergeBranch($issueKey) : bool
    {
        $deployedTasks = $this->jiraClient->getDeployedTasks();
        
        $data = $this->jiraClient->getBlockingIssues($issueKey);
        
        return false;
    }
}
