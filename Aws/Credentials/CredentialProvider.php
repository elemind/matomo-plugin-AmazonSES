<?php

/**
 * AmazonSES plugin for Matomo
 *
 * @link    https://github.com/elemind/matomo-plugin-AmazonSES
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\AmazonSES\Aws\Credentials;

interface CredentialProvider
{
    /**
     * Returns credentials, or null when this provider is not configured / not available.
     *
     * @throws \Piwik\Plugins\AmazonSES\Aws\AwsException when the provider is configured but fails
     */
    public function resolve(): ?Credentials;
}
