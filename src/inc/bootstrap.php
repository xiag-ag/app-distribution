<?php

include __DIR__ . '/../../vendor/autoload.php';

error_reporting(E_ALL & ~E_NOTICE & ~E_WARNING);

$config = include __DIR__ . '/../config/application.php';

if ($config['sentryLogging'] && !defined('TESTS_IN_PROGRESS')) {
    Sentry\init([
        'dsn' => $config['sentryBackendDSN'],
        'error_types' => E_ALL & ~E_NOTICE,
        'environment' => $config['identity'] ?: 'undefined'
    ]);

    if (isset($_SERVER['PHP_AUTH_USER'])) {
        Sentry\configureScope(function (Sentry\State\Scope $scope): void {
            $scope->setUser(['username' => $_SERVER['PHP_AUTH_USER']]);
        });
    }
}
