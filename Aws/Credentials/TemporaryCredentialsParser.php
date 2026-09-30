<?php

/**
 * AmazonSES plugin for Matomo
 *
 * @link    https://github.com/elemind/matomo-plugin-AmazonSES
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\AmazonSES\Aws\Credentials;

use Piwik\Plugins\AmazonSES\Aws\AwsException;

/**
 * Parses the JSON document returned by the ECS container endpoint and EC2 instance metadata service.
 */
class TemporaryCredentialsParser
{
    public static function parse(string $json, string $source): Credentials
    {
        $data = json_decode($json, true);
        if (!is_array($data) || empty($data['AccessKeyId']) || empty($data['SecretAccessKey'])) {
            throw new AwsException(sprintf('Invalid credentials document received from %s.', $source));
        }

        $expiration = null;
        if (!empty($data['Expiration'])) {
            $time = strtotime((string) $data['Expiration']);
            $expiration = $time !== false ? $time : null;
        }

        return new Credentials(
            (string) $data['AccessKeyId'],
            (string) $data['SecretAccessKey'],
            !empty($data['Token']) ? (string) $data['Token'] : null,
            $expiration,
            $source
        );
    }
}
