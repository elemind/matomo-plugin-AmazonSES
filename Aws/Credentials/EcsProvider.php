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
 * ECS / Fargate task role credentials (container credentials endpoint).
 */
class EcsProvider implements CredentialProvider
{
    public const ECS_HOST = 'http://169.254.170.2';

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
        $relative = (string) call_user_func($this->getenv, 'AWS_CONTAINER_CREDENTIALS_RELATIVE_URI');
        $full = (string) call_user_func($this->getenv, 'AWS_CONTAINER_CREDENTIALS_FULL_URI');

        if ($relative !== '') {
            $url = self::ECS_HOST . $relative;
        } elseif ($full !== '') {
            $url = $full;
        } else {
            return null;
        }

        $headers = [];
        $token = $this->getAuthorizationToken();
        if ($token !== '') {
            $headers['Authorization'] = $token;
        }

        $response = $this->http->request('GET', $url, $headers, '', 2.0);
        if (!$response->isSuccessful()) {
            throw new AwsException(sprintf('ECS credentials endpoint returned HTTP %d.', $response->getStatus()), '', $response->getStatus());
        }

        return TemporaryCredentialsParser::parse($response->getBody(), 'ecs');
    }

    private function getAuthorizationToken(): string
    {
        $file = (string) call_user_func($this->getenv, 'AWS_CONTAINER_AUTHORIZATION_TOKEN_FILE');
        if ($file !== '' && is_readable($file)) {
            return trim((string) file_get_contents($file));
        }

        return trim((string) call_user_func($this->getenv, 'AWS_CONTAINER_AUTHORIZATION_TOKEN'));
    }
}
