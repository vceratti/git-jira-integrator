<?php

namespace Integrator\Git;

class Output
{
    /** @var  string */
    public $outputLabel;
    /** @var  string */
    public $outputText;
    /** @var string */
    public $executedCommand;
    /** @var string ok|error */
    public $status;
    private $labels = [
        'not something we can merge' => 'INVALID_BRANCH',
        'conflict' => 'CONFLICT',
        'merge made' => 'MERGED',
        'already up-to-date' => 'MERGED'
    ];
    
    public function __construct($output, $command)
    {
        $this->executedCommand = $command;
        
        if (is_array($output)) {
            $output = implode("\n", $output);
        }
        
        $this->outputText = $output;
        $this->parseOutput();
    }
    
    private function parseOutput()
    {
        foreach ($this->labels as $pattern => $label) {
            if ($this->containsText($pattern)) {
                $this->outputLabel = $label;
            }
        }
    }
    
    private function containsText($pattern)
    {
        $match = false;
        if ($this->textIncludePattern($pattern)) {
            $match = true;
        }
        
        return $match;
    }
    
    private function textIncludePattern($pattern)
    {
        return preg_match("/$pattern/si", $this->outputText) > 0;
    }
}
