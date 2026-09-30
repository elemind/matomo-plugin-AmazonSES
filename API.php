<?php

/**
 * AmazonSES plugin for Matomo
 *
 * @link    https://github.com/elemind/matomo-plugin-AmazonSES
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\AmazonSES;

use Piwik\Config;
use Piwik\Container\StaticContainer;
use Piwik\Mail;
use Piwik\Piwik;
use Piwik\Plugins\AmazonSES\Aws\AwsException;
use Piwik\Plugins\AmazonSES\Mail\SesTransport;
use Piwik\SettingsPiwik;

/**
 * Super user only helpers used by the "Amazon SES" admin page.
 *
 * @method static \Piwik\Plugins\AmazonSES\API getInstance()
 */
class API extends \Piwik\Plugin\API
{
    /** @var SesClientFactory */
    private $factory;

    public function __construct(SesClientFactory $factory)
    {
        $this->factory = $factory;
    }

    /**
     * Returns the effective configuration (without secrets) and the resolved credentials source.
     * No call is made to Amazon SES, so it works with IAM policies that only allow sending.
     *
     * @return array
     */
    public function getStatus()
    {
        Piwik::checkUserHasSuperUserAccess();

        $config = $this->factory->getConfig();
        $status = [
            'region' => $config->getRegion(),
            'endpoint' => $config->getEndpoint(),
            'configurationSet' => $config->getConfigurationSet(),
            'senderEmail' => $config->getSenderEmail() ?: Config::getInstance()->General['noreply_email_address'],
            'emailsEnabled' => (bool) (Config::getInstance()->General['emails_enabled'] ?? true),
            'credentialsSource' => null,
            'smtpHost' => $this->getIgnoredSmtpHost(),
            'error' => null,
        ];

        try {
            $status['credentialsSource'] = $this->factory->getCredentialChain()->resolveOrFail()->getSource();
        } catch (AwsException $e) {
            $status['error'] = $e->getMessage();
        }

        return $status;
    }

    /**
     * The SMTP server configured in the core mail settings, if any: it is not used while this plugin is active.
     */
    private function getIgnoredSmtpHost(): ?string
    {
        $mail = Config::getInstance()->mail;
        if (!is_array($mail) || ($mail['transport'] ?? '') !== 'smtp' || trim((string) ($mail['host'] ?? '')) === '') {
            return null;
        }

        return trim((string) $mail['host']);
    }

    /**
     * Sends a test email through Amazon SES and returns the SES MessageId.
     *
     * @param string $email recipient, defaults to the current user's email
     * @return array
     */
    public function sendTestEmail($email = '')
    {
        Piwik::checkUserHasSuperUserAccess();

        $email = trim((string) $email);
        if ($email === '') {
            $email = Piwik::getCurrentUserEmail();
        }
        if (!Piwik::isValidEmailString($email)) {
            throw new \Exception(Piwik::translate('AmazonSES_InvalidTestRecipient'));
        }

        if (empty(Config::getInstance()->General['emails_enabled'] ?? true)) {
            throw new \Exception(Piwik::translate('AmazonSES_EmailsDisabled'));
        }

        $general = Config::getInstance()->General;
        $matomoUrl = SettingsPiwik::getPiwikUrl();

        $mail = new Mail();
        $mail->addTo($email);
        $mail->setFrom($general['noreply_email_address'], $general['noreply_email_name']);
        $mail->setSubject(Piwik::translate('AmazonSES_TestEmailSubject'));
        $mail->setWrappedHtmlBody(Piwik::translate('AmazonSES_TestEmailBody', [$matomoUrl]));

        $transport = StaticContainer::get('Piwik\Mail\Transport');
        if (!$transport instanceof SesTransport) {
            throw new \Exception(Piwik::translate('AmazonSES_TransportNotActive'));
        }

        try {
            $mail->send();
        } catch (AwsException $e) {
            throw new \Exception($e->getMessage());
        }

        return [
            'recipient' => $email,
            'messageId' => $transport->getLastMessageId(),
        ];
    }
}
