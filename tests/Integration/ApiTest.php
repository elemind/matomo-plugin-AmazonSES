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
use Piwik\Plugins\AmazonSES\Controller;
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

    public function testGetStatusReportsInvalidEndpointWithoutShowingIt()
    {
        $status = $this->statusWith(['endpoint' => 'https://AKIA:s3cr3t@ses.example.com']);

        $this->assertNull($status['endpoint']);
        $this->assertStringContainsString('must not contain credentials', $status['configError']);
        $this->assertStringNotContainsString('s3cr3t', $status['configError']);
        $this->assertFalse($status['insecureEndpoint']);
    }

    public function testGetStatusRejectsPlainHttpEndpointByDefault()
    {
        $status = $this->statusWith(['endpoint' => 'http://ses-mock:8005']);

        $this->assertNull($status['endpoint']);
        $this->assertStringContainsString('allowInsecureEndpoint', $status['configError']);
    }

    public function testGetStatusWarnsAboutAllowedPlainHttpEndpoint()
    {
        $status = $this->statusWith(['endpoint' => 'http://ses-mock:8005', 'allowInsecureEndpoint' => '1']);

        $this->assertSame('http://ses-mock:8005', $status['endpoint']);
        $this->assertNull($status['configError']);
        $this->assertTrue($status['insecureEndpoint']);
        $this->assertEmpty($this->factory->http->requests);
    }

    public function testGetStatusWithDefaultEndpointIsEncrypted()
    {
        $status = API::getInstance()->getStatus();

        $this->assertNull($status['configError']);
        $this->assertFalse($status['insecureEndpoint']);
    }

    private function statusWith(array $config): array
    {
        $this->factory = new TestSesClientFactory($config);
        StaticContainer::getContainer()->set(SesClientFactory::class, $this->factory);

        return (new API($this->factory))->getStatus();
    }

    public function testSendTestEmailRejectsInvalidRecipient()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('AmazonSES_InvalidTestRecipient');

        API::getInstance()->sendTestEmail('not-an-email');
    }

    public function provideNonSuperUsers(): array
    {
        $calls = [
            'getStatus' => function () {
                return API::getInstance()->getStatus();
            },
            'sendTestEmail' => function () {
                return API::getInstance()->sendTestEmail('admin@example.com');
            },
            'Controller::index' => function () {
                return StaticContainer::get(Controller::class)->index();
            },
        ];
        $users = [
            'site admin' => function () {
                FakeAccess::clearAccess(false, [1], [], 'siteAdmin');
            },
            'view only' => function () {
                FakeAccess::clearAccess(false, [], [1], 'viewer');
            },
            'anonymous' => function () {
                FakeAccess::clearAccess(false, [], [], 'anonymous');
            },
        ];

        $cases = [];
        foreach ($calls as $callName => $call) {
            foreach ($users as $userName => $user) {
                $cases[$callName . ' as ' . $userName] = [$user, $call];
            }
        }

        return $cases;
    }

    /**
     * @dataProvider provideNonSuperUsers
     */
    public function testRequiresSuperUser(callable $setUser, callable $call)
    {
        $setUser();

        $this->expectException(\Piwik\NoAccessException::class);

        $call();
    }

    public function testSendTestEmailDoesNotSendForNonSuperUser()
    {
        FakeAccess::clearAccess(false, [1], [], 'siteAdmin');

        try {
            API::getInstance()->sendTestEmail('admin@example.com');
            $this->fail('Expected NoAccessException');
        } catch (\Piwik\NoAccessException $e) {
            $this->assertEmpty($this->factory->http->requests);
        }
    }

    public function provideContainerConfig()
    {
        return [
            'Piwik\Access' => new FakeAccess(),
            SesClientFactory::class => new TestSesClientFactory(),
        ];
    }
}
