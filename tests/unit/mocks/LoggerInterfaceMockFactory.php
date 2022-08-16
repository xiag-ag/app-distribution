<?php

namespace AppDistributionTool\Tests\unit\mocks;

use Psr\Log\LoggerInterface;
use Mockery as m;

/**
 * Class LoggerInterfaceMockFactory
 * @package AppDistributionTool\Tests\unit\mocks
 */
class LoggerInterfaceMockFactory
{
    /** @var string */
    public const LOGGER_EVENT_ERROR = 'error';

    /** @var string */
    public const LOGGER_EVENT_INFO = 'info';

    /**
     * @param array $loggerEvents
     *
     * @return m\MockInterface
     */
    public static function getLoggerMock(array $loggerEvents = []): m\MockInterface
    {
        $loggerMock = m::mock(LoggerInterface::class);

        foreach ($loggerEvents as $loggerEvent => $args) {
            $loggerMock->shouldReceive($loggerEvent)->withSomeOfArgs(...$args)->ordered();
        }

        return $loggerMock;
    }
}
