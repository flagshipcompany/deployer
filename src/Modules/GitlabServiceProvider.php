<?php

namespace Modules;

use Pimple\Container;
use Pimple\ServiceProviderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 *  Handling the webhook from Gitlab.
 */
class GitlabServiceProvider extends VcsServiceProviderAbstract
{
    public function register(Container $app)
    {
        $this->app = $app;

        $app['deployer.vcs_service.gitlab'] = $this;
    }
    public function __construct()
    {
    }

    protected function makeCommitMessages()
    {
        foreach ($this->payload['commits'] as $commit) {
            $msg = "<a href=\"mailto:{$commit['author']['email']}\">{$commit['author']['name']}</a> committed:<br />";
            $msg .= "<pre>{$commit['message']}</pre>";
            $msg .= "<i>See <a href=\"{$commit['url']}\">commit</a> on Gitlab.</i>";
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
        $body .= '<br /><br /><b>COMMAND RESULTS</b><br />';
        $body .= '<pre>';
        $body .= implode("\n", $this->commandOutputs);
        $body .= '</pre><br /></body></html>';

        $this->resultContent = $body;

        $isForced = (!empty($this->payload['forced']) ? '--FORCED-- ' : '');

        return mail(implode(',', $this->notifyEmails), strtoupper($this->env)." RELEASE $isForced- {$this->projectName}", $body, $headers);
    }


    protected function checkPrerequisites(Request $request)
    {
        if ($request->headers->get('x-gitlab-token') !== $this->secret) {
            return new Response("The submitted token is not the expected one.", 400);
        }

        $this->payload = json_decode($request->getContent(), true);

        if ($this->payload === null) {
            return new Response('Got hit with a Gitlab Push hook but payload is empty or is unreadable', 400);
        }

        if ($this->tagsOnly && $this->payload['object_kind'] != 'tag_push' && $this->targetBranch != explode('/', $this->payload['base_ref'])[2]) {
            return new Response('Will not deploy because NOT a TAG push or the tag push does not target '.explode('/', $this->payload['ref'])[2]." while $targetBranch was expected", 400);
        }

        if (!$this->tagsOnly && $this->targetBranch != explode('/', $this->payload['ref'])[2]) {
            return new Response('Will not deploy because target branch == '.explode('/', $this->payload['ref'])[2]." while $this->targetBranch was expected", 400);
        }

        if (count($this->acceptedPushers) > 0 && !in_array(strtolower($this->payload['user_email']), $this->acceptedPushers)) {
            return new Response(strtolower($this->payload['user_email']).' is not in the list of accepted pushers ( '.implode(', ', $this->acceptedPushers).')', 403);
        }
    }
}
