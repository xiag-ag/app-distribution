<?php

namespace AppDistributionTool\Tests\unit\Controller;

use AppDistributionTool\Controller\UploadController;
use AppDistributionTool\Service\FileManager;
use PHPUnit\Framework\MockObject\MockObject;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UploadedFileInterface;
use Psr\Http\Message\UriInterface;

/**
 * Class UploadControllerTest
 * @package AppDistributionTool\Tests\unit\Controller
 */
class UploadControllerTest extends AbstractAppControllerTestClass
{
    /**
     * @var UploadController
     */
    protected $uploadController;

    /**
     * @var FileManager|MockObject
     */
    protected $fileManagerMock;

    protected function setUp(): void
    {
        parent::setUp();

        $this->fileManagerMock = $this->createMock(FileManager::class);
        $this->uploadController = new UploadController(
            $this->twigMock,
            $this->appLoaderMock,
            $this->appsDir,
            $this->cacheManagerMock,
            [],
            $this->loggerMock,
            $this->fileManagerMock
        );
    }

    public function testGetAction()
    {
        $serverResponse = true;
        $errors = $apps = [];
        $streamInterfaceMock = $this->createMock(StreamInterface::class);

        $streamInterfaceMock->expects($this->once())->method('write');
        $this->responseMock->expects($this->once())->method('getBody')->willReturn($streamInterfaceMock);

        $this->appLoaderMock->expects($this->once())->method('loadApps')
            ->with($this->cacheManagerMock, $this->appsDir)->willReturn($apps);
        $this->requestMock->method('getQueryParams')->willReturn([
            'errors' => $errors,
            'serverResponse' => $serverResponse
        ]);
        $this->twigMock->expects($this->once())->method('render')->with('pages/upload.twig', [
            'apps' => $apps,
            'identity' => 'undefined',
            'username' => null,
            'sentryLogging' => false,
            'sentryFrontendDSN' => null
        ]);

        $this->assertEquals(
            $this->responseMock,
            $this->uploadController->getAction($this->requestMock, $this->responseMock)
        );
    }

    public function testPostActionMissingUploadedFile()
    {
        $this->requestMock->expects($this->once())->method('getUploadedFiles')->willReturn([]);
        $this->requestMock->expects($this->once())->method('getParsedBody')->willReturn('');
        $this->loggerMock->expects($this->once())->method('error')->with('Missing file to upload');
        $this->responseMock->expects($this->once())->method('withHeader')->with(
            'Content-Type', 'application/json'
        )->willReturn($this->responseMock);

        $this->assertEquals(
            $this->responseMock,
            $this->uploadController->postAction($this->requestMock, $this->responseMock)
        );
    }

    public function testPostActionUploadedFileError()
    {
        $uploadedFileInterfaceMock = $this->createMock(UploadedFileInterface::class);

        $uploadedFileInterfaceMock->method('getError')->willReturn(':(');
        $this->requestMock->expects($this->once())->method('getUploadedFiles')->willReturn([
            'file' => $uploadedFileInterfaceMock
        ]);
        $this->requestMock->expects($this->once())->method('getParsedBody')->willReturn('');
        $this->loggerMock->expects($this->once())->method('error')
            ->with('Failed to transfer file to server');
        $this->responseMock->expects($this->once())->method('withHeader')->with(
            'Content-Type', 'application/json'
        )->willReturn($this->responseMock);

        $this->assertEquals(
            $this->responseMock,
            $this->uploadController->postAction($this->requestMock, $this->responseMock)
        );
    }

    /**
     * @dataProvider getFields
     *
     * @param $fieldName
     * @param $fields
     */
    public function testPostActionMissingField($fieldName, $fields)
    {
        $uploadedFileInterfaceMock = $this->createMock(UploadedFileInterface::class);

        $uploadedFileInterfaceMock->method('getError')->willReturn(UPLOAD_ERR_OK);
        $uploadedFileInterfaceMock->method('getClientFilename')->willReturn('test.ipa');
        $this->requestMock->expects($this->once())->method('getUploadedFiles')->willReturn([
            'file' => $uploadedFileInterfaceMock
        ]);
        $this->requestMock->method('getParsedBody')->willReturn($fields);
        $this->loggerMock->expects($this->once())->method('error')
            ->with('Missing required field: ' . $fieldName);
        $this->responseMock->expects($this->once())->method('withHeader')->with(
            'Content-Type', 'application/json'
        )->willReturn($this->responseMock);

        $this->assertEquals(
            $this->responseMock,
            $this->uploadController->postAction($this->requestMock, $this->responseMock)
        );
    }
    
    /**
     * @return array
     */
    public function getFields(): array
    {
        return [
            ['group', []],
            ['app', ['group' => '1']],
            ['version', ['group' => '1', 'app' => '1']],
            ['bundle', ['group' => '1', 'app' => '1', 'version' => '1']],
            ['title', ['group' => '1', 'app' => '1', 'version' => '1', 'bundle' => '1']]
        ];
    }

    public function testPostActionFailedToCreateDirectory()
    {
        $uploadedFileInterfaceMock = $this->createMock(UploadedFileInterface::class);

        $uploadedFileInterfaceMock->method('getError')->willReturn(UPLOAD_ERR_OK);
        $uploadedFileInterfaceMock->method('getClientFilename')->willReturn('test.apk');
        $this->requestMock->expects($this->once())->method('getUploadedFiles')->willReturn([
            'file' => $uploadedFileInterfaceMock
        ]);
        $this->requestMock->method('getParsedBody')->willReturn([
            'group' => 'group', 'app' => 'app', 'version' => 'version'
        ]);

        $this->appLoaderMock->expects($this->once())->method('loadApps')->willReturn([]);
        $this->fileManagerMock->expects($this->once())->method('createDirectory')->willReturn(false);
        $this->fileManagerMock->expects($this->once())->method('removeDirectory')
            ->with('group')->willReturn(':(');

        $this->loggerMock->method('error');
        //    ->with('Directory "group/app/version" was not created');
        $this->responseMock->expects($this->once())->method('withHeader')->with(
            'Content-Type', 'application/json'
        )->willReturn($this->responseMock);

        $this->assertEquals(
            $this->responseMock,
            $this->uploadController->postAction($this->requestMock, $this->responseMock)
        );
    }

    public function testPostActionFailedToCloseFilesAndClearCache()
    {
        $uploadedFileInterfaceMock = $this->createMock(UploadedFileInterface::class);
        $uriInterfaceMock = $this->createMock(UriInterface::class);

        $uploadedFileInterfaceMock->method('getError')->willReturn(UPLOAD_ERR_OK);
        $uploadedFileInterfaceMock->method('getClientFilename')->willReturn('test.ipa');
        $uploadedFileInterfaceMock->method('moveTo');

        $this->twigMock->expects($this->once())->method('render')
            ->with('ios_manifest.twig', [
                'path' => 'https://host/apps/group/app/version/test.ipa',
                'bundleId' => 'bundle',
                'version' => 'version',
                'title' => 'title'
            ])
            ->willReturn('manifestContent');

        $uriInterfaceMock->expects($this->once())->method('getHost')->willReturn('host');

        $this->requestMock->method('getUri')->willReturn($uriInterfaceMock);
        $this->requestMock->expects($this->once())->method('getUploadedFiles')->willReturn([
            'file' => $uploadedFileInterfaceMock
        ]);
        $this->requestMock->method('getParsedBody')->willReturn([
            'group' => 'group',
            'app' => 'app',
            'version' => 'version',
            'bundle' => 'bundle',
            'title' => 'title',
            'info' => 'info'
        ]);

        $this->appLoaderMock->expects($this->once())->method('loadApps')->willReturn(['apps' => []]);
        $this->fileManagerMock->method('writeToFile');
        /*    ->with('group/app/version/release.md', 'info')->willReturn(false);
        $this->fileManagerMock->expects($this->at(0))->method('writeToFile')
            ->with('group/app/version/manifest.plist', 'manifestContent')->willReturn(false);*/
        $this->fileManagerMock->expects($this->once())->method('createDirectory')->willReturn(true);

        $this->loggerMock->method('error');
        /*    ->with('Failed to close file group/app/version/manifest.plist');
        $this->loggerMock->expects($this->at(3))->method('error')
            ->with('Failed to close file group/app/version/release.md');
        $this->loggerMock->expects($this->at(4))->method('error')
            ->with('Failed to delete cache file');*/

        $this->cacheManagerMock->expects($this->once())->method('removeCache')->willReturn(false);

        $this->responseMock->expects($this->once())->method('withHeader')->with(
            'Content-Type', 'application/json'
        )->willReturn($this->responseMock);

        $this->assertEquals(
            $this->responseMock,
            $this->uploadController->postAction($this->requestMock, $this->responseMock)
        );
    }
}
