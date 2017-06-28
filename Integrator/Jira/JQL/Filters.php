<?php

namespace Integrator\Jira\JQL;

/**
 * Class Filters
 * The default query filters based on config files
 * @package Integrator
 */
class Filters
{
    /** @var  string  id of Jira custom field to update with result labels */
    public $jenkinsLabelFieldId;
    /** @var string id of the transition to execute when a task was rejected (done -> doing) */
    public $rejectedTransitionId;
    /** @var string id of the transition to execute when a task was accepted (done -> homolog) */
    public $acceptedTransitionId;
    /** @var string id of the transition to execute when a task was deployed (release -> production) */
    public $deployedTransitionId;
    /**
     * @var array List of configs based on config file (see example.json and README)
     */
    private $config;
    /**
     * @var string
     */
    private $defaultFilter;
    /**
     * @var string
     */
    private $doneFilter;
    /** @var string */
    private $homologFilter;
    /** @var  string */
    private $productionFilter;
    /** @var string */
    private $releaseFilter;
    /**
     * @var string
     */
    private $toTestFilter;
    
    /**
     * DefaultFilters constructor.
     * @throws \InvalidArgumentException
     *
     * @param string $configFile
     */
    public function __construct($configFile)
    {
        $jsonConfig = $this->loadConfigFile($configFile);
        $this->config = json_decode($jsonConfig, true);
        $this->jenkinsLabelFieldId = $this->config['jenkinsLabelFieldId'];
        $this->rejectedTransitionId = $this->config['rejectedTransitionId'];
        $this->acceptedTransitionId = $this->config['acceptedTransitionId'];
        $this->deployedTransitionId = $this->config['deployedTransitionId'];
        $this->buildFilters();
    }
    
    /**
     * @param $configFile
     *
     * @throws \InvalidArgumentException
     * @return string
     */
    private function loadConfigFile($configFile)
    {
        $file = __DIR__ . "/../config/$configFile";
        if (!is_file($file)) {
            throw new \InvalidArgumentException(
                "$file.json not found. \n" .
                "You must set a config file for the Jira Client.\n" .
                "(see Integrator\\Jira\\config\\example.json)"
            );
        }
        
        return file_get_contents($file);
    }
    
    /**
     * Build the default JQL string filter
     */
    private function buildFilters()
    {
        $this->buildDefaultFilters();
        $this->doneFilter = $this->filterStatus($this->config['doneStatus']);
        $this->homologFilter = $this->filterStatus($this->config['homologStatus']);
        $this->productionFilter = $this->filterStatus($this->config['productionStatus']);
        $this->releaseFilter = $this->filterStatus($this->config['releaseStatus']);
        $this->toTestFilter = $this->filterStatus($this->config['toTestStatus']);
    }
    
    private function buildDefaultFilters()
    {
        $builder = new QueryBuilder();
        $builder->where($this->makeProjectFilter())
            ->addAnd($this->makeIssueTypeFilter())
            ->addAnd($this->makeExcludeFilters())
            ->addAnd($this->makeRepositoryFilter());
        
        $this->defaultFilter = $builder->getJqlString();
    }
    
    /**
     * @param array $statusArray
     *
     * @return string
     */
    private function filterStatus($statusArray)
    {
        return $this->jqlInArray('status', $statusArray);
    }
    
    /**
     *
     */
    private function makeProjectFilter()
    {
        return $this->jqlInArray('project', $this->config['projects']);
    }
    
    /**
     *
     */
    private function makeIssueTypeFilter()
    {
        return $this->jqlInArray('issuetype', $this->config['issueTypes']);
    }
    
    private function makeExcludeFilters()
    {
        $excludes = $this->config['excludes'];
        $excludeLabels = $this->jqlNotInArray('labels', $excludes['labels']);
        $excludeLabels = "($excludeLabels OR labels is EMPTY)";
        
        return "$excludeLabels";
    }
    
    private function makeRepositoryFilter()
    {
        return $this->jqlEquals('"Repository"', $this->config['repository']);
    }
    
    /**
     * Builds a '$key key in ('value1', 'value2', [...])' JQL string from array of values ['value', 'value2', [...]]
     * @see Filters::jqlFromArray()
     *
     * @param string $key
     * @param array  $arrayOfValues
     *
     * @return string
     */
    private function jqlInArray($key, $arrayOfValues)
    {
        
        return $this->jqlFromArray($key, 'in', $arrayOfValues);
    }
    
    /**
     * @param string $key
     * @param array  $excludes
     *
     * @return string
     */
    private function jqlNotInArray($key, $excludes)
    {
        return $this->jqlFromArray($key, 'not in', $excludes);
    }
    
    private function jqlEquals($field, $value)
    {
        return "$field = $value";
    }
    
    /**
     * Builds a JSQL string with format $key $operator (csv_string($arrayOfValues)).
     * With $key = 'field', $operator = 'in' and  $arrayOfValues = ['value1', 'value2'], it returns the string:
     * field in ('value1','value2')
     *
     * @param string $key
     * @param string $operator IN|NOT IN
     * @param array  $arrayOfValues
     *
     * @return string
     */
    private function jqlFromArray($key, $operator, $arrayOfValues)
    {
        $builder = new QueryBuilder();
        $valuesStr = implode(', ', $arrayOfValues);
        $builder->where("$key $operator ($valuesStr)");
        
        return $builder->getJqlString();
    }
    
    /**
     * @return string
     */
    public function getDefaultFilter()
    {
        return $this->defaultFilter;
    }
    
    /**
     * @param $status
     *
     * @return string
     */
    public function getStatusFilter($status)
    {
        $statusFilter = '';
        switch ($status) {
            case 'doneStatus':
                $statusFilter = $this->getDoneFilter();
                break;
            case 'homologStatus':
                $statusFilter = $this->getHomologFilter();
                break;
            case 'releaseStatus':
                $statusFilter = $this->getReleaseFilter();
                break;
            case 'productionStatus':
                $statusFilter = $this->getProductionFilter();
                break;
        }
        
        return $statusFilter;
    }
    
    public function getDoneFilter()
    {
        return $this->doneFilter;
    }
    
    /**
     * @return string
     */
    public function getHomologFilter()
    {
        return $this->homologFilter;
    }
    
    public function getReleaseFilter()
    {
        return $this->releaseFilter;
    }
    
    public function getJenkinsLabelFieldId()
    {
        return $this->jenkinsLabelFieldId;
    }
    
    private function getProductionFilter()
    {
        return $this->productionFilter;
    }
}
