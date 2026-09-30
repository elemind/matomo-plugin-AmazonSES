<?php

/**
 * AmazonSES plugin for Matomo
 *
 * @link    https://github.com/elemind/matomo-plugin-AmazonSES
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\AmazonSES\Mail;

use PHPMailer\PHPMailer\PHPMailer;
use Piwik\Container\StaticContainer;
use Piwik\Log\LoggerInterface;
use Piwik\Mail;
use Piwik\Mail\Transport;
use Piwik\Piwik;
use Piwik\Plugins\AmazonSES\SesClientFactory;

/**
 * Replaces the core mail transport (see config/config.php): the message is built with PHPMailer exactly like core
 * does, then delivered as a raw MIME message through the SES v2 SendEmail API.
 */
class SesTransport extends Transport
{
    /** @var SesClientFactory */
    private $factory;

    /** @var string|null */
    private $lastMessageId;

    public function __construct(SesClientFactory $factory)
    {
        $this->factory = $factory;
    }

    /**
     * @return bool
     * @throws \Piwik\Plugins\AmazonSES\Aws\AwsException
     * @throws \PHPMailer\PHPMailer\Exception
     */
    public function send(Mail $mail)
    {
        $phpMailer = $this->buildPhpMailer($mail);

        if ($this->isTestMode()) {
            /**
             * @ignore
             * @internal
             */
            Piwik::postTestEvent('Test.Mail.send', [$phpMailer]);
            return true;
        }

        $phpMailer->preSend();

        $config = $this->factory->getConfig();
        $this->lastMessageId = $this->factory->createClient()->sendRawEmail(
            $phpMailer->getSentMIMEMessage(),
            array_keys($mail->getRecipients()),
            array_keys($mail->getBccs()),
            null,
            $config->getConfigurationSet() ?: null
        );

        StaticContainer::get(LoggerInterface::class)->debug(
            'AmazonSES: sent "{subject}" to {count} recipient(s), SES MessageId {id}',
            [
                'subject' => $mail->getSubject(),
                'count' => count($mail->getRecipients()) + count($mail->getBccs()),
                'id' => $this->lastMessageId,
            ]
        );

        return true;
    }

    public function getLastMessageId(): ?string
    {
        return $this->lastMessageId;
    }

    /**
     * Mirrors core Transport::send() without the SMTP part.
     */
    public function buildPhpMailer(Mail $mail): PHPMailer
    {
        $phpMailer = new PHPMailer(true);
        // "smtp" gives CRLF line endings and keeps To/Subject in the headers but never writes a Bcc header;
        // nothing is sent through SMTP since we only call preSend()
        $phpMailer->Mailer = 'smtp';

        PHPMailer::$validator = 'pcre8';
        $phpMailer->CharSet = PHPMailer::CHARSET_UTF8;
        $phpMailer->Encoding = PHPMailer::ENCODING_QUOTED_PRINTABLE;
        $phpMailer->XMailer = ' ';
        // avoid triggering automated (vacation) responses
        $phpMailer->addCustomHeader('Auto-Submitted', 'yes');
        // instance call: setLanguage() is static in PHPMailer 7 but an instance method in PHPMailer 6 (early Matomo 5 releases)
        $phpMailer->setLanguage(StaticContainer::get('Piwik\Translation\Translator')->getCurrentLanguage());

        $phpMailer->Subject = $mail->getSubject();

        $htmlContent = $mail->getBodyHtml();
        $textContent = $mail->getBodyText();

        if (!empty($htmlContent)) {
            $phpMailer->msgHTML($htmlContent);

            if (!empty($textContent)) {
                $phpMailer->AltBody = $textContent;
            }
        } else {
            $phpMailer->Body = $textContent;
        }

        $config = $this->factory->getConfig();
        $fromEmail = $config->getSenderEmail() ?: $mail->getFrom();
        $fromName = $config->getSenderName() ?: $mail->getFromName();
        $phpMailer->setFrom($fromEmail, (string) $fromName);

        foreach ($mail->getRecipients() as $address => $name) {
            $phpMailer->addAddress($address, $name);
        }

        foreach ($mail->getBccs() as $address => $name) {
            $phpMailer->addBCC($address, $name);
        }

        foreach ($mail->getReplyTos() as $address => $name) {
            $phpMailer->addReplyTo($address, $name);
        }

        foreach ($mail->getAttachments() as $attachment) {
            if (!empty($attachment['cid'])) {
                $phpMailer->addStringEmbeddedImage(
                    $attachment['content'],
                    $attachment['cid'],
                    $attachment['filename'],
                    PHPMailer::ENCODING_BASE64,
                    $attachment['mimetype']
                );
            } else {
                $phpMailer->addStringAttachment(
                    $attachment['content'],
                    $attachment['filename'],
                    PHPMailer::ENCODING_BASE64,
                    $attachment['mimetype']
                );
            }
        }

        return $phpMailer;
    }

    protected function isTestMode(): bool
    {
        return defined('PIWIK_TEST_MODE');
    }
}
