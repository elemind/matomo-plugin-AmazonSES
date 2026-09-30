<?php

/**
 * AmazonSES plugin for Matomo
 *
 * @link    https://github.com/elemind/matomo-plugin-AmazonSES
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\AmazonSES\Aws\Http;

use Piwik\Plugins\AmazonSES\Aws\AwsException;

class CurlHttpClient implements HttpClient
{
    /** @var float */
    private $defaultTimeout;

    /** @var array{host?: string, port?: string|int, username?: string, password?: string} */
    private $proxy;

    /**
     * @param array{host?: string, port?: string|int, username?: string, password?: string} $proxy
     */
    public function __construct(float $defaultTimeout = 15.0, array $proxy = [])
    {
        $this->defaultTimeout = $defaultTimeout;
        $this->proxy = $proxy;
    }

    public function request(string $method, string $url, array $headers = [], string $body = '', ?float $timeout = null): HttpResponse
    {
        if (!function_exists('curl_init')) {
            throw new AwsException('The PHP cURL extension is required by the AmazonSES plugin.');
        }

        $headerLines = [];
        foreach ($headers as $name => $value) {
            $headerLines[] = $name . ': ' . $value;
        }
        // avoid "Expect: 100-continue" round trips on large raw messages
        $headerLines[] = 'Expect:';

        $timeout = $timeout ?: $this->defaultTimeout;
        $responseHeaders = [];

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => strtoupper($method),
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headerLines,
            CURLOPT_CONNECTTIMEOUT_MS => (int) (min($timeout, 5) * 1000),
            CURLOPT_TIMEOUT_MS => (int) ($timeout * 1000),
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HEADERFUNCTION => function ($ch, string $line) use (&$responseHeaders) {
                $parts = explode(':', $line, 2);
                if (count($parts) === 2) {
                    $responseHeaders[strtolower(trim($parts[0]))] = trim($parts[1]);
                }
                return strlen($line);
            },
        ]);

        if ($body !== '' || in_array(strtoupper($method), ['POST', 'PUT'], true)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
        }

        if (!empty($this->proxy['host'])) {
            curl_setopt($ch, CURLOPT_PROXY, $this->proxy['host']);
            if (!empty($this->proxy['port'])) {
                curl_setopt($ch, CURLOPT_PROXYPORT, (int) $this->proxy['port']);
            }
            if (!empty($this->proxy['username'])) {
                curl_setopt($ch, CURLOPT_PROXYUSERPWD, $this->proxy['username'] . ':' . ($this->proxy['password'] ?? ''));
            }
        }

        $result = curl_exec($ch);
        if ($result === false) {
            $error = curl_error($ch);
            $errno = curl_errno($ch);
            throw new AwsException(sprintf('HTTP request to %s failed: %s (curl error %d)', $url, $error, $errno));
        }

        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);

        return new HttpResponse($status, $responseHeaders, (string) $result);
    }
}
