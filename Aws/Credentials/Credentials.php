<?php

/**
 * AmazonSES plugin for Matomo
 *
 * @link    https://github.com/elemind/matomo-plugin-AmazonSES
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\AmazonSES\Aws\Credentials;

class Credentials
{
    /** @var string */
    private $accessKeyId;

    /** @var string */
    private $secretAccessKey;

    /** @var string|null */
    private $sessionToken;

    /** @var int|null unix timestamp */
    private $expiration;

    /** @var string */
    private $source;

    public function __construct(
        string $accessKeyId,
        #[\SensitiveParameter]
        string $secretAccessKey,
        #[\SensitiveParameter]
        ?string $sessionToken = null,
        ?int $expiration = null,
        string $source = 'static'
    ) {
        $this->accessKeyId = $accessKeyId;
        $this->secretAccessKey = $secretAccessKey;
        $this->sessionToken = $sessionToken;
        $this->expiration = $expiration;
        $this->source = $source;
    }

    public function getAccessKeyId(): string
    {
        return $this->accessKeyId;
    }

    public function getSecretAccessKey(): string
    {
        return $this->secretAccessKey;
    }

    public function getSessionToken(): ?string
    {
        return $this->sessionToken;
    }

    public function getExpiration(): ?int
    {
        return $this->expiration;
    }

    /**
     * Where the credentials come from (settings, env, ecs, imds). Shown in the admin UI.
     */
    public function getSource(): string
    {
        return $this->source;
    }

    public function isExpired(int $now, int $marginSeconds = 300): bool
    {
        return $this->expiration !== null && $this->expiration - $marginSeconds <= $now;
    }
}
