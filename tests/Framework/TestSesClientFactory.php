<?php

/**
 * AmazonSES plugin for Matomo
 *
 * @link    https://github.com/elemind/matomo-plugin-AmazonSES
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\AmazonSES\tests\Framework;

use Piwik\Plugins\AmazonSES\Aws\Http\HttpClient;
use Piwik\Plugins\AmazonSES\Aws\SesV2Client;
use Piwik\Plugins\AmazonSES\SesClientFactory;
use Piwik\Plugins\AmazonSES\Settings\ConfigReader;

/**
 * Factory whose clients talk to a FakeHttpClient and whose configuration comes from an array.
 */
class TestSesClientFactory extends SesClientFactory
{
    /** @var FakeHttpClient */
    public $http;

    public function __construct(array $config = [], ?FakeHttpClient $http = null)
    {
        parent::__construct(new ConfigReader(null, $config + [
            'region' => 'eu-west-1',
            'accessKeyId' => 'AKIDEXAMPLE',
            'secretAccessKey' => 'secret',
        ], function () {
            return false;
        }));
        $this->http = $http ?: new FakeHttpClient();
    }

    public function createClient(?HttpClient $http = null): SesV2Client
    {
        return parent::createClient($http ?: $this->http);
    }
}
