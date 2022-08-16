<?php

namespace AppDistributionTool\Controller;

use Psr\Http\Message\MessageInterface;
use Slim\Psr7\Request;
use Slim\Psr7\Response;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

/**
 * Class DefaultController
 * @package AppDistributionTool\Controller
 */
class DefaultController extends AbstractAppController
{
    /**
     * @param Request $request
     * @param Response $response
     * @param array $args
     *
     * @return Response|MessageInterface
     *
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     * @throws \JsonException
     */
    public function getAction(Request $request, Response $response, array $args)
    {
        $isGroupPage = false;
        $group = strtolower($args['group']);
        $name = strtolower($args['name']);
        $version = strtolower($args['version']);

        if (!$args) {
            $apps = [
                'noGroup' => [
                    'groupName' => 'GROUPNAME',
                    'apps'      => [
                        'fakeApp' => [
                            'name' => '🔒 applications list is forbidden, use direct link',
                        ],
                    ],
                ],
            ];
        } else {
            $apps = $this->appLoader->loadApps($this->cacheManager, $this->appsDirectory);

            if ($group !== 'all-apps') {
                try {
                    $apps = $this->appLoader->filterApp($apps, $group);

                    if ($name) {
                        $apps[$group]['apps'] = $this->appLoader->filterApp($apps[$group]['apps'], $name);

                        if ($version) {
                            $apps[$group]['apps'][$name]['versions'] = $this->appLoader->filterVersion(
                                $apps[$group]['apps'][$name], $version
                            );
                        }
                    } else { // show only last versions of apps
                        $isGroupPage = true;
                        array_walk($apps[$group]['apps'], function(&$app) {
                            $latestVersion = reset($app['versions']);
                            $app['versions'] = [];
                            $app['versions'][$latestVersion['number']] = $latestVersion;
                        });
                    }
                } catch (\Exception $exception) {
                    $this->logger->error($exception->getMessage(), [
                        'clientData' => $args,
                        'errorTrace' => $exception->getTraceAsString()
                    ]);
                    $response->getBody()->write('Not found');

                    return $response->withStatus(404)->withHeader('Content-Type', 'text/html');
                }
            }
        }

        $body = $this->renderPageTemplate('pages/index.twig', [
            'apps' => $apps,
            'isGroupPage' => $isGroupPage,
            'domain' => $request->getUri()->getHost(),
            'title' => self::getPageTitle($group, $name, $version)
        ]);

        $response->getBody()->write($body);

        return $response;
    }

    /**
     * @param string|null $group
     * @param string|null $name
     * @param string|null $version
     *
     * @return string
     */
    protected static function getPageTitle(?string $group, ?string $name, ?string $version): string
    {
        if (!$group) {
            return '';
        }

        $title = implode(' ', array_filter([$name, $version]));

        return $title ? $title . ' - ' . $group : $group;
    }
}
