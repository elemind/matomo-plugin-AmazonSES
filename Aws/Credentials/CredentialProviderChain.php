<?php

/**
 * AmazonSES plugin for Matomo
 *
 * @link    https://github.com/elemind/matomo-plugin-AmazonSES
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\AmazonSES\Aws\Credentials;

use Piwik\Plugins\AmazonSES\Aws\AwsException;

/**
 * Tries each provider in order and caches the first credentials found until they (almost) expire.
 */
class CredentialProviderChain implements CredentialProvider
{
    /** @var CredentialProvider[] */
    private $providers;

    /** @var Credentials|null */
    private $cached;

    /** @var callable */
    private $clock;

    /**
     * @param CredentialProvider[] $providers
     */
    public function __construct(array $providers, ?callable $clock = null)
    {
        $this->providers = $providers;
        $this->clock = $clock ?: 'time';
    }

    public function resolve(): ?Credentials
    {
        if ($this->cached !== null && !$this->cached->isExpired((int) call_user_func($this->clock))) {
            return $this->cached;
        }

        foreach ($this->providers as $provider) {
            $credentials = $provider->resolve();
            if ($credentials !== null) {
                return $this->cached = $credentials;
            }
        }

        return null;
    }

    /**
     * @throws AwsException when no provider returns credentials
     */
    public function resolveOrFail(): Credentials
    {
        $credentials = $this->resolve();
        if ($credentials === null) {
            throw new AwsException(
                'No AWS credentials found. Configure an access key in the AmazonSES settings, set the AWS_ACCESS_KEY_ID / '
                . 'AWS_SECRET_ACCESS_KEY environment variables, or attach an IAM role (ECS task role or EC2 instance profile).',
                'MissingCredentials'
            );
        }

        return $credentials;
    }
}
