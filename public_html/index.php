<?php

use AppDistributionTool\Controller\MonitoringController;
use AppDistributionTool\Controller\UdidController;
use AppDistributionTool\Extension\DescriptionMiddlewareExtension;
use AppDistributionTool\Service\AppLoader;
use AppDistributionTool\Controller\DefaultController;
use AppDistributionTool\Controller\UploadController;
use AppDistributionTool\Service\CacheManager;
use AppDistributionTool\Service\FileManager;
use DI\Container;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;
use Psr\Container\ContainerInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\NullLogger;
use Slim\Factory\AppFactory;
use Slim\Psr7\Response;
use Twig\Environment;
use Twig\Extra\Markdown\DefaultMarkdown;
use Twig\Extra\Markdown\MarkdownExtension;
use Twig\Extra\Markdown\MarkdownRuntime;
use Twig\Loader\FilesystemLoader;
use Twig\RuntimeLoader\RuntimeLoaderInterface;

include __DIR__ . '/../src/inc/bootstrap.php';

$container = new Container();

//-------------------------register constants-------------------------//
// see: 'deployment' project -> root/salt/tool/dependencies.sls
$container->set('configuration', $config);
$container->set('appsDirectory', __DIR__ . '/../apps');
$container->set('cacheDirectory', __DIR__ . '/../cache');
$container->set('templatesDirectory', __DIR__ . '/../src/templates');

//-------------------------register services-------------------------//
$container->set('AppLoader', function() { return new AppLoader(); });
$container->set('FileManager', function(ContainerInterface $container) {
    return new FileManager( $container->get('appsDirectory'));
});
$container->set('CacheManager', function(ContainerInterface $container) {
    return new CacheManager($container->get('cacheDirectory'));
});
$container->set('Twig', function(ContainerInterface $container) {
    $loader = new FilesystemLoader($container->get('templatesDirectory'));
    $twig = new Environment($loader);

    $twig->addExtension(new MarkdownExtension());
    $twig->addExtension(new DescriptionMiddlewareExtension());
    $twig->addRuntimeLoader(new class implements RuntimeLoaderInterface {
        public function load($class)
        {
            if (MarkdownRuntime::class === $class) {
                return new MarkdownRuntime(new DefaultMarkdown());
            }
        }
    });

    return $twig;
});
$container->set('MainLogger', function(ContainerInterface $container) {
    return isset($container->get('configuration')['logFile']) ?
        (new Logger('main'))
            ->pushHandler(new StreamHandler($container->get('configuration')['logFile'], Logger::ERROR))
        : new NullLogger();
});

//-------------------------register controllers-------------------------//
$abstractControllerParams = [
    $container->get('Twig'),
    $container->get('AppLoader'),
    $container->get('appsDirectory'),
    $container->get('CacheManager'),
    $container->get('configuration'),
    $container->get('MainLogger')
];

$container->set(UploadController::class, function(ContainerInterface $container) use ($abstractControllerParams) {
    $abstractControllerParams[] = $container->get('FileManager');
    return new UploadController(...$abstractControllerParams);
});
$container->set(DefaultController::class, function() use ($abstractControllerParams) {
    return new DefaultController(...$abstractControllerParams);
});
$container->set(MonitoringController::class, function() use ($abstractControllerParams) {
    return new MonitoringController(...$abstractControllerParams);
});
$container->set(UdidController::class, function() use ($abstractControllerParams) {
    return new UdidController(...$abstractControllerParams);
});
//------------------------------------------------------------------------//

AppFactory::setContainer($container);
$app = AppFactory::create();

// use custom error handler in need to send errors to Sentry
$customErrorHandler = function (
    ServerRequestInterface $request,
    Throwable $exception,
    bool $displayErrorDetails,
    bool $logErrors,
    bool $logErrorDetails
) use ($app) {
    if ($app->getContainer()->get('configuration')['sentryLogging']) {
        Sentry\captureException($exception);
    }

    // delegate exception handling to default handler
    $handler = new Slim\Handlers\ErrorHandler(
        $app->getCallableResolver(),
        $app->getResponseFactory(),
        $app->getContainer()->get('MainLogger')
    );
    return $handler->__invoke($request, $exception, $displayErrorDetails, $logErrors, $logErrorDetails);
};

$errorMiddleware = $app->addErrorMiddleware(
    $container->get('configuration')['debug'] ?: false,
    true,
    true,
    $container->get('MainLogger')
);
$errorMiddleware->setDefaultErrorHandler($customErrorHandler);

$app->add(function (RequestInterface $request, RequestHandlerInterface $handler) {
    $uri = $request->getUri();
    $path = $uri->getPath();

    if ($path !== '/' && substr($path, -1) === '/') {
        $path = rtrim($path, '/');
        $uri = $uri->withPath($path);

        if ($request->getMethod() === 'GET') {
            return (new Response())
                ->withHeader('Location', (string) $uri)
                ->withStatus(301);
        }

        $request = $request->withUri($uri);
    }

    return $handler->handle($request);
});

$app->get('/upload', UploadController::class . ':getAction');
$app->post('/upload', UploadController::class . ':postAction');
$app->get('/monitoring', MonitoringController::class . ':getAction');
$app->get('/udid/results', UdidController::class . ':getResultsAction');
$app->post('/udid/submit', UdidController::class . ':getSubmitAction');
$app->get('/udid[/{params:.*}]', UdidController::class . ':getAction');
$app->get('/[{group}[/{name}[/{version}]]]', DefaultController::class . ':getAction');

$app->run();
