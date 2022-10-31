<?php

return [
  "appHost" => getenv("APP_HOST") ?: "localhost:8999",
  "organization" => getenv("ORGANIZATION") ?: "mycompany",
  "debug" => getenv("DEBUG") ?: false,
  "developer" => getenv("DEVELOPER_EMAIL") ?: "developer_name@company.name",
  "logFile" => getenv("LOG_FILE") ?: "/proc/self/fd/2",

  // Sentry options (optional)
  "sentryLogging" => getenv("SENTRY_LOGGING") ?: false,
  "identity" => getenv("SENTRY_IDENTITY") ?: "Apps",
  "sentryBackendDSN" => getenv("SENTRY_BACKEND_DSN") ?: "",
  "sentryFrontendDSN" => getenv("SENTRY_FRONTEND_DSN") ?: "",
];
