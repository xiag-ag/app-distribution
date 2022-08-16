<?php

namespace AppDistributionTool\Tests\unit\Controller;

use AppDistributionTool\Controller\MonitoringController;
use Psr\Http\Message\StreamInterface;

/**
 * Class MonitoringControllerTest
 * @package AppDistributionTool\Tests\unit\Controller
 */
class MonitoringControllerTest extends AbstractAppControllerTestClass
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->monitoringController = new MonitoringController(
            $this->twigMock,
            $this->appLoaderMock,
            $this->appsDir,
            $this->cacheManagerMock,
            [],
            $this->loggerMock
        );
    }

    public function testGetActionAppsExists()
    {
        $this->appLoaderMock->expects($this->once())->method('loadApps')->willReturn([[], []]);

        $streamInterfaceMock = $this->createMock(StreamInterface::class);
        $streamInterfaceMock->expects($this->once())->method('write')->with('OK');

        $this->responseMock->expects($this->once())->method('getBody')->willReturn($streamInterfaceMock);

        $this->assertEquals(
            $this->responseMock,
            $this->monitoringController->getAction($this->requestMock, $this->responseMock)
        );
    }

    public function testGetActionAppsNotExists()
    {
        $this->appLoaderMock->expects($this->once())->method('loadApps')->willReturn([]);

        $streamInterfaceMock = $this->createMock(StreamInterface::class);
        $streamInterfaceMock->expects($this->once())->method('write')->with('No applications found');

        $this->responseMock->expects($this->once())->method('getBody')->willReturn($streamInterfaceMock);
        $this->responseMock->expects($this->once())->method('withStatus')->with(404)
            ->willReturn($this->returnSelf());
        $this->responseMock->expects($this->once())->method('withHeader')
            ->with('Content-Type', 'text/html')->willReturn($this->returnSelf());

        $this->assertEquals(
            $this->responseMock,
            $this->monitoringController->getAction($this->requestMock, $this->responseMock)
        );
    }
}
