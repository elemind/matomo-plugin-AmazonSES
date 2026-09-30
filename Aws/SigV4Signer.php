<?php

/**
 * AmazonSES plugin for Matomo
 *
 * @link    https://github.com/elemind/matomo-plugin-AmazonSES
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\AmazonSES\Aws;

use Piwik\Plugins\AmazonSES\Aws\Credentials\Credentials;

/**
 * Minimal AWS Signature Version 4 signer (header based).
 *
 * @see https://docs.aws.amazon.com/IAM/latest/UserGuide/reference_sigv-create-signed-request.html
 */
class SigV4Signer
{
    public const ALGORITHM = 'AWS4-HMAC-SHA256';

    /** @var string */
    private $service;

    /** @var string */
    private $region;

    public function __construct(string $service, string $region)
    {
        $this->service = $service;
        $this->region = $region;
    }

    /**
     * Returns the given headers plus the X-Amz-Date, X-Amz-Security-Token (if any) and Authorization headers.
     *
     * @param array<string, string> $headers
     * @return array<string, string>
     */
    public function sign(
        string $method,
        string $url,
        array $headers,
        string $body,
        Credentials $credentials,
        ?\DateTimeInterface $now = null
    ): array {
        $now = $now ?: new \DateTimeImmutable('now', new \DateTimeZone('UTC'));
        $utc = (new \DateTimeImmutable('@' . $now->getTimestamp()))->setTimezone(new \DateTimeZone('UTC'));
        $amzDate = $utc->format('Ymd\THis\Z');
        $date = $utc->format('Ymd');

        $parts = parse_url($url);
        if ($parts === false || empty($parts['host'])) {
            throw new \InvalidArgumentException('Invalid URL to sign: ' . $url);
        }

        $host = $parts['host'];
        if (!empty($parts['port'])) {
            $host .= ':' . $parts['port'];
        }

        if (!$this->hasHeader($headers, 'host')) {
            $headers['Host'] = $host;
        }
        $headers['X-Amz-Date'] = $amzDate;
        if ($credentials->getSessionToken() !== null && $credentials->getSessionToken() !== '') {
            $headers['X-Amz-Security-Token'] = $credentials->getSessionToken();
        }

        [$canonicalHeaders, $signedHeaders] = $this->canonicalizeHeaders($headers);

        $canonicalRequest = implode("\n", [
            strtoupper($method),
            $this->canonicalizePath($parts['path'] ?? '/'),
            $this->canonicalizeQuery($parts['query'] ?? ''),
            $canonicalHeaders,
            $signedHeaders,
            hash('sha256', $body),
        ]);

        $scope = implode('/', [$date, $this->region, $this->service, 'aws4_request']);
        $stringToSign = implode("\n", [
            self::ALGORITHM,
            $amzDate,
            $scope,
            hash('sha256', $canonicalRequest),
        ]);

        $signingKey = $this->deriveSigningKey($credentials->getSecretAccessKey(), $date);
        $signature = hash_hmac('sha256', $stringToSign, $signingKey);

        $headers['Authorization'] = sprintf(
            '%s Credential=%s/%s, SignedHeaders=%s, Signature=%s',
            self::ALGORITHM,
            $credentials->getAccessKeyId(),
            $scope,
            $signedHeaders,
            $signature
        );

        return $headers;
    }

    private function deriveSigningKey(
        #[\SensitiveParameter]
        string $secret,
        string $date
    ): string {
        $kDate = hash_hmac('sha256', $date, 'AWS4' . $secret, true);
        $kRegion = hash_hmac('sha256', $this->region, $kDate, true);
        $kService = hash_hmac('sha256', $this->service, $kRegion, true);

        return hash_hmac('sha256', 'aws4_request', $kService, true);
    }

    /**
     * @param array<string, string> $headers
     * @return array{0: string, 1: string}
     */
    private function canonicalizeHeaders(array $headers): array
    {
        $normalized = [];
        foreach ($headers as $name => $value) {
            $name = strtolower(trim((string) $name));
            $value = preg_replace('/\s+/', ' ', trim((string) $value));
            $normalized[$name] = isset($normalized[$name]) ? $normalized[$name] . ',' . $value : $value;
        }
        ksort($normalized, SORT_STRING);

        $canonical = '';
        foreach ($normalized as $name => $value) {
            $canonical .= $name . ':' . $value . "\n";
        }

        return [$canonical, implode(';', array_keys($normalized))];
    }

    private function canonicalizePath(string $path): string
    {
        if ($path === '') {
            return '/';
        }

        $segments = explode('/', $path);
        foreach ($segments as $i => $segment) {
            // non-S3 services expect each segment to be encoded twice; the first pass normalizes the raw input
            $segments[$i] = rawurlencode(rawurlencode(rawurldecode($segment)));
        }

        return implode('/', $segments);
    }

    private function canonicalizeQuery(string $query): string
    {
        if ($query === '') {
            return '';
        }

        $pairs = [];
        foreach (explode('&', $query) as $pair) {
            if ($pair === '') {
                continue;
            }
            $kv = explode('=', $pair, 2);
            $pairs[] = [
                rawurlencode(rawurldecode($kv[0])),
                rawurlencode(rawurldecode($kv[1] ?? '')),
            ];
        }

        usort($pairs, function ($a, $b) {
            return strcmp($a[0], $b[0]) ?: strcmp($a[1], $b[1]);
        });

        return implode('&', array_map(function ($kv) {
            return $kv[0] . '=' . $kv[1];
        }, $pairs));
    }

    /**
     * @param array<string, string> $headers
     */
    private function hasHeader(array $headers, string $name): bool
    {
        foreach ($headers as $key => $unused) {
            if (strtolower((string) $key) === $name) {
                return true;
            }
        }

        return false;
    }
}
