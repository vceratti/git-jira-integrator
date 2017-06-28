<?php

namespace Integrator\Jira;

/**
 * Class Issue
 * Default Issue model Jira taks
 */
class Issue
{
    /** Issue Key from Jira
     * @var string
     */
    public $key;
    /** Description
     * @var string
     */
    public $summary;
    /** Task type, like "Bug", "Task"
     * @var string
     */
    public $type;
    /**  Task priority (check the number order)
     * @var int
     */
    public $priority;
    /**  Set of Labels assigned for issue
     * @var array
     */
    public $labels;
    /** Project key
     * @var string
     */
    public $project;
    /** Display name from assignee
     * @var string
     */
    public $assigneName;
    /** Asiggne email
     * @var string
     */
    public $assigneEmail;
    /** Name of the Epic which task is from
     * @var string
     */
    public $epic;
    /** Status, like "Done", "To Do"
     * @var string
     */
    public $status;
    
    /**
     * @param string $issueKey
     */
    public function __construct($issueKey)
    {
        $this->key = $issueKey;
    }
}
