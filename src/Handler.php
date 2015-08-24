<?php

namespace Flagship\Deployer;

/**
 *  Handling the webhook from Github.
 */
class Handler
{
    protected $targetBranch = 'master';
    protected $payload;
    protected $acceptedPushers = [];
    protected $commands = [];
    protected $env;
    protected $projectPath;
    protected $projectName;
    protected $notifyEmails = [];
    protected $fromEmail = '';

    protected $commandOutputs = [];
    protected $file;
    protected $commitMessages = [];

    public function __construct($config, $env)
    {
        $conf = json_decode($config, true)[$env];

        $this->env = $env;

        $this->targetBranch = $conf['branch'];
        $this->acceptedPushers = $conf['accepted_pushers'];
        $this->commands = $conf['commands'];
        $this->projectName = $conf['project_name'];
        $this->projectPath = $conf['project_path'];
        $this->notifyEmails = $conf['notify_emails'];
        $this->fromEmail = $conf['from_email'];

        putenv('PATH=/sbin:/bin:/usr/sbin:/usr/bin'); //making sure we can find usr/bin

        $this->file = __DIR__.'/'.__FILE__;
    }

    public function run()
    {
        if (strpos($_SERVER['HTTP_USER_AGENT'], 'GitHub-Hookshot') === false) {
            return; //It's not a webhook request, disregard and carry on!
        }

        $this->checkPrerequisites();
        $this->runCommands();
        $this->makeCommitMessages();
        $this->sendEmail();

        die; //we're done, no need to run the rest of the app.
    }

    protected function makeCommitMessages()
    {
        foreach ($this->payload['commits'] as $commit) {
            $msg = '<a href="mailto:'.$commit['author']['email'].'">'.$commit['author']['name'].'</a>:<br />';
            $msg .= $commit['message'].'<br />';
            $msg .= 'commit: <a href="'.$commit['url'].'">'.$commit['id'].'</a>';
            $this->commitMessages[] = $msg;
        }
    }

    protected function sendEmail()
    {
        $headers = "From: {$this->fromEmail}\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";

        $body = "<html><body><b><a href=\"{$this->payload['compare']}\">Compare</a> with previous release</b><br><br>";
        $body .= '<b>CHANGE LOG</b><br /><br />';
        $body .= implode('<hr/>', $this->commitMessages);
        $body .= '<hr/><b>COMMAND RESULTS</b><br /><br />';
        $body .= '<pre>';
        $body .= implode("\n", $this->commandOutputs);
        $body .= '</pre><br /></body></html>';

        mail(implode(',', $this->notifyEmails), strtoupper($this->env)." RELEASE - {$this->projectName}", $body, $headers);
    }

    protected function runCommands()
    {
        chdir($this->projectPath);

        exec("git fetch origin && git reset --hard origin/$this->targetBranch", $this->commandOutputs, $exitCode);
        echo $exitCode;

        foreach ($this->commands as $command) {
            exec($command, $this->commandOutputs, $exitCode);
            echo "$command -- $exitCode";
        }
    }

    protected function checkPrerequisites()
    {
        $this->payload = json_decode($_POST['payload'], true);

        if ($this->payload === null) {
            http_response_code(500);
            $message = 'got hit with a github deploy hook but payload is empty or is unreadable';
            $this->failToMessage($message);
        }

        if (!isset($_POST['secret']) || $this->secret != $_POST['secret']) {
            http_response_code(403);
            $message = 'got hit with a github deploy hook but there is no secret or the secret do not match';
            $this->failToMessage($message);
        }

        if ($this->targetBranch != explode('/', $this->payload['ref'])[2]) {
            http_response_code(404);
            $message = 'Will not deploy because target branch == '.explode('/', $this->payload['ref'])[2]."while $targetBranch was expected";
            $this->failToMessage($message);
        }

        if (count($this->acceptedPushers) > 0 && !in_array(strtolower($this->payload['pusher']['email']), $this->acceptedPushers)) {
            http_response_code(403);
            $message = strtolower($this->payload['pusher']['email']).' is not in the list of accepted pushers ( '.implode(', ', $this->acceptedPushers).')';
            $this->failToMessage($message);
        }
    }

    protected function failToMessage($message)
    {
        echo $this->file.' '.$message;
        error_log($this->file.' '.$message);
    }
}
