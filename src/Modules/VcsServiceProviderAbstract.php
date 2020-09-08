<?php

namespace Modules;

use Pimple\Container;
use Pimple\ServiceProviderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 *  Handling the webhook from Github.
 */
abstract class VcsServiceProviderAbstract implements ServiceProviderInterface
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
    protected $commitMessages = [];

    protected $tagCommand = 'git checkout $(git describe --tags `git rev-list --tags --max-count=1`)';

    protected $app;

    public function register(Container $app)
    {
    }
    public function __construct()
    {
    }

    public function run(Request $request, $config)
    {
        putenv('PATH=/usr/local/src/nvm/versions/node/v8.17.0/bin:/sbin:/bin:/usr/sbin:/usr/bin:$PATH'); //making sure we can find usr/bin and thus the programs.

        $this->env = $request->get('env');
        $this->parseConfig($config);

        $preReqs = $this->checkPrerequisites($request);
        if (!is_null($preReqs)) {
            return $preReqs;
        }
        $this->runCommands();
        $this->makeCommitMessages();

        if (count($this->notifyEmails) <= 0) {
            return new Response('OK', 200);
        }

        if (!$this->sendEmail()) {
            return new Response($this->resultContent, 500);
        }

        return new Response('OK', 200);
    }

    protected function parseConfig($conf)
    {
        $this->targetBranch = $conf['branch'];
        $this->tagsOnly = isset($conf['tags_only']) && !!$conf['tags_only'];
        $this->acceptedPushers = $conf['accepted_pushers'];
        $this->commands = $conf['commands'];
        $this->projectName = $conf['project_name'];
        $this->projectPath = $conf['project_path'];
        $this->notifyEmails = $conf['notify_emails'];
        $this->fromEmail = $conf['from_email'];
        $this->secret = $conf['secret'];
    }

    protected function makeCommitMessages()
    {
        foreach ($this->payload['commits'] as $commit) {
            $msg = "<a href=\"mailto:{$commit['author']['email']}\">{$commit['author']['name']}</a> committed:<br />";
            $msg .= "<pre>{$commit['message']}</pre>";
            $msg .= "<i>See <a href=\"{$commit['url']}\">commit</a> on GitHub.</i>";
            $this->commitMessages[] = $msg;
        }
    }

    protected function sendEmail()
    {
        $headers = "From: {$this->fromEmail}\r\n";
        $headers .= "MIME-Version: 1.0\r\n";
        $headers .= "Content-Type: text/html; charset=UTF-8\r\n";

        $body = '<html><body>';
        $body .= '<b>CHANGE LOG:</b><br />';
        $body .= implode('<hr/>', $this->commitMessages);
        $body .= "<hr /><b><a href=\"{$this->payload['compare']}\">Compare</a> with previous release</b>";
        $body .= '<hr/><b>COMMAND RESULTS</b><br />';
        $body .= '<pre>';
        $body .= implode("\n", $this->commandOutputs);
        $body .= '</pre><br /></body></html>';

        $this->resultContent = $body;

        $isForced = ($this->payload['forced'] ? '--FORCED-- ' : '');

        return mail(implode(',', $this->notifyEmails), strtoupper($this->env)." RELEASE $isForced- {$this->projectName}", $body, $headers);
    }

    protected function runCommands()
    {
        chdir($this->projectPath);

        if ($this->tagsOnly) {
            array_unshift($this->commands, $this->tagCommand);
        }
        array_unshift($this->commands, "git fetch origin && git reset --hard origin/$this->targetBranch");

        foreach ($this->commands as $command) {
            $this->commandOutputs[] = $command.':';
            exec($command, $this->commandOutputs, $exitCode);
            $this->commandOutputs[] = 'Exit code: '.($exitCode === 0 ? 'OK' : $exitCode);
            $this->commandOutputs[] = '==============================';
        }
    }

    abstract protected function checkPrerequisites(Request $request);
}
