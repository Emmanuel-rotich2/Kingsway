<?php

namespace App\Config;

/**
 * Kingsway Academy Configuration Class
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

        // Step 3: Load environment-specific config file
        self::loadEnvironmentConfig();

        self::$loaded = true;
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

        $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        foreach ($lines as $line) {
            if (strpos(trim($line), '#') === 0) {
                continue; // Skip comments
            }

            if (strpos($line, '=') !== false) {
                list($key, $value) = explode('=', $line, 2);
                $key = trim($key);
                $value = trim($value, '"\'');

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
    public static function set(string $key, $value)
    {
        self::init();
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
