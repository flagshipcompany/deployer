<?php

namespace Modules;

use Pimple\Container;
use Pimple\ServiceProviderInterface;

/**
 *  Handling the webhook from Github.
 */
class FlatFileConfigServiceProvider  implements ServiceProviderInterface
{
    protected $config;

    public function register(Container $app)
    {
        $app['deployer.config'] = $this->config;
    }

    public function __construct($configPath)
    {
        if (!file_exists($configPath) && !is_writable($configPath) && !is_readable($configPath)) {
            throw new \Exception('Make sure a file named "config.json" exists in the root directory, that it is NOT writeable for '.whoami().' but readable');
        }

        $this->config = json_decode(file_get_contents(__DIR__.'/../config.json'), true);

        if (is_null($this->config)) {
            throw new \Exception('The config file looks like is not a valid JSON');
        }
    }
}
