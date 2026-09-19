<?php

namespace App\Config;

/**
 * Kingsway Preparatory School Configuration Class
 * 
 * Self-contained configuration manager that:
 * - Loads .env file
 * - Detects environment (development/production)
 * - Defines all system constants
 * - Auto-loads via Composer PSR-4
 * 
 * Usage in any module:
 *   use App\Config\Config;
 *   // Constants are auto-defined on first use
 *   $value = DB_HOST;  // or Config::get('DB_HOST');
 */
class Config
{
    private static $config = [];
    private static $loaded = false;
    private static $environment = null;

    /**
     * Initialize configuration
     * Auto-called on first use of any Config method
     */
    public static function init()
    {
        if (self::$loaded) {
            return;
        }

        // Step 1: Load .env file
        self::loadEnvFile();

        // Step 2: Detect environment
        self::$environment = self::detectEnvironment();

        // Govern native PHP warnings/notices from API entry points, CLI jobs
        // and standalone workers that do not pass through api/index.php.
        self::configureRuntimeLogging();

        // Step 3: Load environment-specific config file
        self::loadEnvironmentConfig();

        self::$loaded = true;
    }

    private static function configureRuntimeLogging(): void
    {
        $environment = in_array(self::$environment, ['production', 'staging'], true)
            ? self::$environment
            : 'development';
        $directory = dirname(__DIR__) . '/logs/' . $environment;
        $dirMode = $environment === 'production' ? 0770 : 0777;
        if (!is_dir($directory)) {
            @mkdir($directory, $dirMode, true);
        }
        @chmod($directory, $dirMode);
        date_default_timezone_set((string) ($_ENV['APP_TIMEZONE'] ?? 'Africa/Nairobi'));
        ini_set('log_errors', '1');
        ini_set('error_log', $directory . '/php-errors-' . date('Y-m-d') . '.log');
        if ($environment === 'production') {
            ini_set('display_errors', '0');
            ini_set('display_startup_errors', '0');
        }
    }

    /**
     * Detect current environment
     */
    private static function detectEnvironment(): string
    {
        // Override from .env file
        if (isset($_ENV['APP_ENV'])) {
            return $_ENV['APP_ENV'];
        }

        // Auto-detect from hostname
        $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
        
        if ($host === 'localhost' || strpos($host, '127.0.0.1') !== false) {
            return 'development';
        }

        if (strpos($host, 'staging') !== false || strpos($host, 'test') !== false) {
            return 'staging';
        }

        return 'production';
    }

    /**
     * Load .env file
     */
    private static function loadEnvFile()
    {
        $envFile = __DIR__ . '/.env';
        
        if (!file_exists($envFile)) {
            error_log('INFO: .env file not found - using defaults');
            return;
        }

        if (!is_readable($envFile)) {
            throw new \RuntimeException(
                'Environment configuration exists but is not readable by the PHP process.'
            );
        }

        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        if ($lines === false) {
            throw new \RuntimeException('Environment configuration could not be loaded.');
        }
        
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) {
                continue; // Skip comments
            }

            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value, '"\'');

                // Deployment-provided environment variables take precedence
                // over repository/local .env values. This prevents a local
                // APP_ENV=development entry from downgrading production.
                $processValue = getenv($key);
                if ($processValue !== false && $processValue !== '') {
                    $value = (string) $processValue;
                }

                $_ENV[$key] = $value;
                putenv("$key=$value");
                self::$config[$key] = $value;
            }
        }
    }

    /**
     * Load environment-specific configuration file
     */
    private static function loadEnvironmentConfig()
    {
        $configFile = __DIR__ . '/config_' . self::$environment . '.php';
        
        if (file_exists($configFile)) {
            require_once $configFile;
        } else {
            error_log('WARNING: Environment config file not found: ' . $configFile);
        }
    }

    /**
     * Get configuration value
     */
    public static function get(string $key, $default = null)
    {
        self::init();

        if (defined($key)) {
            return constant($key);
        }

        if (isset($_ENV[$key])) {
            return $_ENV[$key];
        }

        if (isset(self::$config[$key])) {
            return self::$config[$key];
        }

        return $default;
    }

    /**
     * Set configuration value at runtime
     */
    private static array $immutableKeys = [
        'JWT_SECRET', 'JWT_EXPIRY', 'JWT_ISSUER', 'JWT_AUDIENCE',
        'DB_HOST', 'DB_NAME', 'DB_USER', 'DB_PASS',
        'AI_ENABLED', 'AI_PROVIDER_NAME', 'AI_PROVIDER_BASE_URL', 'AI_PROVIDER_KIND', 'AI_MODEL', 'AI_API_KEY', 'AI_PROVIDER_FALLBACKS', 'AI_RESPONSE_FORMAT', 'AI_REASONING_EFFORT', 'AI_VISION_ENABLED', 'AI_FORCE_IPV4',
    ];

    public static function set(string $key, $value)
    {
        self::init();
        if (in_array($key, self::$immutableKeys, true) && self::isProduction()) {
            throw new \RuntimeException("Cannot mutate {$key} at runtime in production");
        }
        self::$config[$key] = $value;
    }

    /**
     * Get current environment
     */
    public static function getEnvironment(): string
    {
        self::init();
        return self::$environment;
    }

    /**
     * Check if production
     */
    public static function isProduction(): bool
    {
        return self::getEnvironment() === 'production';
    }

    /**
     * Check if development
     */
    public static function isDevelopment(): bool
    {
        return self::getEnvironment() === 'development';
    }

    /**
     * Check if debug enabled
     */
    public static function isDebug(): bool
    {
        return (bool) self::get('DEBUG', false);
    }

    /**
     * Get all config values
     */
    public static function all(): array
    {
        self::init();
        return self::$config;
    }
}

// Auto-initialize when class loads
Config::init();
