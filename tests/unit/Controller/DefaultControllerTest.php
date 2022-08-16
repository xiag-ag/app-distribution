<?php

namespace AppDistributionTool\Tests\unit\Controller;

use AppDistributionTool\Controller\DefaultController;
use AppDistributionTool\Service\AppLoader;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UriInterface;

/**
 * Class DefaultControllerTest
 * @package AppDistributionTool\Tests\unit\Controller
 */
class DefaultControllerTest extends AbstractAppControllerTestClass
{
    /**
     * @var DefaultController
     */
    protected $defaultController;

    protected function setUp(): void
    {
        parent::setUp();

        $this->defaultController = new DefaultController(
            $this->twigMock,
            $this->appLoaderMock,
            $this->appsDir,
            $this->cacheManagerMock,
            [],
            $this->loggerMock
        );
    }

    public function testGetActionWithNoArgs()
    {
        $host = 'host';
        $uriInterfaceMock = $this->createMock(UriInterface::class);
        $streamInterfaceMock = $this->createMock(StreamInterface::class);

        $uriInterfaceMock->expects($this->once())->method('getHost')->willReturn($host);
        $this->requestMock->expects($this->once())->method('getUri')->willReturn($uriInterfaceMock);

        $this->twigMock->expects($this->once())->method('render')->with('pages/index.twig', [
            'apps' => [
                'noGroup' => [
                    'groupName' => 'GROUPNAME',
                    'apps'      => [
                        'fakeApp' => [
                            'name' => '🔒 applications list is forbidden, use direct link',
                        ],
                    ],
                ],
            ],
            'title' => '',
            'isGroupPage' => false,
            'domain' => 'host',
            'identity' => 'undefined',
            'username' => null,
            'sentryLogging' => false,
            'sentryFrontendDSN' => null,
        ]);

        $streamInterfaceMock->expects($this->once())->method('write');
        $this->responseMock->expects($this->once())->method('getBody')->willReturn($streamInterfaceMock);

        $this->assertEquals($this->responseMock, $this->defaultController->getAction(
            $this->requestMock,
            $this->responseMock,
            []
        ));
    }

    public function testGetActionWithAllArgs()
    {
        $host = 'host';
        $group = 'group1';
        $name = 'app1';
        $version = '1.0.0';
        $versions = self::generateAppVersions(1, 1);
        $app = [
            $name => [
                'name' => $name,
                'description' => '',
                'versions' => $versions
            ]
        ];
        $apps = [
            $group => [
                'groupName' => $group,
                'apps' => $app
            ]
        ];

        $this->appLoaderMock->expects($this->once())->method('loadApps')->willReturn($apps);
        $this->appLoaderMock->expects($this->at(1))->method('filterApp')->willReturn($apps);
        $this->appLoaderMock->expects($this->at(2))->method('filterApp')->willReturn($app);
        $this->appLoaderMock->expects($this->once())->method('filterVersion')->willReturn($versions);

        $uriInterfaceMock = $this->createMock(UriInterface::class);
        $streamInterfaceMock = $this->createMock(StreamInterface::class);

        $uriInterfaceMock->expects($this->once())->method('getHost')->willReturn($host);
        $this->requestMock->expects($this->once())->method('getUri')->willReturn($uriInterfaceMock);

        $this->twigMock->expects($this->once())->method('render')->with('pages/index.twig', [
            'apps' => $apps,
            'title' => $name . ' ' . $version . ' - ' . $group,
            'isGroupPage' => false,
            'domain' => 'host',
            'identity' => 'undefined',
            'username' => null,
            'sentryLogging' => false,
            'sentryFrontendDSN' => null,
        ]);

        $streamInterfaceMock->expects($this->once())->method('write');
        $this->responseMock->expects($this->once())->method('getBody')->willReturn($streamInterfaceMock);

        $this->assertEquals($this->responseMock, $this->defaultController->getAction(
            $this->requestMock,
            $this->responseMock,
            [
                'group' => $group,
                'name' => $name,
                'version' => $version
            ]
        ));
    }

    public function testGetActionNotFound()
    {
        $streamInterfaceMock = $this->createMock(StreamInterface::class);
        $streamInterfaceMock->expects($this->once())->method('write')->with('Not found');

        $this->appLoaderMock->expects($this->once())->method('loadApps')->willReturn([]);
        $this->appLoaderMock->expects($this->once())->method('filterApp')->willThrowException(new \Exception());

        $this->loggerMock->expects($this->once())->method('error');
        $this->responseMock->expects($this->once())->method('getBody')->willReturn($streamInterfaceMock);
        $this->responseMock->expects($this->once())->method('withStatus')->with(404)
            ->willReturn($this->returnSelf());
        $this->responseMock->expects($this->once())->method('withHeader')
            ->with('Content-Type', 'text/html')->willReturn($this->returnSelf());

        $this->assertEquals($this->responseMock, $this->defaultController->getAction(
            $this->requestMock,
            $this->responseMock,
            ['group' => 'group1']
        ));
    }

    public function testGetActionGroupPassed()
    {
        $host = 'host';
        $group = 'group1';
        $name = 'app1';
        $versions = self::generateAppVersions(1, 3);
        $app = [
            $name => [
                'name' => $name,
                'description' => '',
                'versions' => $versions
            ]
        ];
        $apps = [
            $group => [
                'groupName' => $group,
                'apps' => $app
            ]
        ];

        $this->appLoaderMock->expects($this->once())->method('loadApps')->willReturn($apps);
        $this->appLoaderMock->expects($this->at(1))->method('filterApp')->willReturn($apps);

        $uriInterfaceMock = $this->createMock(UriInterface::class);
        $streamInterfaceMock = $this->createMock(StreamInterface::class);

        $uriInterfaceMock->expects($this->once())->method('getHost')->willReturn($host);
        $this->requestMock->expects($this->once())->method('getUri')->willReturn($uriInterfaceMock);

        $expectedApps = $apps;
        $expectedApps[$group]['apps'][$name]['versions'] = self::generateAppVersions(1, 1);
        $this->twigMock->expects($this->once())->method('render')->with('pages/index.twig', [
            'apps' => $expectedApps,
            'title' => $group,
            'isGroupPage' => true,
            'domain' => 'host',
            'identity' => 'undefined',
            'username' => null,
            'sentryLogging' => false,
            'sentryFrontendDSN' => null,
        ]);

        $streamInterfaceMock->expects($this->once())->method('write');
        $this->responseMock->expects($this->once())->method('getBody')->willReturn($streamInterfaceMock);

        $this->assertEquals($this->responseMock, $this->defaultController->getAction(
            $this->requestMock,
            $this->responseMock,
            ['group' => $group]
        ));
    }

    protected static function generateAppVersions(int $minVersion, int $maxVersion)
    {
        $versions = [];

        for ($i = $minVersion; $i <= $maxVersion; $i++) {
            $version = $i . '.0.0';
            $versions[$version] = [
                'number' => $version,
                'description' => 'test-' . $i,
                'type' => 'ios',
                'link' => AppLoader::IOS_MANIFEST_FILE_NAME
            ];
        }

        return $versions;
    }
}
