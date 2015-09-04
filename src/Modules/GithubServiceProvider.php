<?php

namespace Modules;

use Pimple\Container;
use Pimple\ServiceProviderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 *  Handling the webhook from Github.
 */
class GithubServiceProvider  implements ServiceProviderInterface
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

    protected $app;

    public function register(Container $app)
    {
        $this->app = $app;

        $app['deployer.vcs_service'] = $this;
    }
    public function __construct()
    {
    }

    public function run(Request $request)
    {
        putenv('PATH=/sbin:/bin:/usr/sbin:/usr/bin'); //making sure we can find usr/bin and thus the programs.


        $this->parseConfig($request);

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

    protected function parseConfig(Request $request)
    {
        $project = $request->attributes->get('project');
        $this->env = $request->attributes->get('env');
        $conf = $this->app['deployer.config'][$project][$this->env];

        $this->targetBranch = $conf['branch'];
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

        array_unshift($this->commands, "git fetch origin && git reset --hard origin/$this->targetBranch");

        foreach ($this->commands as $command) {
            $this->commandOutputs[] = $command.':';
            exec($command, $this->commandOutputs, $exitCode);
            $this->commandOutputs[] = 'Exit code: '.($exitCode === 0 ? 'OK' : $exitCode);
            $this->commandOutputs[] = '==============================';
        }
    }

    protected function checkPrerequisites(Request $request)
    {
        if (strpos($request->headers->get('USER_AGENT'), 'GitHub-Hookshot') === false) {
            return new Response('Not a github webhook', 400);
        }

        $hash = substr($request->headers->get('X_HUB_SIGNATURE'), 5);
        $cmpHash = hash_hmac('sha1', $request->getContent(), $this->secret);

        if (!$hash === $cmpHash) {
            return new Response("$hash is not equal to expected hash $cmpHash", 400);
        }

        $this->payload = json_decode($request->request->get('payload'), true);

        if ($this->payload === null) {
            return new Response('Got hit with a github deploy hook but payload is empty or is unreadable', 400);
        }

        if ($this->targetBranch != explode('/', $this->payload['ref'])[2]) {
            return new Response('Will not deploy because target branch == '.explode('/', $this->payload['ref'])[2]." while $targetBranch was expected", 400);
        }

        if (count($this->acceptedPushers) > 0 && !in_array(strtolower($this->payload['pusher']['email']), $this->acceptedPushers)) {
            return new Response(strtolower($this->payload['pusher']['email']).' is not in the list of accepted pushers ( '.implode(', ', $this->acceptedPushers).')', 403);
        }
    }
}
