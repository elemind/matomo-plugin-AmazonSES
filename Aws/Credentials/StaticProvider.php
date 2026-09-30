<?php

/**
 * AmazonSES plugin for Matomo
 *
 * @link    https://github.com/elemind/matomo-plugin-AmazonSES
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\AmazonSES\Aws\Credentials;

class StaticProvider implements CredentialProvider
{
    /** @var string */
    private $accessKeyId;

    /** @var string */
    private $secretAccessKey;

    /** @var string */
    private $source;

    public function __construct(
        ?string $accessKeyId,
        #[\SensitiveParameter]
        ?string $secretAccessKey,
        string $source = 'settings'
    ) {
        $this->accessKeyId = trim((string) $accessKeyId);
        $this->secretAccessKey = trim((string) $secretAccessKey);
        $this->source = $source;
    }

    public function resolve(): ?Credentials
    {
        if ($this->accessKeyId === '' || $this->secretAccessKey === '') {
            return null;
        }

        return new Credentials($this->accessKeyId, $this->secretAccessKey, null, null, $this->source);
    }
}
