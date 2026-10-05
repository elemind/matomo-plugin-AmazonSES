<?php

/**
 * AmazonSES plugin for Matomo
 *
 * @link    https://github.com/elemind/matomo-plugin-AmazonSES
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\AmazonSES\Settings;

use Piwik\Config;
use Piwik\Plugins\AmazonSES\SystemSettings;

/**
 * Effective plugin configuration.
 *
 * Precedence: [AmazonSES] section in config.ini.php (handled natively by Matomo system settings, the field is then
 * hidden in the UI) > UI system settings > AWS environment variables > defaults.
 */
class ConfigReader
{
    public const SECTION = 'AmazonSES';
    public const DEFAULT_REGION = 'us-east-1';

    /** @var SystemSettings|null */
    private $settings;

    /** @var array<string, mixed> */
    private $section;

    /** @var callable */
    private $getenv;

    /**
     * @param array<string, mixed>|null $section defaults to the [AmazonSES] section of config.ini.php
     */
    public function __construct(?SystemSettings $settings = null, ?array $section = null, ?callable $getenv = null)
    {
        $this->settings = $settings;
        $this->section = $section ?? self::readSection();
        $this->getenv = $getenv ?: 'getenv';
    }

    public static function readSection(): array
    {
        $section = Config::getInstance()->{self::SECTION};

        return is_array($section) ? $section : [];
    }

    public function getRegion(): string
    {
        $region = $this->get('region');
        if ($region === '') {
            $region = $this->env('AWS_REGION') ?: $this->env('AWS_DEFAULT_REGION');
        }

        return $region !== '' ? strtolower($region) : self::DEFAULT_REGION;
    }

    public function getAccessKeyId(): string
    {
        return $this->get('accessKeyId');
    }

    public function getSecretAccessKey(): string
    {
        return $this->get('secretAccessKey');
    }

    public function getConfigurationSet(): string
    {
        return $this->get('configurationSet');
    }

    public function getSenderEmail(): string
    {
        return $this->get('senderEmail');
    }

    public function getSenderName(): string
    {
        return $this->get('senderName');
    }

    /**
     * Custom endpoint (local mock, VPC endpoint). Only settable via config.ini.php or environment.
     */
    public function getEndpoint(): ?string
    {
        $endpoint = trim((string) ($this->section['endpoint'] ?? ''));
        if ($endpoint === '') {
            $endpoint = $this->env('AWS_ENDPOINT_URL_SESV2') ?: $this->env('AWS_ENDPOINT_URL');
        }

        return $endpoint !== '' ? $endpoint : null;
    }

    /**
     * Whether a plain http:// custom endpoint is accepted (local SES mock). Only settable via config.ini.php or environment.
     */
    public function allowsInsecureEndpoint(): bool
    {
        $value = trim((string) ($this->section['allowInsecureEndpoint'] ?? ''));
        if ($value === '') {
            $value = $this->env('AMAZONSES_ALLOW_INSECURE_ENDPOINT');
        }

        return in_array(strtolower($value), ['1', 'true', 'yes', 'on'], true);
    }

    public function getTimeout(): float
    {
        $timeout = (float) ($this->section['timeout'] ?? 0);

        return $timeout > 0 ? $timeout : 15.0;
    }

    private function get(string $name): string
    {
        if ($this->settings !== null) {
            // SystemSetting::getValue() already returns the config.ini.php value when one is set
            return trim((string) $this->settings->{$name}->getValue());
        }

        return trim((string) ($this->section[$name] ?? ''));
    }

    private function env(string $name): string
    {
        return trim((string) call_user_func($this->getenv, $name));
    }
}
