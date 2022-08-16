<?php

namespace AppDistributionTool\Tests\unit\Service;

use AppDistributionTool\Service\CacheManager;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use PHPUnit\Framework\TestCase;

/**
 * Class CacheManagerTest
 * @package AppDistributionTool\Tests\unit\Service
 */
class CacheManagerTest extends TestCase
{
    /**
     * @var vfsStreamDirectory
     */
    protected $rootDir;

    /**
     * @var CacheManager
     */
    protected $cacheManager;

    /**
     * @var string
     */
    protected $cacheDirectory;

    protected function setUp(): void
    {
        $this->rootDir = vfsStream::setup('root', 0775);
        $this->cacheDirectory = $this->rootDir->url() . '/cache';
        $this->cacheManager = new CacheManager($this->cacheDirectory);

        vfsStream::create(['cache' => []], $this->rootDir);
    }

    public function testCheckCacheExpiredFileNotExists()
    {
        $this->assertTrue($this->cacheManager->checkCacheExpired());
    }

    public function testCheckCacheExpiredByTime()
    {
        $file = vfsStream::newFile('apps.json');
        $file->lastModified(12345);

        $this->rootDir->getChild('cache')->addChild($file);

        $this->assertTrue($this->cacheManager->checkCacheExpired());
    }

    public function testCheckCacheExpiredFalse()
    {
        $this->rootDir->getChild('cache')->addChild(vfsStream::newFile('apps.json'));

        $this->assertFalse($this->cacheManager->checkCacheExpired());
    }

    public function testRemoveCacheExistedFile()
    {
        $this->rootDir->getChild('cache')->addChild(vfsStream::newFile('apps.json'));

        $this->assertTrue($this->cacheManager->removeCache());
    }

    public function testRemoveCacheNotExistedFile()
    {
        $this->assertTrue($this->cacheManager->removeCache());
    }

    public function testRemoveCacheFailure()
    {
        $file = vfsStream::newFile('apps.json', 0);

        $this->rootDir->getChild('cache')->addChild($file);

        $this->assertFalse($this->cacheManager->checkCacheExpired());
    }

    public function testUpdateCacheNotExistedFile()
    {
        $content = 'cache';
        $this->assertEquals(mb_strlen(json_encode($content)), $this->cacheManager->updateCache($content));
    }

    public function testUpdateCacheNotWritableFile()
    {
        $file = vfsStream::newFile('apps.json', 0);

        $this->rootDir->getChild('cache')->addChild($file);

        $this->assertFalse($this->cacheManager->updateCache('cache'));
    }

    public function testGetCacheContentExistedFile()
    {
        $file = vfsStream::newFile('apps.json');
        $content = 'cache';

        $file->setContent(json_encode($content));
        $this->rootDir->getChild('cache')->addChild($file);

        $this->assertEquals($content, $this->cacheManager->getCacheContent());
    }

    public function testGetCacheContentNotExistedFile()
    {
        $this->expectException(\JsonException::class);
        $this->cacheManager->getCacheContent();
    }
}
