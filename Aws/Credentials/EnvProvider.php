<?php

/**
 * AmazonSES plugin for Matomo
 *
 * @link    https://github.com/elemind/matomo-plugin-AmazonSES
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\AmazonSES\Aws\Credentials;

/**
 * Reads AWS_ACCESS_KEY_ID / AWS_SECRET_ACCESS_KEY / AWS_SESSION_TOKEN.
 */
class EnvProvider implements CredentialProvider
{
    /** @var callable */
    private $getenv;

    public function __construct(?callable $getenv = null)
    {
        $this->getenv = $getenv ?: 'getenv';
    }

    public function resolve(): ?Credentials
    {
        $key = (string) call_user_func($this->getenv, 'AWS_ACCESS_KEY_ID');
        $secret = (string) call_user_func($this->getenv, 'AWS_SECRET_ACCESS_KEY');
        if ($key === '' || $secret === '') {
            return null;
        }

        $token = (string) call_user_func($this->getenv, 'AWS_SESSION_TOKEN');

        return new Credentials($key, $secret, $token !== '' ? $token : null, null, 'env');
    }
}
