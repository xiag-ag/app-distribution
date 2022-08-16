<?php

namespace AppDistributionTool\Tests\unit\Controller;

use AppDistributionTool\Controller\MonitoringController;
use AppDistributionTool\Service\AppLoader;
use AppDistributionTool\Service\CacheManager;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Slim\Psr7\Request;
use Slim\Psr7\Response;
use Twig\Environment;

/**
 * Class AbstractAppControllerTestClass
 * @package AppDistributionTool\Tests\unit\Controller
 */
class AbstractAppControllerTestClass extends TestCase
{
    /**
     * @var MockObject|Request
     */
    protected $requestMock;

    /**
     * @var MockObject|Response
     */
    protected $responseMock;

    /**
     * @var MonitoringController
     */
    protected $monitoringController;

    /**
     * @var MockObject|LoggerInterface
     */
    protected $loggerMock;

    /**
     * @var MockObject|Environment
     */
    protected $twigMock;

    /**
     * @var AppLoader|MockObject
     */
    protected $appLoaderMock;

    /**
     * @var CacheManager|MockObject
     */
    protected $cacheManagerMock;

    /**
     * @var string
     */
    protected $appsDir;

    protected function setUp(): void
    {
        $this->requestMock = $this->createMock(Request::class);
        $this->responseMock = $this->createMock(Response::class);
        $this->loggerMock = $this->createMock(LoggerInterface::class);
        $this->twigMock = $this->createMock(Environment::class);
        $this->appLoaderMock = $this->createMock(AppLoader::class);
        $this->cacheManagerMock = $this->createMock(CacheManager::class);
        $this->appsDir = '';
    }
}
