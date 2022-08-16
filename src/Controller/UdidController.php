<?php

namespace AppDistributionTool\Controller;

use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\ResponseInterface;
use Slim\Psr7\Message;
use Slim\Psr7\Request;
use Slim\Psr7\Response;
use Twig\Error\LoaderError;
use Twig\Error\RuntimeError;
use Twig\Error\SyntaxError;

/**
 * Class UdidController
 * @package AppDistributionTool\Controller
 */
class UdidController extends AbstractAppController
{
    /**
     * @param Request $request
     * @param Response $response
     *
     * @return Response
     *
     * @throws LoaderError
     * @throws RuntimeError
     * @throws SyntaxError
     */
    public function getResultsAction(Request $request, Response $response): Response
    {
        $deviceProduct = $request->getQueryParams()['DEVICE_PRODUCT'];
        $udid = $request->getQueryParams()['UDID'];
        $deviceVersion = $request->getQueryParams()['DEVICE_VERSION'];

        $body = <<<TEXT
Hello

This is my UDID: {$udid}
Device product: {$deviceProduct}
System version: {$deviceVersion}

Please rebuild the app and notify me.

Thanks!
TEXT;

        $response->getBody()->write($this->environment->render('pages/udid/index.twig', [
            'subject' => 'This is my UDID from iOS device',
            'body' => str_replace(array("\r\n", "\r", "\n"), "%0D%0A", $body),
            'UDID' => $udid,
            'DEVICE_PRODUCT' => $deviceProduct,
            'DEVICE_VERSION' => $deviceVersion,
            'stepTemplate' => 'pages/udid/_step2.twig',
            'developer' => $this->configuration['debug'] ? $this->configuration['developer'] : null
        ]));

        return $response;
    }

    /**
     * @param Request $request
     * @param Response $response
     *
     * @return MessageInterface|ResponseInterface|Message|Response
     */
    public function getSubmitAction(Request $request, Response $response)
    {
        $params = self::parseUDIDParams($request->getBody()->getContents());

        return $response->withStatus(301)->withHeader('Location', '/udid/results?' . http_build_query($params));
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
     */
    public function getAction(Request $request, Response $response): Response
    {
        $response->getBody()->write($this->environment->render('pages/udid/index.twig', [
            'stepTemplate' => 'pages/udid/_step1.twig',
        ]));

        return $response;
    }

    /**
     * @param $data
     *
     * @return array
     */
    protected static function parseUDIDParams($data): array
    {
        $plistBegin = '<?xml version="1.0"';
        $plistEnd = '</plist>';

        $pos1 = strpos($data, $plistBegin);
        $pos2 = strpos($data, $plistEnd);
        $data2 = substr($data, $pos1, $pos2 - $pos1);

        $xml = xml_parser_create();
        xml_parse_into_struct($xml, $data2, $vs);
        xml_parser_free($xml);

        $arrayCleaned = [];
        foreach ($vs as $v) {
            if ($v['level'] == 3 && $v['type'] == 'complete') {
                $arrayCleaned[] = $v;
            }
        }

        $iterator = 0;
        $params = [];

        foreach ($arrayCleaned as $elem) {
            switch ($elem['value']) {
                case 'UDID':
                    $params['UDID'] = $arrayCleaned[$iterator + 1]['value'];
                    break;
                case 'PRODUCT':
                    $params['DEVICE_PRODUCT'] = $arrayCleaned[$iterator + 1]['value'];
                    break;
                case 'VERSION':
                    $params['DEVICE_VERSION'] = $arrayCleaned[$iterator + 1]['value'];
                    break;
            }
            $iterator++;
        }

        return $params;
    }
}
