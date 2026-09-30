<?php

/**
 * AmazonSES plugin for Matomo
 *
 * @link    https://github.com/elemind/matomo-plugin-AmazonSES
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\AmazonSES;

use Piwik\Piwik;
use Piwik\Settings\FieldConfig;
use Piwik\Settings\Setting;
use Piwik\Validators\CharacterLength;
use Piwik\Validators\Email;
use Piwik\Validators\Exception as ValidatorException;

/**
 * Every setting can also be set in config.ini.php, e.g.:
 *
 *   [AmazonSES]
 *   region = "eu-west-1"
 *   senderEmail = "analytics@example.com"
 *
 * A value set in config.ini.php wins and the field is no longer editable in the UI.
 */
class SystemSettings extends \Piwik\Settings\Plugin\SystemSettings
{
    /**
     * Regions where the SES v2 API is available. Any other region can be set in config.ini.php.
     */
    public const REGIONS = [
        'us-east-1', 'us-east-2', 'us-west-1', 'us-west-2',
        'ca-central-1', 'sa-east-1',
        'eu-west-1', 'eu-west-2', 'eu-west-3', 'eu-central-1', 'eu-north-1', 'eu-south-1',
        'ap-south-1', 'ap-northeast-1', 'ap-northeast-2', 'ap-northeast-3',
        'ap-southeast-1', 'ap-southeast-2', 'ap-southeast-3',
        'af-south-1', 'il-central-1', 'me-south-1',
        'us-gov-west-1',
    ];

    /** @var Setting */
    public $region;

    /** @var Setting */
    public $accessKeyId;

    /** @var Setting */
    public $secretAccessKey;

    /** @var Setting */
    public $configurationSet;

    /** @var Setting */
    public $senderEmail;

    /** @var Setting */
    public $senderName;

    protected function init()
    {
        $this->title = 'Amazon SES';

        $this->region = $this->makeSetting('region', '', FieldConfig::TYPE_STRING, function (FieldConfig $field) {
            $field->title = Piwik::translate('AmazonSES_SettingRegion');
            $field->uiControl = FieldConfig::UI_CONTROL_SINGLE_SELECT;
            $field->availableValues = ['' => Piwik::translate('AmazonSES_SettingRegionAuto')] + array_combine(self::REGIONS, self::REGIONS);
            $field->description = Piwik::translate('AmazonSES_SettingRegionHelp');
        });

        $this->accessKeyId = $this->makeSetting('accessKeyId', '', FieldConfig::TYPE_STRING, function (FieldConfig $field) {
            $field->title = Piwik::translate('AmazonSES_SettingAccessKeyId');
            $field->uiControl = FieldConfig::UI_CONTROL_TEXT;
            $field->description = Piwik::translate('AmazonSES_SettingAccessKeyIdHelp');
            $field->validate = self::patternValidator('/^[A-Z0-9]{16,128}$/', 'AmazonSES_SettingAccessKeyIdInvalid');
        });

        $this->secretAccessKey = $this->makeSetting('secretAccessKey', '', FieldConfig::TYPE_STRING, function (FieldConfig $field) {
            $field->title = Piwik::translate('AmazonSES_SettingSecretAccessKey');
            $field->uiControl = FieldConfig::UI_CONTROL_PASSWORD;
            $field->description = Piwik::translate('AmazonSES_SettingSecretAccessKeyHelp');
            $field->validators[] = new CharacterLength(0, 128);
        });

        $this->configurationSet = $this->makeSetting('configurationSet', '', FieldConfig::TYPE_STRING, function (FieldConfig $field) {
            $field->title = Piwik::translate('AmazonSES_SettingConfigurationSet');
            $field->uiControl = FieldConfig::UI_CONTROL_TEXT;
            $field->description = Piwik::translate('AmazonSES_SettingConfigurationSetHelp');
            $field->validate = self::patternValidator('/^[A-Za-z0-9_-]{1,64}$/', 'AmazonSES_SettingConfigurationSetInvalid');
        });

        $this->senderEmail = $this->makeSetting('senderEmail', '', FieldConfig::TYPE_STRING, function (FieldConfig $field) {
            $field->title = Piwik::translate('AmazonSES_SettingSenderEmail');
            $field->uiControl = FieldConfig::UI_CONTROL_TEXT;
            $field->description = Piwik::translate('AmazonSES_SettingSenderEmailHelp');
            $field->validators[] = new Email();
        });

        $this->senderName = $this->makeSetting('senderName', '', FieldConfig::TYPE_STRING, function (FieldConfig $field) {
            $field->title = Piwik::translate('AmazonSES_SettingSenderName');
            $field->uiControl = FieldConfig::UI_CONTROL_TEXT;
            $field->description = Piwik::translate('AmazonSES_SettingSenderNameHelp');
            $field->validators[] = new CharacterLength(0, 100);
        });
    }

    private static function patternValidator(string $pattern, string $errorTranslationKey): \Closure
    {
        return function ($value) use ($pattern, $errorTranslationKey) {
            $value = trim((string) $value);
            if ($value !== '' && !preg_match($pattern, $value)) {
                throw new ValidatorException(Piwik::translate($errorTranslationKey));
            }
        };
    }
}
