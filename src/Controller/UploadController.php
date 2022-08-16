<?php

namespace AppDistributionTool\Controller;

use AppDistributionTool\Service\AppLoader;
use AppDistributionTool\Service\CacheManager;
use AppDistributionTool\Service\FileManager;
use Psr\Http\Message\MessageInterface;
use Psr\Log\LoggerInterface;
use Slim\Psr7\Request;
use Slim\Psr7\Response;
use Twig\Environment;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

/**
 * Class UploadController
 * @package AppDistributionTool\Controller
 */
class UploadController extends AbstractAppController
{
    /**
     * @var FileManager
     */
    protected $fileManager;

    /**
     * UploadController constructor.
     * @param Environment $environment
     * @param AppLoader $appLoader
     * @param string $appsDirectory
     * @param CacheManager $cacheManager
     * @param array $configuration
     * @param LoggerInterface|null $logger
     * @param FileManager $fileManager
     */
    public function __construct(
        Environment $environment,
        AppLoader $appLoader,
        string $appsDirectory,
        CacheManager $cacheManager,
        array $configuration,
        LoggerInterface $logger,
        FileManager $fileManager
    ) {
        parent::__construct($environment, $appLoader, $appsDirectory, $cacheManager, $configuration, $logger);

        $this->fileManager = $fileManager;
    }

    /**
     * @param Request $request
     * @param Response $response
     *
     * @return Response
     *
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     * @throws \JsonException
     */
    public function getAction(Request $request, Response $response): Response
    {
        $response->getBody()->write(
            $this->renderPageTemplate('pages/upload.twig', [
                'apps' => $this->appLoader->loadApps($this->cacheManager, $this->appsDirectory)
            ])
        );

        return $response;
    }

    /**
     * @param Request $request
     * @param Response $response
     *
     * @return MessageInterface
     */
    public function postAction(Request $request, Response $response): MessageInterface
    {
        try {
            //--------------------------validate fields--------------------------//
            $errors = [];
            $file = self::getUploadedFile($request, 'file');
            $fileExtension = pathinfo($file->getClientFilename(), PATHINFO_EXTENSION);

            if (!$group = $request->getParsedBody()['group']) {
                throw new \RuntimeException('Missing required field: group');
            }

            if (!$app = $request->getParsedBody()['app']) {
                throw new \RuntimeException('Missing required field: app');
            }

            if (!$version = $request->getParsedBody()['version']) {
                throw new \RuntimeException('Missing required field: version');
            }

            $info = $request->getParsedBody()['info'];

            if ($fileExtension === 'ipa') {
                if (!$bundle = $request->getParsedBody()['bundle']) {
                    throw new \RuntimeException('Missing required field: bundle');
                }

                if (!$title = $request->getParsedBody()['title']) {
                    throw new \RuntimeException('Missing required field: title');
                }
            }

            //--------------------------create directory, upload file--------------------------//
            $fileDirectory = $group . '/' . $app . '/' . $version;

            if (!$existedGroup = $this->appLoader->loadApps($this->cacheManager, $this->appsDirectory)[$group]) {
                $createdDirectory = $group;
            } else if (!$existedApp = $existedGroup['apps'][$app]) {
                $createdDirectory = $group . '/' . $app;
            } else if (!$existedApp['versions'][$version]) {
                $createdDirectory = $fileDirectory;
            }

            if (!$this->fileManager->createDirectory($fileDirectory)) {
                throw new \RuntimeException('Directory "' . $fileDirectory . '" was not created');
            }

            $fileName = $file->getClientFilename();

            if ($fileExtension === 'ipa') {
                $appUrl = 'https://' . $request->getUri()->getHost() . '/apps/' . $group . '/' . $app . '/' . $version
                    . '/' . $file->getClientFilename();

                $this->generateManifestFile($appUrl, $bundle, $version, $title, $fileDirectory);
            } elseif ($fileExtension !== 'apk') {
                preg_match('/(.*)\.[^.]*$/', $file->getClientFilename(), $matches);
                $fileName = $matches[1] . '__.' . $fileExtension;
            }

            $file->moveTo($this->appsDirectory . '/' . $fileDirectory . '/' . $fileName);

            if (!empty($info) && !$this->fileManager->writeToFile(
                $fileDirectory . '/' . AppLoader::VERSION_RELEASE_NOTES_FILE_NAME, $info)
            ) {
                $this->logger->error(
                    'Failed to close file ' . $fileDirectory . '/' . AppLoader::VERSION_RELEASE_NOTES_FILE_NAME
                );
            }

            //--------------------------clear cache--------------------------//
            if (!$this->cacheManager->removeCache()) {
                $this->logger->error('Failed to delete cache file');
                $errors[] = 'The file was downloaded successfully, but the cache was not cleared';
            }

            $response->getBody()->write(json_encode(['code' => 201, 'result' => 'OK']));
        } catch (\Throwable $exception) {
            $errors[] = $exception->getMessage();

            if ($this->configuration['sentryLogging']) {
                \Sentry\captureException($exception);
            }

            $this->logger->error($exception->getMessage(), [
                'clientData' => $request->getParsedBody(),
                'errorTrace' => $exception->getTraceAsString()
            ]);

            // revert made changes
            if (isset($createdDirectory) && $this->fileManager->removeDirectory($createdDirectory) !== '0') {
                $this->logger->error('Failed to remove created directory ' . $createdDirectory);
            }

            $response->getBody()->write(json_encode(['code' => 500, 'result' => $errors]));
        }

        return $response->withHeader('Content-Type', 'application/json');
    }

    /**
     * @param string $path
     * @param string $bundleId
     * @param string $version
     * @param string $title
     * @param string $directory
     *
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    protected function generateManifestFile(
        string $path,
        string $bundleId,
        string $version,
        string $title,
        string $directory
    ): void {
        $manifestContent = $this->environment->render('ios_manifest.twig', [
            'path' => $path,
            'bundleId' => $bundleId,
            'version' => $version,
            'title' => $title
        ]);

        if (!$this->fileManager->writeToFile(
            $directory . '/' . AppLoader::IOS_MANIFEST_FILE_NAME, $manifestContent)
        ) {
            $this->logger->error('Failed to close file ' . $directory . '/' . AppLoader::IOS_MANIFEST_FILE_NAME);
        }
    }
}
