<?php

namespace AppDistributionTool\Tests\unit\Service;

use AppDistributionTool\Service\AppLoader;
use AppDistributionTool\Service\FileManager;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use PHPUnit\Framework\TestCase;

/**
 * Class FileManagerTest
 * @package AppDistributionTool\Tests\unit\Service
 */
class FileManagerTest extends TestCase
{
    /**
     * @var vfsStreamDirectory
     */
    protected $rootDir;

    /**
     * @var FileManager
     */
    protected $fileManager;

    /**
     * @var string
     */
    protected $fileManagerRootDir;

    protected function setUp(): void
    {
        $this->rootDir = vfsStream::setup('root', 0775);
        $this->fileManagerRootDir = $this->rootDir->url() . '/apps';
        $this->fileManager = new FileManager($this->fileManagerRootDir);

        vfsStream::create([
            'apps' => [
                'group1' => [
                    'app1' => [
                        '1.0.0' => [
                            AppLoader::VERSION_RELEASE_NOTES_FILE_NAME => ''
                        ]
                    ]
                ]
            ]
        ], $this->rootDir);
    }

   public function testCreateDirectory()
    {
        $path = '/group1/app2/2.0.0';

        $this->assertTrue($this->fileManager->createDirectory($path));
        $this->assertTrue(file_exists($this->fileManagerRootDir . $path));
    }

    public function testWriteToFile()
    {
        $fileName = 'group1/app1/1.0.0/release.md';
        $content = 'Test';
        $file = vfsStream::newFile($fileName);

        $this->rootDir->getChild('apps')->addChild($file);

        $this->assertTrue($this->fileManager->writeToFile($fileName, $content));
        $this->assertSame($content, file_get_contents($file->url()));
    }

    public function testWriteToFileOpenFails()
    {
        $fileName = 'group1/app1/2.0.0/release.md';
        $file = vfsStream::newFile($fileName, 0444);

        $this->rootDir->getChild('apps')->addChild($file);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Failed to open file ' . $file->url());

        $this->fileManager->writeToFile($fileName, 'Test');
    }

    public function testRemoveNonExistedDirectory()
    {
        $this->assertSame('0', $this->fileManager->removeDirectory('/group1/app1/10.0.0'));
    }

    public function testRemoveDirectoryRootDir()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Unable to delete root directory');

        $this->fileManager->removeDirectory('/');
    }

    public function testRemoveNearestDirectoryToRootDir()
    {
        $this->assertSame('0', $this->fileManager->removeDirectory('/group1'));
        $this->assertSame('0', $this->fileManager->removeDirectory('/group1/'));
    }

    public function testRemoveDirectoryOutOfRootDir()
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Wrong path to file/directory: getting out of root directory');

        $this->fileManager->removeDirectory('/../');
    }

    public function testRemoveExistedDirectory()
    {
        $this->assertSame('0', $this->fileManager->removeDirectory('/group1/app1/'));
    }
}
