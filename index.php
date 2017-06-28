<?php

require_once 'vendor/autoload.php';

$loader = new \Aura\Autoload\Loader;
$loader->register();
$loader->addPrefix('Integrator', __DIR__ . '/Integrator');

try {
    echo "\n------ repository_done ---------------\n";
    $integrator = new Integrator\GitJiraIntegrator('repository_done');
    $integrator->integrateDoneTasks();
    echo "\n-------------------------------------\n";
    
    echo "\n------ repository_release -------\n";
    $integratorRelease = new Integrator\GitJiraIntegrator('repository_release');
    $integratorRelease->integrateReleaseTasks();
    echo "\n------------------------------------------------\n";
    
    echo "\n------ repository_master --------\n";
    $integrator = new Integrator\GitJiraIntegrator('repository_master');
    $integrator->deployLatestRelease();
    
    echo "\n--------------------------------------\n";
} catch (\Exception $exception) {
    echo $exception->getMessage();
}
