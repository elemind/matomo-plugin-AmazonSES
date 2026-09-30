<?php

/**
 * AmazonSES plugin for Matomo
 *
 * @link    https://github.com/elemind/matomo-plugin-AmazonSES
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\AmazonSES;

class AmazonSES extends \Piwik\Plugin
{
    public function registerEvents()
    {
        return [
            'Translate.getClientSideTranslationKeys' => 'getClientSideTranslationKeys',
        ];
    }

    public function getClientSideTranslationKeys(&$translationKeys)
    {
        $keys = [
            'PageTitle', 'StatusTitle', 'Region', 'Endpoint', 'CredentialsSource', 'ConfigurationSet', 'Sender',
            'None', 'TestTitle', 'TestRecipient', 'TestRecipientHelp', 'SendTest', 'TestSent', 'SettingsLink',
            'Refresh', 'EmailsDisabled', 'SmtpIgnored',
        ];
        foreach ($keys as $key) {
            $translationKeys[] = 'AmazonSES_' . $key;
        }
        $translationKeys[] = 'General_Yes';
        $translationKeys[] = 'General_No';
        $translationKeys[] = 'General_Error';
    }
}
