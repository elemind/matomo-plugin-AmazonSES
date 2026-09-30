<?php

/**
 * AmazonSES plugin for Matomo
 *
 * @link    https://github.com/elemind/matomo-plugin-AmazonSES
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\AmazonSES\tests\Framework;

use Piwik\Plugins\AmazonSES\Aws\AwsException;
use Piwik\Plugins\AmazonSES\Aws\Http\HttpClient;
use Piwik\Plugins\AmazonSES\Aws\Http\HttpResponse;

class FakeHttpClient implements HttpClient
{
    /** @var array<int, array{method: string, url: string, headers: array<string, string>, body: string}> */
    public $requests = [];

    /** @var array<int, HttpResponse|AwsException> */
    private $queue = [];

    /**
     * @param HttpResponse|AwsException $response
     */
    public function queue($response): self
    {
        $this->queue[] = $response;
        return $this;
    }

    public function request(string $method, string $url, array $headers = [], string $body = '', ?float $timeout = null): HttpResponse
    {
        $this->requests[] = ['method' => $method, 'url' => $url, 'headers' => $headers, 'body' => $body];

        if (empty($this->queue)) {
            throw new \LogicException("Unexpected HTTP request: $method $url");
        }

        $next = array_shift($this->queue);
        if ($next instanceof AwsException) {
            throw $next;
        }

        return $next;
    }
}
