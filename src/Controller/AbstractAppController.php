<?php

namespace AppDistributionTool\Controller;

use AppDistributionTool\Service\AppLoader;
use AppDistributionTool\Service\CacheManager;
use Psr\Http\Message\UploadedFileInterface;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Slim\Psr7\Request;
use Twig\Environment;

/**
 * Class AbstractAppController
 * @package AppDistributionTool\Controller
 */
abstract class AbstractAppController
{
    /**
     * @var LoggerInterface
     */
    protected $logger;

    /**
     * @var Environment
     */
    protected $environment;

    /**
     * @var AppLoader
     */
    protected $appLoader;

    /**
     * @var string
     */
    protected $appsDirectory;

    /**
     * @var CacheManager
     */
    protected $cacheManager;

    /**
     * @var array
     */
    protected $configuration;

    /**
     * AbstractAppController constructor.
     *
     * @param Environment $environment
     * @param AppLoader $appLoader
     * @param string $appsDirectory
     * @param CacheManager $cacheManager
     * @param array $configuration
     * @param LoggerInterface|null $logger
     */
    public function __construct(
        Environment $environment,
        AppLoader $appLoader,
        string $appsDirectory,
        CacheManager $cacheManager,
        array $configuration,
        LoggerInterface $logger = null
    ) {
        $this->environment = $environment;
        $this->appLoader = $appLoader;
        $this->appsDirectory = $appsDirectory;
        $this->cacheManager = $cacheManager;
        $this->configuration = $configuration;
        $this->logger = $logger ?: new NullLogger();
    }

    /**
     * @param string $name
     * @param array $context
     *
     * @return string
     *
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     * @throws \RuntimeException
     */
    protected function renderPageTemplate(string $name, array $context = []): string
    {
        $sentryParams = [
            'identity' => $this->configuration['identity'] ?: 'undefined',
            'username' => $_SERVER['PHP_AUTH_USER'] ?: null,
            'sentryLogging' => $this->configuration['sentryLogging'] ?: false,
            'sentryFrontendDSN' => $this->configuration['sentryFrontendDSN']
        ];

        if ($coincidences = array_intersect_key($context, $sentryParams)) {
            throw new \RuntimeException(
                'Templates context intersects with sentry params: '. implode(', ', $coincidences)
            );
        }

        return $this->environment->render($name, array_merge($sentryParams, $context));
    }

    /**
     * @param Request $request
     * @param string $fileName
     *
     * @return UploadedFileInterface
     *
     * @throws \RuntimeException
     */
    protected static function getUploadedFile(Request $request, string $fileName): UploadedFileInterface
    {
        /** @var UploadedFileInterface $file */
        if (!$file = $request->getUploadedFiles()[$fileName]) {
            throw new \RuntimeException('Missing file to upload');
        }

        if ($file->getError() !== UPLOAD_ERR_OK) {
            throw new \RuntimeException('Failed to transfer file to server');
        }

        return $file;
    }
}
