<?php

/**
 * AmazonSES plugin for Matomo
 *
 * @link    https://github.com/elemind/matomo-plugin-AmazonSES
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\AmazonSES\Aws\Credentials;

use Piwik\Plugins\AmazonSES\Aws\AwsException;
use Piwik\Plugins\AmazonSES\Aws\Http\HttpClient;

/**
 * EC2 instance profile credentials via IMDSv2 (session token required).
 */
class ImdsProvider implements CredentialProvider
{
    public const DEFAULT_ENDPOINT = 'http://169.254.169.254';

    /** @var HttpClient */
    private $http;

    /** @var callable */
    private $getenv;

    public function __construct(HttpClient $http, ?callable $getenv = null)
    {
        $this->http = $http;
        $this->getenv = $getenv ?: 'getenv';
    }

    public function resolve(): ?Credentials
    {
        if (strtolower((string) call_user_func($this->getenv, 'AWS_EC2_METADATA_DISABLED')) === 'true') {
            return null;
        }

        $endpoint = rtrim((string) call_user_func($this->getenv, 'AWS_EC2_METADATA_SERVICE_ENDPOINT'), '/');
        if ($endpoint === '') {
            $endpoint = self::DEFAULT_ENDPOINT;
        }

        try {
            $tokenResponse = $this->http->request(
                'PUT',
                $endpoint . '/latest/api/token',
                ['X-aws-ec2-metadata-token-ttl-seconds' => '21600'],
                '',
                1.0
            );
        } catch (AwsException $e) {
            // not running on EC2 (or IMDS unreachable): this provider simply does not apply
            return null;
        }

        if (!$tokenResponse->isSuccessful()) {
            return null;
        }

        $headers = ['X-aws-ec2-metadata-token' => trim($tokenResponse->getBody())];
        $base = $endpoint . '/latest/meta-data/iam/security-credentials/';

        $roleResponse = $this->http->request('GET', $base, $headers, '', 1.0);
        if (!$roleResponse->isSuccessful()) {
            // instance without an attached instance profile
            return null;
        }

        $role = trim(strtok($roleResponse->getBody(), "\n"));
        if ($role === '') {
            return null;
        }

        $credsResponse = $this->http->request('GET', $base . rawurlencode($role), $headers, '', 1.0);
        if (!$credsResponse->isSuccessful()) {
            throw new AwsException(sprintf('EC2 instance metadata returned HTTP %d for role "%s".', $credsResponse->getStatus(), $role));
        }

        return TemporaryCredentialsParser::parse($credsResponse->getBody(), 'imds');
    }
}
