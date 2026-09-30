<?php

/**
 * AmazonSES plugin for Matomo
 *
 * @link    https://github.com/elemind/matomo-plugin-AmazonSES
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\AmazonSES\tests\Unit;

use PHPUnit\Framework\TestCase;
use Piwik\Plugins\AmazonSES\Aws\Credentials\Credentials;
use Piwik\Plugins\AmazonSES\Aws\SigV4Signer;

/**
 * Vectors from the AWS SigV4 test suite and the IAM documentation example.
 *
 * @group AmazonSES
 * @group Unit
 */
class SigV4SignerTest extends TestCase
{
    private const KEY = 'AKIDEXAMPLE';
    private const SECRET = 'wJalrXUtnFEMI/K7MDENG+bPxRfiCYEXAMPLEKEY';

    private function now(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('2015-08-30T12:36:00Z');
    }

    public function testGetVanilla()
    {
        $signer = new SigV4Signer('service', 'us-east-1');
        $headers = $signer->sign('GET', 'https://example.amazonaws.com/', [], '', new Credentials(self::KEY, self::SECRET), $this->now());

        $this->assertSame('20150830T123600Z', $headers['X-Amz-Date']);
        $this->assertSame('example.amazonaws.com', $headers['Host']);
        $this->assertSame(
            'AWS4-HMAC-SHA256 Credential=AKIDEXAMPLE/20150830/us-east-1/service/aws4_request, SignedHeaders=host;x-amz-date, '
            . 'Signature=5fa00fa31553b73ebf1942676e86291e8372ff2a2260956d9b8aae1d763fbf31',
            $headers['Authorization']
        );
    }

    public function testPostVanilla()
    {
        $signer = new SigV4Signer('service', 'us-east-1');
        $headers = $signer->sign('POST', 'https://example.amazonaws.com/', [], '', new Credentials(self::KEY, self::SECRET), $this->now());

        $this->assertStringEndsWith(
            'Signature=5da7c1a2acd57cee7505fc6676e4e544621c30862966e37dddb68e92efbe5d6b',
            $headers['Authorization']
        );
    }

    public function testIamDocumentationExampleWithQueryString()
    {
        $signer = new SigV4Signer('iam', 'us-east-1');
        $headers = $signer->sign(
            'GET',
            'https://iam.amazonaws.com/?Version=2010-05-08&Action=ListUsers',
            ['Content-Type' => 'application/x-www-form-urlencoded; charset=utf-8'],
            '',
            new Credentials(self::KEY, self::SECRET),
            $this->now()
        );

        $this->assertSame(
            'AWS4-HMAC-SHA256 Credential=AKIDEXAMPLE/20150830/us-east-1/iam/aws4_request, '
            . 'SignedHeaders=content-type;host;x-amz-date, '
            . 'Signature=5d672d79c15b13162d9279b0855cfba6789a8edb4c82c400e06b5924a6f2b5d7',
            $headers['Authorization']
        );
    }

    public function testSessionTokenIsAddedAndSigned()
    {
        $signer = new SigV4Signer('ses', 'eu-west-1');
        $headers = $signer->sign(
            'POST',
            'https://email.eu-west-1.amazonaws.com/v2/email/outbound-emails',
            ['Content-Type' => 'application/json'],
            '{}',
            new Credentials(self::KEY, self::SECRET, 'TOKEN123'),
            $this->now()
        );

        $this->assertSame('TOKEN123', $headers['X-Amz-Security-Token']);
        $this->assertStringContainsString('SignedHeaders=content-type;host;x-amz-date;x-amz-security-token,', $headers['Authorization']);
        $this->assertStringContainsString('/20150830/eu-west-1/ses/aws4_request', $headers['Authorization']);
    }

    public function testNonDefaultPortIsPartOfHostHeader()
    {
        $signer = new SigV4Signer('ses', 'eu-west-1');
        $headers = $signer->sign('GET', 'http://ses-mock:8005/v2/email/account', [], '', new Credentials(self::KEY, self::SECRET), $this->now());

        $this->assertSame('ses-mock:8005', $headers['Host']);
    }

    public function testSignatureIsDeterministicAndDependsOnBody()
    {
        $signer = new SigV4Signer('ses', 'eu-west-1');
        $credentials = new Credentials(self::KEY, self::SECRET);
        $url = 'https://email.eu-west-1.amazonaws.com/v2/email/outbound-emails';

        $a = $signer->sign('POST', $url, [], 'a', $credentials, $this->now());
        $b = $signer->sign('POST', $url, [], 'a', $credentials, $this->now());
        $c = $signer->sign('POST', $url, [], 'b', $credentials, $this->now());

        $this->assertSame($a['Authorization'], $b['Authorization']);
        $this->assertNotSame($a['Authorization'], $c['Authorization']);
    }
}
