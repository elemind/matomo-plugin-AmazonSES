<?php

/**
 * AmazonSES plugin for Matomo
 *
 * @link    https://github.com/elemind/matomo-plugin-AmazonSES
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

use Piwik\Plugins\AmazonSES\Mail\SesTransport;
use Piwik\Plugins\AmazonSES\SesClientFactory;
use Piwik\Plugins\AmazonSES\Settings\ConfigReader;
use Piwik\Plugins\AmazonSES\SystemSettings;

return [
    // Replace the core mail transport. Only active while the plugin is activated.
    'Piwik\Mail\Transport' => Piwik\DI::get(SesTransport::class),

    SesTransport::class => Piwik\DI::factory(function ($c) {
        return new SesTransport($c->get(SesClientFactory::class));
    }),

    SesClientFactory::class => Piwik\DI::factory(function ($c) {
        return new SesClientFactory(new ConfigReader($c->get(SystemSettings::class)));
    }),
];
