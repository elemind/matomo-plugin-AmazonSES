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
     * Returns the effective configuration (without secrets), the resolved credentials source and the SES account state.
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
            'account' => null,
            'error' => null,
        ];

        try {
            $credentials = $this->factory->getCredentialChain()->resolveOrFail();
            $status['credentialsSource'] = $credentials->getSource();

            $account = $this->factory->createClient()->getAccount();
            $quota = $account['SendQuota'] ?? [];
            $status['account'] = [
                'productionAccessEnabled' => (bool) ($account['ProductionAccessEnabled'] ?? false),
                'sendingEnabled' => (bool) ($account['SendingEnabled'] ?? true),
                'enforcementStatus' => (string) ($account['EnforcementStatus'] ?? ''),
                'max24HourSend' => $quota['Max24HourSend'] ?? null,
                'maxSendRate' => $quota['MaxSendRate'] ?? null,
                'sentLast24Hours' => $quota['SentLast24Hours'] ?? null,
            ];
        } catch (AwsException $e) {
            $status['error'] = $e->getMessage();
        }

        return $status;
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
