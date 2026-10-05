<?php

/**
 * AmazonSES plugin for Matomo
 *
 * @link    https://github.com/elemind/matomo-plugin-AmazonSES
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\AmazonSES\Aws;

use Piwik\Plugins\AmazonSES\Aws\Credentials\CredentialProviderChain;
use Piwik\Plugins\AmazonSES\Aws\Http\HttpClient;
use Piwik\Plugins\AmazonSES\Aws\Http\HttpResponse;

/**
 * Tiny client for the SES v2 SendEmail operation.
 *
 * @see https://docs.aws.amazon.com/ses/latest/APIReference-V2/API_SendEmail.html
 */
class SesV2Client
{
    public const SERVICE = 'ses';

    /** SES v2 limit for a raw message, after base64 encoding. */
    public const MAX_RAW_MESSAGE_BYTES = 40 * 1024 * 1024;

    /** @var CredentialProviderChain */
    private $credentials;

    /** @var HttpClient */
    private $http;

    /** @var string */
    private $region;

    /** @var string */
    private $endpoint;

    /** @var callable|null returns \DateTimeInterface, used by tests */
    private $clock;

    public function __construct(
        CredentialProviderChain $credentials,
        HttpClient $http,
        string $region,
        #[\SensitiveParameter]
        ?string $endpoint = null,
        bool $allowInsecureEndpoint = false,
        ?callable $clock = null
    ) {
        if (!preg_match('/^[a-z]{2}(-[a-z]+)+-\d+$/', $region)) {
            throw new AwsException(sprintf('Invalid AWS region "%s".', $region), 'InvalidRegion');
        }

        if ($endpoint !== null && $endpoint !== '') {
            self::assertValidEndpoint($endpoint, $allowInsecureEndpoint);
        }

        $this->credentials = $credentials;
        $this->http = $http;
        $this->region = $region;
        $this->endpoint = rtrim($endpoint ?: sprintf('https://email.%s.amazonaws.com', $region), '/');
        $this->clock = $clock;
    }

    public function getRegion(): string
    {
        return $this->region;
    }

    public function getEndpoint(): string
    {
        return $this->endpoint;
    }

    public function isEndpointEncrypted(): bool
    {
        return stripos($this->endpoint, 'https://') === 0;
    }

    /**
     * Signed requests and email contents travel to this URL, so plain HTTP is only accepted when explicitly allowed
     * (local SES mock). The URL is never part of the error message: it could embed credentials.
     */
    private static function assertValidEndpoint(
        #[\SensitiveParameter]
        string $endpoint,
        bool $allowInsecure
    ): void {
        $parts = parse_url($endpoint);
        if (!is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            throw new AwsException('The custom Amazon SES endpoint is not a valid URL.', 'InvalidEndpoint');
        }

        $scheme = strtolower($parts['scheme']);
        if ($scheme !== 'https' && !($scheme === 'http' && $allowInsecure)) {
            throw new AwsException(
                'The custom Amazon SES endpoint must use https://. Plain http:// is only accepted for a local SES mock,'
                . ' with allowInsecureEndpoint = 1 in the [AmazonSES] section of config.ini.php'
                . ' or the AMAZONSES_ALLOW_INSECURE_ENDPOINT=1 environment variable.',
                'InvalidEndpoint'
            );
        }

        foreach (['user', 'pass', 'query', 'fragment'] as $forbidden) {
            if (array_key_exists($forbidden, $parts)) {
                throw new AwsException(
                    'The custom Amazon SES endpoint must not contain credentials, a query string or a fragment.',
                    'InvalidEndpoint'
                );
            }
        }
    }

    /**
     * Sends a complete MIME message.
     *
     * @param string[] $to  recipients (To + Cc)
     * @param string[] $bcc blind recipients, not present in the MIME headers
     * @return string SES MessageId
     */
    public function sendRawEmail(
        string $rawMime,
        array $to,
        array $bcc = [],
        ?string $fromEmailAddress = null,
        ?string $configurationSetName = null
    ): string {
        $encoded = base64_encode($rawMime);
        if (strlen($encoded) > self::MAX_RAW_MESSAGE_BYTES) {
            throw new AwsException('The email is larger than the 40 MB accepted by Amazon SES.', 'MessageTooLarge');
        }

        $payload = [
            'Content' => ['Raw' => ['Data' => $encoded]],
        ];

        $destination = [];
        if (!empty($to)) {
            $destination['ToAddresses'] = array_values($to);
        }
        if (!empty($bcc)) {
            $destination['BccAddresses'] = array_values($bcc);
        }
        if (!empty($destination)) {
            $payload['Destination'] = $destination;
        }
        if (!empty($fromEmailAddress)) {
            $payload['FromEmailAddress'] = $fromEmailAddress;
        }
        if (!empty($configurationSetName)) {
            $payload['ConfigurationSetName'] = $configurationSetName;
        }

        $data = $this->call('POST', '/v2/email/outbound-emails', $payload);

        if (empty($data['MessageId'])) {
            throw new AwsException('Amazon SES accepted the request but returned no MessageId.', 'InvalidResponse');
        }

        return (string) $data['MessageId'];
    }

    /**
     * @param array<string, mixed>|null $payload
     * @return array<string, mixed>
     */
    private function call(string $method, string $path, ?array $payload = null): array
    {
        $body = $payload === null ? '' : (string) json_encode($payload, JSON_UNESCAPED_SLASHES);
        $headers = ['Accept' => 'application/json'];
        if ($payload !== null) {
            $headers['Content-Type'] = 'application/json';
        }

        $url = $this->endpoint . $path;
        $now = $this->clock ? call_user_func($this->clock) : null;

        $signer = new SigV4Signer(self::SERVICE, $this->region);
        $signed = $signer->sign($method, $url, $headers, $body, $this->credentials->resolveOrFail(), $now);

        $response = $this->http->request($method, $url, $signed, $body);

        if (!$response->isSuccessful()) {
            throw $this->toException($response);
        }

        $decoded = json_decode($response->getBody(), true);

        return is_array($decoded) ? $decoded : [];
    }

    private function toException(HttpResponse $response): AwsException
    {
        $data = json_decode($response->getBody(), true);
        $message = '';
        $code = '';

        if (is_array($data)) {
            $message = (string) ($data['message'] ?? $data['Message'] ?? '');
            $code = (string) ($data['__type'] ?? $data['code'] ?? '');
        }

        $typeHeader = (string) $response->getHeader('x-amzn-ErrorType');
        if ($typeHeader !== '') {
            $code = $typeHeader;
        }

        // "BadRequestException:http://internal.amazon.com/..." or "com.amazon...#MessageRejected"
        $code = preg_replace('/[:].*$/', '', $code);
        $code = preg_replace('/^.*#/', '', (string) $code);

        if ($message === '') {
            $message = trim(substr(strip_tags($response->getBody()), 0, 300)) ?: 'no error message';
        }

        return new AwsException(
            sprintf('Amazon SES error (HTTP %d%s): %s', $response->getStatus(), $code !== '' ? ', ' . $code : '', $message),
            (string) $code,
            $response->getStatus()
        );
    }
}
