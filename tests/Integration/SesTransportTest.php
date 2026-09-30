<?php

/**
 * AmazonSES plugin for Matomo
 *
 * @link    https://github.com/elemind/matomo-plugin-AmazonSES
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\AmazonSES\tests\Integration;

use Piwik\Mail;
use Piwik\Plugins\AmazonSES\Aws\AwsException;
use Piwik\Plugins\AmazonSES\Aws\Http\HttpResponse;
use Piwik\Plugins\AmazonSES\Mail\SesTransport;
use Piwik\Plugins\AmazonSES\tests\Framework\TestSesClientFactory;
use Piwik\Tests\Framework\TestCase\IntegrationTestCase;

/**
 * @group AmazonSES
 * @group Plugins
 */
class SesTransportTest extends IntegrationTestCase
{
    private function transport(TestSesClientFactory $factory): SesTransport
    {
        return new class ($factory) extends SesTransport {
            protected function isTestMode(): bool
            {
                return false;
            }
        };
    }

    private function mail(): Mail
    {
        $mail = new Mail();
        $mail->setFrom('noreply@example.com', 'Matomo');
        $mail->addTo('alice@example.com', 'Alice');
        $mail->addBcc('secret@example.com');
        $mail->addReplyTo('support@example.com');
        $mail->setSubject('Rapporto settimanale – àèì');
        $mail->setBodyHtml('<p>Hello <b>HTML</b></p>');
        $mail->setBodyText('Hello text');

        return $mail;
    }

    /**
     * @return array{0: array, 1: string} decoded payload and raw MIME
     */
    private function lastRequest(TestSesClientFactory $factory): array
    {
        $payload = json_decode(end($factory->http->requests)['body'], true);

        return [$payload, base64_decode($payload['Content']['Raw']['Data'])];
    }

    public function testSendsRawMimeThroughSes()
    {
        $factory = new TestSesClientFactory();
        $factory->http->queue(new HttpResponse(200, [], '{"MessageId":"msg-1"}'));
        $transport = $this->transport($factory);

        $this->assertTrue($transport->send($this->mail()));
        $this->assertSame('msg-1', $transport->getLastMessageId());

        [$payload, $mime] = $this->lastRequest($factory);

        $this->assertSame(['alice@example.com'], $payload['Destination']['ToAddresses']);
        $this->assertSame(['secret@example.com'], $payload['Destination']['BccAddresses']);
        $this->assertArrayNotHasKey('ConfigurationSetName', $payload);

        $this->assertStringContainsString("To: Alice <alice@example.com>\r\n", $mime);
        $this->assertStringContainsString("From: Matomo <noreply@example.com>\r\n", $mime);
        $this->assertStringContainsString("Reply-To: support@example.com\r\n", $mime);
        $this->assertStringContainsStringIgnoringCase('Subject: =?utf-8?', $mime);
        $this->assertStringContainsString('Auto-Submitted: yes', $mime);
        $this->assertStringContainsString('multipart/alternative', $mime);
        $this->assertStringContainsString('Hello text', $mime);
        $this->assertStringNotContainsString('Bcc:', $mime);
        $this->assertStringNotContainsString('secret@example.com', $mime);
    }

    public function testSenderOverrideAndConfigurationSet()
    {
        $factory = new TestSesClientFactory([
            'senderEmail' => 'analytics@example.org',
            'senderName' => 'Analytics',
            'configurationSet' => 'matomo',
        ]);
        $factory->http->queue(new HttpResponse(200, [], '{"MessageId":"msg-2"}'));

        $this->transport($factory)->send($this->mail());

        [$payload, $mime] = $this->lastRequest($factory);
        $this->assertSame('matomo', $payload['ConfigurationSetName']);
        $this->assertStringContainsString("From: Analytics <analytics@example.org>\r\n", $mime);
    }

    public function testAttachmentsAndInlineImages()
    {
        $factory = new TestSesClientFactory();
        $factory->http->queue(new HttpResponse(200, [], '{"MessageId":"msg-3"}'));

        $mail = $this->mail();
        $mail->addAttachment('%PDF-1.4 fake', 'application/pdf', 'report.pdf');
        $mail->addAttachment('PNGDATA', 'image/png', 'graph.png', 'graph-cid');

        $this->transport($factory)->send($mail);

        [, $mime] = $this->lastRequest($factory);
        $this->assertStringContainsString('filename=report.pdf', $mime);
        $this->assertStringContainsString(base64_encode('%PDF-1.4 fake'), $mime);
        $this->assertStringContainsString('Content-ID: <graph-cid>', $mime);
    }

    public function testSesErrorsAreNotSwallowed()
    {
        $factory = new TestSesClientFactory();
        $factory->http->queue(new HttpResponse(400, ['x-amzn-ErrorType' => 'MessageRejected'], '{"message":"Email address is not verified."}'));

        $this->expectException(AwsException::class);
        $this->expectExceptionMessage('Email address is not verified.');

        $this->transport($factory)->send($this->mail());
    }

    public function testInTestModeNothingIsSent()
    {
        $factory = new TestSesClientFactory();

        $this->assertTrue((new SesTransport($factory))->send($this->mail()));
        $this->assertEmpty($factory->http->requests);
    }
}
