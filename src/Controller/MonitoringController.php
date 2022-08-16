<?php

namespace AppDistributionTool\Controller;

use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\ResponseInterface;
use Slim\Psr7\Message;
use Slim\Psr7\Request;
use Slim\Psr7\Response;

/**
 * Class MonitoringController
 * @package AppDistributionTool\Controller
 */
class MonitoringController extends AbstractAppController
{
    /**
     * @param Request $request
     * @param Response $response
     *
     * @return MessageInterface|ResponseInterface|Message|Response
     *
     * @throws \JsonException
     */
    public function getAction(Request $request, Response $response)
    {
        $apps = $this->appLoader->loadApps($this->cacheManager, $this->appsDirectory);

        if (count($apps) === 0) {
            $response->getBody()->write('No applications found');

            return $response->withStatus(404)->withHeader('Content-Type', 'text/html');
        }

        $response->getBody()->write('OK');

        return $response;
    }
}
