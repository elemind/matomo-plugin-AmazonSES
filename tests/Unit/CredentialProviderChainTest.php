<?php

/**
 * AmazonSES plugin for Matomo
 *
 * @link    https://github.com/elemind/matomo-plugin-AmazonSES
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\AmazonSES\tests\Unit;

use PHPUnit\Framework\TestCase;
use Piwik\Plugins\AmazonSES\Aws\AwsException;
use Piwik\Plugins\AmazonSES\Aws\Credentials\CredentialProvider;
use Piwik\Plugins\AmazonSES\Aws\Credentials\CredentialProviderChain;
use Piwik\Plugins\AmazonSES\Aws\Credentials\Credentials;
use Piwik\Plugins\AmazonSES\Aws\Credentials\EcsProvider;
use Piwik\Plugins\AmazonSES\Aws\Credentials\EnvProvider;
use Piwik\Plugins\AmazonSES\Aws\Credentials\ImdsProvider;
use Piwik\Plugins\AmazonSES\Aws\Credentials\StaticProvider;
use Piwik\Plugins\AmazonSES\Aws\Http\HttpResponse;
use Piwik\Plugins\AmazonSES\tests\Framework\FakeHttpClient;

/**
 * @group AmazonSES
 * @group Unit
 */
class CredentialProviderChainTest extends TestCase
{
    private function env(array $vars): callable
    {
        return function ($name) use ($vars) {
            return $vars[$name] ?? false;
        };
    }

    private function credentialsJson(string $expiration = '2099-01-01T00:00:00Z'): string
    {
        return json_encode([
            'AccessKeyId' => 'ASIATEMP',
            'SecretAccessKey' => 'tempsecret',
            'Token' => 'temptoken',
            'Expiration' => $expiration,
        ]);
    }

    public function testStaticProviderIgnoresEmptyValues()
    {
        $this->assertNull((new StaticProvider('', 'secret'))->resolve());
        $this->assertNull((new StaticProvider('key', null))->resolve());

        $credentials = (new StaticProvider(' key ', 'secret'))->resolve();
        $this->assertSame('key', $credentials->getAccessKeyId());
        $this->assertSame('settings', $credentials->getSource());
    }

    public function testEnvProvider()
    {
        $this->assertNull((new EnvProvider($this->env([])))->resolve());

        $credentials = (new EnvProvider($this->env([
            'AWS_ACCESS_KEY_ID' => 'AKIA',
            'AWS_SECRET_ACCESS_KEY' => 'secret',
            'AWS_SESSION_TOKEN' => 'token',
        ])))->resolve();

        $this->assertSame('AKIA', $credentials->getAccessKeyId());
        $this->assertSame('token', $credentials->getSessionToken());
        $this->assertSame('env', $credentials->getSource());
    }

    public function testEcsProviderUsesRelativeUri()
    {
        $http = (new FakeHttpClient())->queue(new HttpResponse(200, [], $this->credentialsJson()));
        $provider = new EcsProvider($http, $this->env(['AWS_CONTAINER_CREDENTIALS_RELATIVE_URI' => '/v2/credentials/abc']));

        $credentials = $provider->resolve();

        $this->assertSame('http://169.254.170.2/v2/credentials/abc', $http->requests[0]['url']);
        $this->assertSame('ASIATEMP', $credentials->getAccessKeyId());
        $this->assertSame('temptoken', $credentials->getSessionToken());
        $this->assertSame(4070908800, $credentials->getExpiration());
    }

    public function testEcsProviderFullUriWithAuthorizationToken()
    {
        $http = (new FakeHttpClient())->queue(new HttpResponse(200, [], $this->credentialsJson()));
        $provider = new EcsProvider($http, $this->env([
            'AWS_CONTAINER_CREDENTIALS_FULL_URI' => 'http://169.254.170.23/v1/credentials',
            'AWS_CONTAINER_AUTHORIZATION_TOKEN' => 'secret-token',
        ]));

        $provider->resolve();

        $this->assertSame('http://169.254.170.23/v1/credentials', $http->requests[0]['url']);
        $this->assertSame('secret-token', $http->requests[0]['headers']['Authorization']);
    }

    public function testEcsProviderNotConfigured()
    {
        $this->assertNull((new EcsProvider(new FakeHttpClient(), $this->env([])))->resolve());
    }

    public function testImdsV2Flow()
    {
        $http = (new FakeHttpClient())
            ->queue(new HttpResponse(200, [], 'imds-token'))
            ->queue(new HttpResponse(200, [], "matomo-role\n"))
            ->queue(new HttpResponse(200, [], $this->credentialsJson()));

        $credentials = (new ImdsProvider($http, $this->env([])))->resolve();

        $this->assertSame('PUT', $http->requests[0]['method']);
        $this->assertSame('http://169.254.169.254/latest/api/token', $http->requests[0]['url']);
        $this->assertSame('imds-token', $http->requests[1]['headers']['X-aws-ec2-metadata-token']);
        $this->assertSame('http://169.254.169.254/latest/meta-data/iam/security-credentials/matomo-role', $http->requests[2]['url']);
        $this->assertSame('imds', $credentials->getSource());
    }

    public function testImdsUnreachableReturnsNull()
    {
        $http = (new FakeHttpClient())->queue(new AwsException('timeout'));

        $this->assertNull((new ImdsProvider($http, $this->env([])))->resolve());
    }

    public function testImdsCanBeDisabled()
    {
        $http = new FakeHttpClient();

        $this->assertNull((new ImdsProvider($http, $this->env(['AWS_EC2_METADATA_DISABLED' => 'true'])))->resolve());
        $this->assertEmpty($http->requests);
    }

    public function testChainReturnsFirstMatchAndCaches()
    {
        $counting = new class implements CredentialProvider {
            public $calls = 0;
            public function resolve(): ?Credentials
            {
                $this->calls++;
                return new Credentials('A', 'B', null, null, 'counting');
            }
        };

        $chain = new CredentialProviderChain([new StaticProvider('', ''), $counting]);

        $this->assertSame('counting', $chain->resolve()->getSource());
        $chain->resolve();
        $this->assertSame(1, $counting->calls);
    }

    public function testChainRefreshesExpiringCredentials()
    {
        $now = 1000;
        $provider = new class implements CredentialProvider {
            public $calls = 0;
            public function resolve(): ?Credentials
            {
                $this->calls++;
                return new Credentials('A', 'B', 'T', 1200, 'temp');
            }
        };

        $chain = new CredentialProviderChain([$provider], function () use (&$now) {
            return $now;
        });

        $chain->resolve();
        $chain->resolve();
        $this->assertSame(2, $provider->calls, 'credentials expiring within 5 minutes are not cached');
    }

    public function testResolveOrFailThrowsHelpfulMessage()
    {
        $this->expectException(AwsException::class);
        $this->expectExceptionMessage('No AWS credentials found');

        (new CredentialProviderChain([new StaticProvider(null, null)]))->resolveOrFail();
    }
}
