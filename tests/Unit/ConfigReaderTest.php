<?php

/**
 * AmazonSES plugin for Matomo
 *
 * @link    https://github.com/elemind/matomo-plugin-AmazonSES
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\AmazonSES\tests\Unit;

use PHPUnit\Framework\TestCase;
use Piwik\Plugins\AmazonSES\Settings\ConfigReader;

/**
 * @group AmazonSES
 * @group Unit
 */
class ConfigReaderTest extends TestCase
{
    private function reader(array $section, array $env = []): ConfigReader
    {
        return new ConfigReader(null, $section, function ($name) use ($env) {
            return $env[$name] ?? false;
        });
    }

    public function testDefaults()
    {
        $reader = $this->reader([]);

        $this->assertSame('us-east-1', $reader->getRegion());
        $this->assertNull($reader->getEndpoint());
        $this->assertSame(15.0, $reader->getTimeout());
        $this->assertSame('', $reader->getSenderEmail());
        $this->assertFalse($reader->allowsInsecureEndpoint());
    }

    public function testRegionFallsBackToEnvironment()
    {
        $this->assertSame('eu-south-1', $this->reader([], ['AWS_DEFAULT_REGION' => 'eu-south-1'])->getRegion());
        $this->assertSame('eu-west-1', $this->reader([], ['AWS_REGION' => 'EU-WEST-1', 'AWS_DEFAULT_REGION' => 'x'])->getRegion());
        $this->assertSame('ap-south-1', $this->reader(['region' => 'ap-south-1'], ['AWS_REGION' => 'eu-west-1'])->getRegion());
    }

    public function testEndpointPrecedence()
    {
        $this->assertSame('http://generic', $this->reader([], ['AWS_ENDPOINT_URL' => 'http://generic'])->getEndpoint());
        $this->assertSame('http://sesv2', $this->reader([], [
            'AWS_ENDPOINT_URL' => 'http://generic',
            'AWS_ENDPOINT_URL_SESV2' => 'http://sesv2',
        ])->getEndpoint());
        $this->assertSame('http://ini', $this->reader(['endpoint' => 'http://ini'], [
            'AWS_ENDPOINT_URL_SESV2' => 'http://sesv2',
        ])->getEndpoint());
    }

    public function testAllowInsecureEndpoint()
    {
        foreach (['1', 'true', 'Yes', ' on '] as $value) {
            $this->assertTrue($this->reader(['allowInsecureEndpoint' => $value])->allowsInsecureEndpoint(), $value);
        }
        foreach (['0', 'false', 'no', 'whatever'] as $value) {
            $this->assertFalse($this->reader(['allowInsecureEndpoint' => $value])->allowsInsecureEndpoint(), $value);
        }

        $this->assertTrue($this->reader([], ['AMAZONSES_ALLOW_INSECURE_ENDPOINT' => '1'])->allowsInsecureEndpoint());
        // config.ini.php wins over the environment
        $this->assertFalse($this->reader(
            ['allowInsecureEndpoint' => '0'],
            ['AMAZONSES_ALLOW_INSECURE_ENDPOINT' => '1']
        )->allowsInsecureEndpoint());
    }

    public function testValuesAreTrimmed()
    {
        $reader = $this->reader([
            'accessKeyId' => ' AKIA ',
            'senderEmail' => " analytics@example.com\n",
            'timeout' => '30',
        ]);

        $this->assertSame('AKIA', $reader->getAccessKeyId());
        $this->assertSame('analytics@example.com', $reader->getSenderEmail());
        $this->assertSame(30.0, $reader->getTimeout());
    }
}
