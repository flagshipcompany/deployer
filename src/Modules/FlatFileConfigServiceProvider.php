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
    protected $configPath;

    public function register(Container $app)
    {
        $app['deployer.config'] = function () {
            if (!file_exists($this->configPath) && !is_writable($this->configPath) && !is_readable($this->configPath)) {
                throw new \Exception('Make sure a file named "config.json" exists in the root directory, that it is NOT writeable for '.exec('whoami').' but readable');
            }

            $this->config = json_decode(file_get_contents($this->configPath), true);

            if (is_null($this->config)) {
                throw new \Exception('The config file looks like is not a valid JSON');
            }

            return $this->config;
        };
    }

    public function __construct($configPath)
    {
        $this->configPath = $configPath;
    }
}
