<?php

/**
 * AmazonSES plugin for Matomo
 *
 * @link    https://github.com/elemind/matomo-plugin-AmazonSES
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\AmazonSES;

use Piwik\Config;
use Piwik\Plugins\AmazonSES\Aws\Credentials\CredentialProviderChain;
use Piwik\Plugins\AmazonSES\Aws\Credentials\EcsProvider;
use Piwik\Plugins\AmazonSES\Aws\Credentials\EnvProvider;
use Piwik\Plugins\AmazonSES\Aws\Credentials\ImdsProvider;
use Piwik\Plugins\AmazonSES\Aws\Credentials\StaticProvider;
use Piwik\Plugins\AmazonSES\Aws\Http\CurlHttpClient;
use Piwik\Plugins\AmazonSES\Aws\Http\HttpClient;
use Piwik\Plugins\AmazonSES\Aws\SesV2Client;
use Piwik\Plugins\AmazonSES\Settings\ConfigReader;

class SesClientFactory
{
    /** @var ConfigReader */
    private $config;

    /** @var CredentialProviderChain|null */
    private $credentials;

    public function __construct(ConfigReader $config)
    {
        $this->config = $config;
    }

    public function getConfig(): ConfigReader
    {
        return $this->config;
    }

    public function createClient(?HttpClient $http = null): SesV2Client
    {
        $http = $http ?: new CurlHttpClient($this->config->getTimeout(), $this->getMatomoProxy());

        return new SesV2Client(
            $this->getCredentialChain(),
            $http,
            $this->config->getRegion(),
            $this->config->getEndpoint(),
            $this->config->allowsInsecureEndpoint()
        );
    }

    public function getCredentialChain(): CredentialProviderChain
    {
        if ($this->credentials === null) {
            // metadata endpoints are link-local: never route them through the Matomo HTTP proxy
            $metadataHttp = new CurlHttpClient(2.0);

            $this->credentials = new CredentialProviderChain([
                new StaticProvider($this->config->getAccessKeyId(), $this->config->getSecretAccessKey()),
                new EnvProvider(),
                new EcsProvider($metadataHttp),
                new ImdsProvider($metadataHttp),
            ]);
        }

        return $this->credentials;
    }

    /**
     * @return array{host?: string, port?: string|int, username?: string, password?: string}
     */
    private function getMatomoProxy(): array
    {
        $proxy = Config::getInstance()->proxy;

        return is_array($proxy) ? $proxy : [];
    }
}
