<?php

namespace AppDistributionTool\Tests\unit\Service;

use AppDistributionTool\Service\AppLoader;
use AppDistributionTool\Service\CacheManager;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

/**
 * Class AppLoaderTest
 * @package AppDistributionTool\Tests\unit\Service
 */
class AppLoaderTest extends TestCase
{
    /**
     * @var CacheManager|MockObject
     */
    protected $cacheManagerMock;

    /**
     * @var vfsStreamDirectory
     */
    protected $rootDir;

    /**
     * @var string
     */
    protected $appsDir;

    /**
     * @var AppLoader
     */
    protected $appLoader;

    protected function setUp(): void
    {
        $this->cacheManagerMock = $this->createMock(CacheManager::class);
        $this->rootDir = vfsStream::setup('root', 0775);
        $this->appsDir = $this->rootDir->url() . '/apps';
        $this->appLoader = new AppLoader();

        vfsStream::create([
            'apps' => [
                'group1' => [
                    'app1' => [
                        '1.0.1' => [
                            'test.apk' => 'test',
                            AppLoader::VERSION_RELEASE_NOTES_FILE_NAME => 'test'
                        ],
                        '1.0.2' => ['file_with_another_extension__.zip' => 'test'],
                        '1.0.10' => ['file_with_another_extension__.zip' => 'test'],
                        '2.0.0' => [],
                        '3.0.0' => ['file_with_another_extension__.zip' => 'test'],
                        '4.0.0' => ['file_with_another_extension.zip' => 'test']
                    ],
                    'app2' => [
                        '1.0.0' => [
                            'test.ipa' => 'test',
                            AppLoader::IOS_MANIFEST_FILE_NAME => 'test',
                            AppLoader::VERSION_RELEASE_NOTES_FILE_NAME => 'test',
                        ],
                    ]
                ]
            ]
        ], $this->rootDir);
    }

    public function testLoadAppsCacheExpired()
    {
        $this->cacheManagerMock->method('checkCacheExpired')->willReturn(true);
        $this->cacheManagerMock->method('updateCache')->willReturn(true);

        $apps = $this->appLoader->loadApps($this->cacheManagerMock, $this->appsDir);

        $this->assertEquals($this->getApps(), $apps);
    }

    public function testLoadAppsCacheExists()
    {
        $apps = $this->getApps();
        $this->cacheManagerMock->method('checkCacheExpired')->willReturn(false);
        $this->cacheManagerMock->method('getCacheContent')->willReturn($apps);

        $this->assertEquals($apps, $this->appLoader->loadApps($this->cacheManagerMock, $this->appsDir));
    }

    public function testLoadAppsCacheExpiredUpdateFails()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to update cache');

        $this->cacheManagerMock->method('checkCacheExpired')->willReturn(true);
        $this->cacheManagerMock->method('updateCache')->willReturn(false);

        $this->appLoader->loadApps($this->cacheManagerMock, $this->appsDir);
    }

    public function testCheckLinksForDifferentTypeOfFiles()
    {
        $this->cacheManagerMock->method('checkCacheExpired')->willReturn(true);
        $this->cacheManagerMock->method('updateCache')->willReturn(true);

        $loadedApps = $this->appLoader->loadApps($this->cacheManagerMock, $this->appsDir);
        $this->assertEquals('test.apk', $loadedApps['group1']['apps']['app1']['versions']['1.0.1']['link']);
        $this->assertEquals(null, $loadedApps['group1']['apps']['app1']['versions']['2.0.0']['link']);
        $this->assertEquals('file_with_another_extension__.zip', $loadedApps['group1']['apps']['app1']['versions']['3.0.0']['link']);
        $this->assertEquals(null, $loadedApps['group1']['apps']['app1']['versions']['4.0.0']['link']);
        $this->assertEquals(AppLoader::IOS_MANIFEST_FILE_NAME, $loadedApps['group1']['apps']['app2']['versions']['1.0.0']['link']);
    }

    public function testFilterAppFound()
    {
        $apps = $this->getApps();

        $this->assertEquals($apps, $this->appLoader->filterApp($apps, 'group1'));
    }

    public function testFilterAppNotFound()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('App not found');

        $this->appLoader->filterApp($this->getApps(), 'group123');
    }

    public function testFilterVersionFound()
    {
        $apps = $this->getApps();

        $this->assertEquals(
            $apps['group1']['apps']['app2']['versions'],
            $this->appLoader->filterVersion($apps['group1']['apps']['app2'], '1.0.0')
        );
    }

    public function testVersionsSorting()
    {
        $this->cacheManagerMock->method('checkCacheExpired')->willReturn(true);
        $this->cacheManagerMock->method('updateCache')->willReturn(true);
        $loadedVersions = $this->appLoader->loadApps($this->cacheManagerMock, $this->appsDir);
        $loadedVersions = array_keys($loadedVersions['group1']['apps']['app1']['versions']);
        
        self::assertSame(['4.0.0', '3.0.0', '2.0.0', '1.0.10', '1.0.2', '1.0.1'], $loadedVersions);

    }

    public function testFilterVersionNotFound()
    {
        $this->expectException(\Exception::class);
        $this->expectExceptionMessage('Version not found');

        $this->appLoader->filterVersion($this->getApps()['group1']['apps']['app2'], '123.0.0');
    }

    protected function getApps()
    {
        return [
            'group1' => [
                'groupName' => 'group1',
                'apps' => [
                    'app2' => [
                        'name' => 'app2',
                        'description' => '',
                        'versions' => [
                            '1.0.0' => [
                                'number' => '1.0.0',
                                'description' => 'test',
                                'type' => 'ios',
                                'link' => AppLoader::IOS_MANIFEST_FILE_NAME
                            ]
                        ],
                    ],
                    'app1' => [
                        'name' => 'app1',
                        'description' => '',
                        'versions' => [
                            '1.0.1' => [
                                'number' => '1.0.1',
                                'description' => 'test',
                                'type' => 'android',
                                'link' => 'test.apk'
                            ],
                            '1.0.2' => [
                                'number' => '1.0.2',
                                'description' => '',
                                'type' => 'android',
                                'link' => 'file_with_another_extension__.zip'
                            ],
                            '1.0.10' => [
                                'number' => '1.0.10',
                                'description' => '',
                                'type' => 'android',
                                'link' => 'file_with_another_extension__.zip'
                            ],
                            '2.0.0' => [
                                'number' => '2.0.0',
                                'description' => '',
                                'type' => 'android',
                                'link' => null
                            ],
                            '3.0.0' => [
                                'number' => '3.0.0',
                                'description' => '',
                                'type' => 'android',
                                'link' => 'file_with_another_extension__.zip'
                            ],
                            '4.0.0' => [
                                'number' => '4.0.0',
                                'description' => '',
                                'type' => 'android',
                                'link' => null
                            ]
                        ],
                    ]
                ]
            ]
        ];
    }
}
