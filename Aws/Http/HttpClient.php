<?php

/**
 * AmazonSES plugin for Matomo
 *
 * @link    https://github.com/elemind/matomo-plugin-AmazonSES
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\AmazonSES\Aws\Http;

use Piwik\Plugins\AmazonSES\Aws\AwsException;

interface HttpClient
{
    /**
     * @param array<string, string> $headers
     * @throws AwsException on network errors (not on HTTP error statuses)
     */
    public function request(string $method, string $url, array $headers = [], string $body = '', ?float $timeout = null): HttpResponse;
}
