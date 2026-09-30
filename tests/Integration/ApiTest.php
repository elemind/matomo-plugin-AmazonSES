<?php

/**
 * AmazonSES plugin for Matomo
 *
 * @link    https://github.com/elemind/matomo-plugin-AmazonSES
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */

namespace Piwik\Plugins\AmazonSES\tests\Integration;

use Piwik\Container\StaticContainer;
use Piwik\Plugins\AmazonSES\API;
use Piwik\Plugins\AmazonSES\Aws\Http\HttpResponse;
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

    public function testGetStatus()
    {
        $this->factory->http->queue(new HttpResponse(200, [], json_encode([
            'ProductionAccessEnabled' => false,
            'SendingEnabled' => true,
            'SendQuota' => ['Max24HourSend' => 200, 'MaxSendRate' => 1, 'SentLast24Hours' => 5],
        ])));

        $status = API::getInstance()->getStatus();

        $this->assertSame('eu-west-1', $status['region']);
        $this->assertSame('settings', $status['credentialsSource']);
        $this->assertNull($status['error']);
        $this->assertFalse($status['account']['productionAccessEnabled']);
        $this->assertSame(5, $status['account']['sentLast24Hours']);
    }

    public function testGetStatusReportsErrorsInsteadOfThrowing()
    {
        $this->factory->http->queue(new HttpResponse(403, [], '{"message":"The security token included in the request is invalid."}'));

        $status = API::getInstance()->getStatus();

        $this->assertNull($status['account']);
        $this->assertStringContainsString('security token', $status['error']);
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
