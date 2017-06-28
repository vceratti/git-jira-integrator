<?php

namespace Integrator\Jira;

use JiraRestApi\Issue\Issue;
use JiraRestApi\JiraClient;
use JiraRestApi\Issue\IssueService;

class Client extends JiraClient
{
    /** Default list of values to get from jira
     * @var array
     * @see Client::__construct()
     */
    private $queryParam;
    /**
     * @var \JiraRestApi\Issue\IssueService
     */
    private $issueService;
    /** @var \Integrator\Jira\JQL\Filters */
    private $jQLDefaultFilters;
    /** @var array of \Integrator\Jira\Issue s */
    public $doneIssuesList;
    /** @var array  of \Integrator\Jira\Issue s */
    public $homologIssuesList;
    /** @var array  of \Integrator\Jira\Issue s */
    public $releaseIssueList;
    /** @var  array of \Integrator\Jira\Issue s */
    public $productionIssueList;
    
    /**
     * JiraClient constructor.
     * @throws \InvalidArgumentException
     *
     * @param string $project Project name,  with the git repository name and the
     *                        corresponding config/$project.json file name for configuration file
     */
    public function __construct($project)
    {
        parent::__construct();
        $this->jQLDefaultFilters = new JQL\Filters($project . '.json');
        
        $this->setDefaultQueryParams();
        
        $this->issueService = new IssueService();
    }
    
    private function setDefaultQueryParams()
    {
        $this->queryParam = [
            'key',
            'issuetype',
            'priority',
            'project',
            'summary',
            'status',
            'labels',
            'assignee'
        ];
    }
    
    /** Gets all tasks which are in 'Homolog' status, then 'Done' status  */
    public function getReadyTaksFromCurrentSprint()
    {
        $this->doneIssuesList = $this->addIssues($this->queryDoneIssuesCurrentSprint());
        $this->homologIssuesList = $this->addIssues($this->queryHomologIssuesCurrentSprint());
        
        return $this->homologIssuesList + $this->doneIssuesList;
    }
    
    /**
     * @param array $resultIssues
     *
     * @return array
     */
    private function addIssues($resultIssues)
    {
        $addedIssues = [];
        foreach ($resultIssues as $issue) {
            $addedIssues[$issue->key] = $this->getIssueModel($issue);
        }
        
        echo 'Adding ' . count($addedIssues) . ' issues from Jira: ' .
            implode(', ', array_keys($addedIssues)) . "\n";
        
        return $addedIssues;
    }
    
    /**
     * @return array|mixed
     */
    public function queryDoneIssuesCurrentSprint()
    {
        return $this->queryIssuesByStatus('doneStatus', true);
    }
    
    /**
     * @return array|mixed
     */
    public function queryHomologIssuesCurrentSprint()
    {
        return $this->queryIssuesByStatus('homologStatus', true);
    }
    
    /**
     * @param \stdClass $issue
     *
     * @return Issue
     */
    private function getIssueModel($issue)
    {
        $newIssue = new Issue($issue->key);
        $newIssue->summary = $issue->fields->summary;
        $newIssue->type = $issue->fields->issuetype->name;
        $newIssue->priority = (int)$issue->fields->priority->id;
        $newIssue->status = $issue->fields->status->name;
        $newIssue->labels = $issue->fields->labels;
        $newIssue->project = $issue->fields->project->key;
        $newIssue->assigneName = $issue->fields->assignee->key;
        $newIssue->assigneEmail = $issue->fields->assignee->emailAddress;
        
        return $newIssue;
    }
    
    /**
     * @param string $status doneStatus|releaseStatus
     * @param bool   $currentSprintOnly
     *
     * @return array|mixed
     */
    private function queryIssuesByStatus($status, $currentSprintOnly)
    {
        $result = $this->searchIssuesByStatus($status, $currentSprintOnly);
        
        $issues = [];
        if (isset($result['issues'])) {
            $issues = $result['issues'];
        }
        
        return $issues;
    }
    
    /**
     * @param string $status doneStatus|releaseStatus
     * @param        $currentSprintOnly
     *
     * @return array
     */
    private function searchIssuesByStatus($status, $currentSprintOnly)
    {
        $statusFilter = $this->jQLDefaultFilters->getStatusFilter($status);
        
        $jql = $this->getJqlForStatus($statusFilter);
        if ($currentSprintOnly) {
            $jql->addAnd('sprint in openSprints()');
        }
        if ($status == 'releaseStatus') {
            $jql->addAnd("cf[{$this->jQLDefaultFilters->getJenkinsLabelFieldId()}] in (MERGED, RELEASE)");
        }
        
        return $this->searchIssues($jql);
    }
    
    /**
     * @param JQL\QueryBuilder $jql
     *
     * @return array
     */
    private function searchIssues($jql)
    {
        $jqlString = $jql->getJqlString();
        
        echo "\nSearching for issues with JQL: $jqlString\n\n";
        $result = $this->issueService->search($jqlString, 0, 999, $this->queryParam);
        $result = array_filter(get_object_vars($result));
        
        return $result;
    }
    
    /** Gets all fields from issue by Jira issue key
     *
     * @param string $key
     *
     * @return string json
     * @throws \JiraRestApi\JiraException
     * @throws \JsonMapper_Exception
     */
    public function getIssueLinks($key)
    {
        $queryParam = [
            'fields' => ['issueLink']
        ];
        
        return $this->issueService->get($key, $queryParam);
    }
    
    /** Get issue data by Jira issue key
     *
     * @param string $key
     *
     * @return \JiraRestApi\Issue\Issue
     * @throws \JiraRestApi\JiraException
     * @throws \JsonMapper_Exception
     */
    private function getIssue($key)
    {
        $queryParam = [
            'fields' => ['*all'],
            'expand' => $this->getExpandParamsArray()
        ];
        
        return $this->issueService->get($key, $queryParam);
    }
    
    /**
     * @return array
     */
    private function getExpandParamsArray()
    {
        return [
            'renderedFields',
            'names',
            'schema',
            'transitions',
            'operations',
            'editmeta',
            'changelog',
            $this->jQLDefaultFilters->getJenkinsLabelFieldId()
        ];
    }
    
    /**
     * @param string $issueKey
     * @param string $label
     */
    public function updateJenkinsLabel($issueKey, $label)
    {
        $jenkinsLabelFieldId = $this->jQLDefaultFilters->getJenkinsLabelFieldId();
        
        $arrNewState = ["customfield_$jenkinsLabelFieldId" => [$label]];
        
        $this->updateIssueField($issueKey, $arrNewState);
    }
    
    public function updateIssueField($issueKey, $arrFields)
    {
        echo "Updating $issueKey \n";
        $json = ['fields' => $arrFields];
        
        $this->exec("issue//$issueKey", json_encode($json), 'PUT');
    }
    
    public function sendToRejected($issueKey)
    {
        $this->moveIssue($issueKey, $this->getRejectedIssueTransitionId());
    }
    
    private function moveIssue($issueKey, $transitionId)
    {
        $setTransition['transition'] = ['id' => $transitionId];
        $setTransition = json_encode($setTransition);
        echo "Moving issue $issueKey: $setTransition\n";
        
        $transitionPath = "issue//$issueKey/transitions?expand=transitions.fields";
        
        $this->exec($transitionPath, $setTransition, 'POST');
    }
    
    private function getRejectedIssueTransitionId()
    {
        return $this->jQLDefaultFilters->rejectedTransitionId;
    }
    
    public function sendToAccepted($issueKey)
    {
        $this->moveIssue($issueKey, $this->getAcceptedTransitionId());
    }
    
    private function getAcceptedTransitionId()
    {
        return $this->jQLDefaultFilters->acceptedTransitionId;
    }
    
    public function getDeployedTasks()
    {
        $this->getProductionTasks();
        $this->getReleasedTasks();
        
        return $this->productionIssueList + $this->releaseIssueList;
    }
    
    public function getProductionTasks()
    {
        $resultIssues = $this->queryIssuesByStatus('productionStatus', false);
        $this->productionIssueList = $this->addIssues($resultIssues);
        
        return $this->productionIssueList;
    }
    
    public function getReleasedTasks()
    {
        $resultIssues = $this->queryIssuesByStatus('releaseStatus', false);
        $this->releaseIssueList = $this->addIssues($resultIssues);
        
        return $this->releaseIssueList;
    }
    
    /**
     * @param string $issueKey
     * @param array  $conflictingBranches
     */
    public function updateDependencies($issueKey, $conflictingBranches)
    {
        if (isset($conflictingBranches)) {
            foreach ($conflictingBranches as $branch) {
                $this->createBlockedByLink($issueKey, $branch);
            }
        }
    }
    
    private function createBlockedByLink($issueKey, $branch)
    {
        $jsonLinkCreateLink = [
            'type' => ['name' => 'Blocks'],
            'inwardIssue' => ['key' => $branch],
            'outwardIssue' => ['key' => $issueKey],
            'comment' => $this->getCommentBodyForConflict($branch)
        ];
        
        $this->updateIssueLink(json_encode($jsonLinkCreateLink));
    }
    
    /**
     * @param string $branch
     *
     * @return array
     */
    private function getCommentBodyForConflict($branch)
    {
        return [
            'body' =>
                preg_replace(
                    "/\n/",
                    ' ',
                    "Conflict task block: $branch must be merged into this task to resolve conflicts. 
                If $branch is already in Release/Production, you can simply update merge and dev branch."
                )
        ];
    }
    
    private function updateIssueLink($jsonCreateLink)
    {
        $this->exec('issueLink', $jsonCreateLink, 'POST');
    }
    
    public function moveToDeployed($issueKey)
    {
        $this->moveIssue($issueKey, $this->getDeployedIssueTransitionId());
    }
    
    private function getDeployedIssueTransitionId()
    {
        return $this->jQLDefaultFilters->deployedTransitionId;
    }
    
    /**
     * @param $statusFilter
     *
     * @return JQL\QueryBuilder
     */
    private function getJqlForStatus($statusFilter)
    {
        $jql = new JQL\QueryBuilder();
        
        $jql->where($statusFilter)
            ->addAnd($this->jQLDefaultFilters->getDefaultFilter());
        
        return $jql;
    }
    
    public function getBlockingIssues($issueKey)
    {
        $issueData = $this->getIssueLinks($issueKey);
    }
}
