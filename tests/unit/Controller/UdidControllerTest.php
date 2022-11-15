<?php

namespace AppDistributionTool\Tests\unit\Controller;

use AppDistributionTool\Controller\UdidController;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use org\bovigo\vfs\vfsStreamFile;
use Psr\Http\Message\StreamInterface;

/**
 * Class UdidControllerTest
 * @package AppDistributionTool\Tests\unit\Controller
 */
class UdidControllerTest extends AbstractAppControllerTestClass
{
    /**
     * @var UdidController
     */
    protected $udidController;

    /**
     * @var vfsStreamDirectory
     */
    protected $vfs;

    protected function setUp(): void
    {
        parent::setUp();

        $this->vfs = vfsStream::setup();

        $this->udidController = new UdidController(
            $this->twigMock,
            $this->appLoaderMock,
            $this->vfs->url(),
            $this->cacheManagerMock,
            [],
            $this->loggerMock
        );
    }

    public function testGetResultsAction()
    {
        $content = 'content';
        $udid = 'UDID';
        $deviceProduct = 'DEVICE_PRODUCT';
        $deviceVersion = 'DEVICE_VERSION';
        $streamInterfaceMock = $this->createMock(StreamInterface::class);
        $body = <<<TEXT
Hello

This is my UDID: {$udid}
Device product: {$deviceProduct}
System version: {$deviceVersion}

Please rebuild the app and notify me.

Thanks!
TEXT;

        $this->twigMock->expects($this->once())->method('render')
            ->with('pages/udid/index.twig', [
                'subject' => 'This is my UDID from iOS device',
                'body' => str_replace(array("\r\n", "\r", "\n"), "%0D%0A", $body),
                'UDID' => $udid,
                'DEVICE_PRODUCT' => $deviceProduct,
                'DEVICE_VERSION' => $deviceVersion,
                'stepTemplate' => 'pages/udid/_step2.twig',
                'developer' => null
            ])
            ->willReturn($content);

        $streamInterfaceMock->expects($this->once())->method('write')->with($content);
        $this->responseMock->expects($this->once())->method('getBody')->willReturn($streamInterfaceMock);

        $this->requestMock->method('getQueryParams')->willReturn([
            'DEVICE_PRODUCT' => $deviceProduct,
            'UDID' => $udid,
            'DEVICE_VERSION' => $deviceVersion
        ]);

        $this->assertEquals(
            $this->responseMock,
            $this->udidController->getResultsAction($this->requestMock, $this->responseMock)
        );
    }

    public function testGetSubmitAction()
    {
        $streamInterfaceMock = $this->createMock(StreamInterface::class);
        $parsedBody = <<<TEXT
<?xml version="1.0" encoding="UTF-8"?>
<!DOCTYPE plist PUBLIC "-//Apple//DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0">
<dict>
	<key>DEVICE_NAME</key>
	<string>Mac test</string>
	<key>NotOnConsole</key>
	<false/>
	<key>PRODUCT</key>
	<string>Mactest1</string>
	<key>UDID</key>
	<string>123</string>
	<key>UserID</key>
	<string>456</string>
	<key>UserLongName</key>
	<string>Test</string>
	<key>UserShortName</key>
	<string>Test</string>
	<key>VERSION</key>
	<string>123456</string>
</dict>
</plist>
TEXT;

        $streamInterfaceMock->expects($this->once())->method('getContents')->willReturn($parsedBody);
        $this->requestMock->expects($this->once())->method('getBody')->willReturn($streamInterfaceMock);
        $this->responseMock->method('withStatus')->with(301)->willReturnSelf();
        $this->responseMock->method('withHeader')
            ->with('Location', '/udid/results?DEVICE_PRODUCT=Mactest1&UDID=123&DEVICE_VERSION=123456')
            ->willReturnSelf();

        $this->assertEquals(
            $this->responseMock,
            $this->udidController->getSubmitAction($this->requestMock, $this->responseMock)
        );
    }

    public function testGetAction()
    {
        $configFile = new vfsStreamFile('udid.mobileconfig');
        $this->vfs->addChild($configFile);

        $content = 'content';
        $streamInterfaceMock = $this->createMock(StreamInterface::class);

        $streamInterfaceMock->expects($this->once())->method('write')->with($content);
        $this->responseMock->expects($this->once())->method('getBody')->willReturn($streamInterfaceMock);

        $this->twigMock->expects($this->once())->method('render')
            ->with('pages/udid/index.twig', ['stepTemplate' => 'pages/udid/_step1.twig'])
            ->willReturn($content);

        $this->assertEquals(
            $this->responseMock,
            $this->udidController->getAction($this->requestMock, $this->responseMock)
        );
    }

    public function testGenerateUdidMobileConfig()
    {
        $this->twigMock->expects($this->at(0))->method('render')
            ->with('udid_config.twig', ['appHost' => null, 'organization' => null, 'uuid' => null]);

        self::assertFalse($this->vfs->hasChild('udid.mobileconfig'));

        $this->udidController->getAction($this->requestMock, $this->responseMock);

        self::assertTrue($this->vfs->hasChild('udid.mobileconfig'));
    }
}
