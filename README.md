# README #


### What is this repository for? ###

PHP simple API for working with Git and Jira Issues.

Assuiming you use Jira Issue Keys for branching name, you can: 
- set a "dev" branch, get the 'Done' tasks and merge them to an homolog enviroment for testing.
 
- set a "marked for release" rule, when issues are approved in homolog, then get those tasks and create a new release in confugured "release branch".
 
- set a "production" branch and deploy a new release to it, moving the issues to its final status.

There is a lot work to do on this read me, like reviewing the example files and the criteria used for each available task.

### Config ###

Frist create **.env**  file in project root following _.env.example_:

Then, set up a json file on **Integrator/Git/config**, following example.json model, describing:

 @TODO: update example with new fields, from the used config files.

Next, we must set filters for Issues in each integration task.
 
 @TODO: update example with new fields, from the used config files.
  

### How to Use ###

index.php contains the template for using the available tasks:
 
You must create and instance of the integrator with the "task" name, which is the same of the JSON config files.
 
- $integrator = new Integrator\GitJiraIntegrator('repository_done'); --> will use respository_done.json
  
Then: 

- $integrator->integrateDoneTasks(); get the "Done" tasks and try to merge them, putting the result (like MERGED or CONFLICTS) in a Jira custom label field
- $integrator->integrateReleaseTasks(); get the "Marked for Release" tasks and merge them into a release branch, updating Jira issues
- $integrator->integrateReleaseTasks(); get the "Release" tasks and merge them into master, moving Jira issues


### Who do I talk to? ###
 
 Vinícius Ceratti
 
 v.ceratti@gmail.com
