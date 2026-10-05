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
use Piwik\Plugins\AmazonSES\Aws\Credentials\CredentialProviderChain;
use Piwik\Plugins\AmazonSES\Aws\Credentials\StaticProvider;
use Piwik\Plugins\AmazonSES\Aws\Http\HttpResponse;
use Piwik\Plugins\AmazonSES\Aws\SesV2Client;
use Piwik\Plugins\AmazonSES\tests\Framework\FakeHttpClient;

/**
 * @group AmazonSES
 * @group Unit
 */
class SesV2ClientTest extends TestCase
{
    /** @var FakeHttpClient */
    private $http;

    public function setUp(): void
    {
        parent::setUp();
        $this->http = new FakeHttpClient();
    }

    private function client(?string $endpoint = null, bool $allowInsecureEndpoint = false): SesV2Client
    {
        return new SesV2Client(
            new CredentialProviderChain([new StaticProvider('AKIDEXAMPLE', 'secret')]),
            $this->http,
            'eu-west-1',
            $endpoint,
            $allowInsecureEndpoint
        );
    }

    public function testSendRawEmailBuildsSignedRequest()
    {
        $this->http->queue(new HttpResponse(200, [], '{"MessageId":"0102-abc"}'));

        $id = $this->client()->sendRawEmail(
            "Subject: hi\r\n\r\nbody",
            ['a@example.com'],
            ['hidden@example.com'],
            'noreply@example.com',
            'matomo-set'
        );

        $this->assertSame('0102-abc', $id);

        $request = $this->http->requests[0];
        $this->assertSame('POST', $request['method']);
        $this->assertSame('https://email.eu-west-1.amazonaws.com/v2/email/outbound-emails', $request['url']);
        $this->assertStringStartsWith('AWS4-HMAC-SHA256 Credential=AKIDEXAMPLE/', $request['headers']['Authorization']);
        $this->assertStringContainsString('/eu-west-1/ses/aws4_request', $request['headers']['Authorization']);

        $payload = json_decode($request['body'], true);
        $this->assertSame("Subject: hi\r\n\r\nbody", base64_decode($payload['Content']['Raw']['Data']));
        $this->assertSame(['a@example.com'], $payload['Destination']['ToAddresses']);
        $this->assertSame(['hidden@example.com'], $payload['Destination']['BccAddresses']);
        $this->assertSame('noreply@example.com', $payload['FromEmailAddress']);
        $this->assertSame('matomo-set', $payload['ConfigurationSetName']);
    }

    public function testOptionalFieldsAreOmitted()
    {
        $this->http->queue(new HttpResponse(200, [], '{"MessageId":"x"}'));

        $this->client()->sendRawEmail('raw', ['a@example.com']);

        $payload = json_decode($this->http->requests[0]['body'], true);
        $this->assertArrayNotHasKey('BccAddresses', $payload['Destination']);
        $this->assertArrayNotHasKey('FromEmailAddress', $payload);
        $this->assertArrayNotHasKey('ConfigurationSetName', $payload);
    }

    public function testCustomEndpoint()
    {
        $this->http->queue(new HttpResponse(200, [], '{"MessageId":"x"}'));

        $client = $this->client('https://vpce-1.email.eu-west-1.vpce.amazonaws.com:8443/prefix/');
        $client->sendRawEmail('raw', ['a@example.com']);

        $this->assertTrue($client->isEndpointEncrypted());
        $this->assertSame(
            'https://vpce-1.email.eu-west-1.vpce.amazonaws.com:8443/prefix/v2/email/outbound-emails',
            $this->http->requests[0]['url']
        );
    }

    public function testPlainHttpEndpointIsRejectedByDefault()
    {
        try {
            $this->client('http://ses-mock:8005');
            $this->fail('Expected exception');
        } catch (AwsException $e) {
            $this->assertSame('InvalidEndpoint', $e->getAwsErrorCode());
            $this->assertStringContainsString('allowInsecureEndpoint', $e->getMessage());
            $this->assertStringNotContainsString('ses-mock', $e->getMessage());
        }
    }

    public function testPlainHttpEndpointCanBeAllowedExplicitly()
    {
        $this->http->queue(new HttpResponse(200, [], '{"MessageId":"x"}'));

        $client = $this->client('http://ses-mock:8005/', true);
        $client->sendRawEmail('raw', ['a@example.com']);

        $this->assertFalse($client->isEndpointEncrypted());
        $this->assertSame('http://ses-mock:8005/v2/email/outbound-emails', $this->http->requests[0]['url']);
    }

    public function provideInvalidEndpoints(): array
    {
        return [
            'credentials' => ['https://AKIA:s3cr3t@ses.example.com'],
            'user only' => ['https://s3cr3t@ses.example.com'],
            'query' => ['https://ses.example.com/?s3cr3t=1'],
            'fragment' => ['https://ses.example.com/#s3cr3t'],
            'no scheme' => ['ses.example.com/s3cr3t'],
            'no host' => ['https:///s3cr3t'],
            'other scheme' => ['ftp://ses.example.com/s3cr3t'],
            'file' => ['file:///etc/s3cr3t'],
        ];
    }

    /**
     * @dataProvider provideInvalidEndpoints
     */
    public function testInvalidEndpointIsRejectedWithoutEchoingIt(string $endpoint)
    {
        try {
            $this->client($endpoint, true);
            $this->fail('Expected exception');
        } catch (AwsException $e) {
            $this->assertSame('InvalidEndpoint', $e->getAwsErrorCode());
            $this->assertStringNotContainsString('s3cr3t', $e->getMessage());
        }
    }

    public function testAwsErrorIsMappedToException()
    {
        $this->http->queue(new HttpResponse(
            400,
            ['x-amzn-ErrorType' => 'MessageRejected:http://internal.amazon.com/coral/com.amazonaws.sesv2/'],
            '{"message":"Email address is not verified. The following identities failed the check in region EU-WEST-1: noreply@example.com"}'
        ));

        try {
            $this->client()->sendRawEmail('raw', ['a@example.com']);
            $this->fail('Expected exception');
        } catch (AwsException $e) {
            $this->assertSame('MessageRejected', $e->getAwsErrorCode());
            $this->assertSame(400, $e->getHttpStatus());
            $this->assertStringContainsString('Email address is not verified', $e->getMessage());
            $this->assertStringContainsString('HTTP 400, MessageRejected', $e->getMessage());
        }
    }

    public function testErrorCodeFromJsonType()
    {
        $this->http->queue(new HttpResponse(403, [], '{"__type":"com.amazon.coral.service#UnrecognizedClientException","message":"The security token included in the request is invalid."}'));

        try {
            $this->client()->sendRawEmail('raw', ['a@example.com']);
            $this->fail('Expected exception');
        } catch (AwsException $e) {
            $this->assertSame('UnrecognizedClientException', $e->getAwsErrorCode());
        }
    }

    public function testInvalidRegionIsRejected()
    {
        $this->expectException(AwsException::class);

        new SesV2Client(new CredentialProviderChain([]), $this->http, 'eu-west-1.evil.com');
    }

    public function testMissingMessageIdIsAnError()
    {
        $this->http->queue(new HttpResponse(200, [], '{}'));
        $this->expectException(AwsException::class);

        $this->client()->sendRawEmail('raw', ['a@example.com']);
    }
}
