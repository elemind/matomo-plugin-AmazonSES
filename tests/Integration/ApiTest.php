<?php

/**
 * AmazonSES plugin for Matomo
 *
 * @link    https://github.com/elemind/matomo-plugin-AmazonSES
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\AmazonSES\tests\Integration;

use Piwik\Config;
use Piwik\Container\StaticContainer;
use Piwik\Plugins\AmazonSES\API;
use Piwik\Plugins\AmazonSES\Mail\SesTransport;
use Piwik\Plugins\AmazonSES\SesClientFactory;
use Piwik\Plugins\AmazonSES\tests\Framework\TestSesClientFactory;
use Piwik\Tests\Framework\Mock\FakeAccess;
use Piwik\Tests\Framework\TestCase\IntegrationTestCase;

/**
 * @group AmazonSES
 * @group Plugins
 */
class ApiTest extends IntegrationTestCase
{
    /** @var TestSesClientFactory */
    private $factory;

    public function setUp(): void
    {
        parent::setUp();
        FakeAccess::$superUser = true;
        $this->factory = StaticContainer::get(SesClientFactory::class);
    }

    public function testPluginReplacesCoreTransport()
    {
        $this->assertInstanceOf(SesTransport::class, StaticContainer::get('Piwik\Mail\Transport'));
    }

    public function testGetStatusMakesNoAwsCall()
    {
        $status = API::getInstance()->getStatus();

        $this->assertSame('eu-west-1', $status['region']);
        $this->assertSame('settings', $status['credentialsSource']);
        $this->assertNull($status['error']);
        $this->assertArrayNotHasKey('account', $status);
        $this->assertEmpty($this->factory->http->requests);
    }

    public function testGetStatusWarnsAboutIgnoredSmtpServer()
    {
        $this->assertNull(API::getInstance()->getStatus()['smtpHost']);

        Config::getInstance()->mail['transport'] = 'smtp';
        Config::getInstance()->mail['host'] = 'smtp.example.com';

        $this->assertSame('smtp.example.com', API::getInstance()->getStatus()['smtpHost']);
    }

    public function testGetStatusReportsMissingCredentials()
    {
        StaticContainer::getContainer()->set(SesClientFactory::class, new TestSesClientFactory([
            'accessKeyId' => '',
            'secretAccessKey' => '',
        ]));

        $status = (new API(StaticContainer::get(SesClientFactory::class)))->getStatus();

        $this->assertNull($status['credentialsSource']);
        $this->assertStringContainsString('No AWS credentials found', $status['error']);
    }

    public function testSendTestEmailRejectsInvalidRecipient()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('AmazonSES_InvalidTestRecipient');

        API::getInstance()->sendTestEmail('not-an-email');
    }

    public function testRequiresSuperUser()
    {
        FakeAccess::$superUser = false;
        FakeAccess::$idSitesAdmin = [1];

        $this->expectException(\Piwik\NoAccessException::class);

        API::getInstance()->getStatus();
    }

    public function provideContainerConfig()
    {
        return [
            'Piwik\Access' => new FakeAccess(),
            SesClientFactory::class => new TestSesClientFactory(),
        ];
    }
}
