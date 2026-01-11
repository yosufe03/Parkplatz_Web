<?php
/**
 * Application Configuration
 *
 * Load environment variables from .env file (or system environment)
 * Never commit sensitive values - use .env files for local development
 */

// Load from environment or use defaults
$config = [
    'db_host' => getenv('DB_HOST') ?: 'db',
    'db_user' => getenv('DB_USER') ?: 'db',
    'db_password' => getenv('DB_PASSWORD') ?: 'db',
    'db_name' => getenv('DB_NAME') ?: 'db',
    'app_secret_key' => getenv('APP_SECRET_KEY') ?: 'change-this-secret-key-in-production',
    'session_timeout' => (int)(getenv('APP_SESSION_TIMEOUT') ?: 1800), // 30 minutes
    'app_debug' => getenv('APP_DEBUG') === 'true',
    'app_env' => getenv('APP_ENV') ?: 'development',
];

return $config;

