<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Kingsway Config Test</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 900px;
            margin: 40px auto;
            padding: 20px;
            background: #f5f5f5;
        }
        .card {
            background: white;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            border-bottom: 3px solid #4CAF50;
            padding-bottom: 10px;
        }
        h2 {
            color: #4CAF50;
            margin-top: 0;
        }
        .status {
            display: inline-block;
            padding: 5px 10px;
            border-radius: 4px;
            font-weight: bold;
        }
        .status.ok {
            background: #4CAF50;
            color: white;
        }
        .status.warning {
            background: #ff9800;
            color: white;
        }
        .status.error {
            background: #f44336;
            color: white;
        }
        .config-item {
            margin: 10px 0;
            padding: 10px;
            background: #f9f9f9;
            border-left: 4px solid #4CAF50;
        }
        .config-key {
            font-weight: bold;
            color: #555;
        }
        .config-value {
            color: #333;
            margin-left: 10px;
        }
        .masked {
            color: #999;
            font-style: italic;
        }
    </style>
</head>
<body>
    <h1>🔧 Kingsway Configuration Test</h1>
    
    <?php
    require_once __DIR__ . '/../vendor/autoload.php';
use App\Config\Config;
Config::init();
    require_once __DIR__ . '/../config/Config.php';
    
    // Test environment detection
    $environment = Config::getEnvironment();
    $isDebug = Config::isDebug();
    $isProduction = Config::isProduction();
    ?>
    
    <div class="card">
        <h2>Environment Status</h2>
        <div class="config-item">
            <span class="config-key">Environment:</span>
            <span class="status <?php echo $isProduction ? 'warning' : 'ok'; ?>">
                <?php echo strtoupper($environment); ?>
            </span>
        </div>
        <div class="config-item">
            <span class="config-key">Debug Mode:</span>
            <span class="status <?php echo $isDebug ? 'warning' : 'ok'; ?>">
                <?php echo $isDebug ? 'ENABLED' : 'DISABLED'; ?>
            </span>
            <?php if ($isProduction && $isDebug): ?>
                <span class="status error">⚠️ WARNING: Debug enabled in production!</span>
            <?php endif; ?>
        </div>
        <div class="config-item">
            <span class="config-key">Base URL:</span>
            <span class="config-value"><?php echo BASE_URL; ?></span>
        </div>
    </div>
    
    <div class="card">
        <h2>Database Configuration</h2>
        <div class="config-item">
            <span class="config-key">Host:</span>
            <span class="config-value"><?php echo Config::get('DB_HOST', 'Not set'); ?></span>
        </div>
        <div class="config-item">
            <span class="config-key">Database:</span>
            <span class="config-value"><?php echo Config::get('DB_NAME', 'Not set'); ?></span>
        </div>
        <div class="config-item">
            <span class="config-key">User:</span>
            <span class="config-value"><?php echo Config::get('DB_USER', 'Not set'); ?></span>
        </div>
        <div class="config-item">
            <span class="config-key">Password:</span>
            <span class="config-value masked">
                <?php 
                $dbPass = Config::get('DB_PASS', '');
                echo $dbPass ? str_repeat('*', 8) . ' (set)' : 'Not set';
                ?>
            </span>
        </div>
        <?php
        // Test database connection
        try {
            $dsn = sprintf(
                "mysql:host=%s;dbname=%s;charset=utf8mb4",
                Config::get('DB_HOST'),
                Config::get('DB_NAME')
            );
            $pdo = new PDO($dsn, Config::get('DB_USER'), Config::get('DB_PASS'));
            echo '<div class="config-item"><span class="status ok">✓ Database Connection: SUCCESS</span></div>';
        } catch (PDOException $e) {
            echo '<div class="config-item"><span class="status error">✗ Database Connection: FAILED</span><br>';
            echo '<span class="config-value">' . htmlspecialchars($e->getMessage()) . '</span></div>';
        }
        ?>
    </div>
    
    <div class="card">
        <h2>Authentication</h2>
        <div class="config-item">
            <span class="config-key">JWT Secret:</span>
            <span class="config-value masked">
                <?php 
                $jwtSecret = Config::get('JWT_SECRET', '');
                if ($jwtSecret) {
                    $secretLength = strlen($jwtSecret);
                    echo str_repeat('*', min($secretLength, 16)) . " ({$secretLength} chars)";
                    
                    // Check if using default/weak secret
                    if (in_array($jwtSecret, ['change_this_secret', 'dev_secret_key_change_this'])) {
                        echo ' <span class="status error">⚠️ Using default secret!</span>';
                    }
                } else {
                    echo 'Not set <span class="status error">⚠️</span>';
                }
                ?>
            </span>
        </div>
        <div class="config-item">
            <span class="config-key">JWT Expiry:</span>
            <span class="config-value"><?php echo Config::get('JWT_EXPIRY', 'Not set'); ?> seconds</span>
        </div>
    </div>
    
    <div class="card">
        <h2>External Services</h2>
        
        <h3 style="margin-top: 20px;">Email (SMTP)</h3>
        <div class="config-item">
            <span class="config-key">SMTP Host:</span>
            <span class="config-value"><?php echo Config::get('SMTP_HOST', 'Not set'); ?></span>
        </div>
        <div class="config-item">
            <span class="config-key">SMTP Username:</span>
            <span class="config-value"><?php echo Config::get('SMTP_USERNAME', 'Not set'); ?></span>
        </div>
        <div class="config-item">
            <span class="config-key">SMTP Password:</span>
            <span class="config-value masked">
                <?php 
                $smtpPass = Config::get('SMTP_PASSWORD', '');
                echo $smtpPass ? str_repeat('*', 8) . ' (set)' : 'Not set';
                ?>
            </span>
        </div>
        
        <h3 style="margin-top: 20px;">SMS</h3>
        <div class="config-item">
            <span class="config-key">SMS Provider:</span>
            <span class="config-value"><?php echo Config::get('SMS_PROVIDER', 'Not set'); ?></span>
        </div>
        <div class="config-item">
            <span class="config-key">SMS API Key:</span>
            <span class="config-value masked">
                <?php 
                $smsKey = Config::get('SMS_API_KEY', '');
                echo $smsKey ? str_repeat('*', 12) . ' (set)' : 'Not set';
                ?>
            </span>
        </div>
        
        <h3 style="margin-top: 20px;">M-Pesa</h3>
        <div class="config-item">
            <span class="config-key">Environment:</span>
            <span class="config-value"><?php echo Config::get('MPESA_ENVIRONMENT', 'Not set'); ?></span>
        </div>
        <div class="config-item">
            <span class="config-key">Consumer Key:</span>
            <span class="config-value masked">
                <?php 
                $mpesaKey = Config::get('MPESA_CONSUMER_KEY', '');
                echo $mpesaKey ? str_repeat('*', 12) . ' (set)' : 'Not set';
                ?>
            </span>
        </div>
        
        <h3 style="margin-top: 20px;">KCB Bank</h3>
        <div class="config-item">
            <span class="config-key">Environment:</span>
            <span class="config-value"><?php echo Config::get('KCB_ENVIRONMENT', 'Not set'); ?></span>
        </div>
        <div class="config-item">
            <span class="config-key">API Key:</span>
            <span class="config-value masked">
                <?php 
                $kcbKey = Config::get('KCB_API_KEY', '');
                echo $kcbKey ? substr($kcbKey, 0, 20) . '... (set)' : 'Not set';
                ?>
            </span>
        </div>
    </div>
    
    <div class="card">
        <h2>File Paths</h2>
        <div class="config-item">
            <span class="config-key">Upload Path:</span>
            <span class="config-value"><?php echo defined('UPLOAD_PATH') ? UPLOAD_PATH : 'Not set'; ?></span>
            <?php if (defined('UPLOAD_PATH') && is_dir(UPLOAD_PATH) && is_writable(UPLOAD_PATH)): ?>
                <span class="status ok">✓ Writable</span>
            <?php elseif (defined('UPLOAD_PATH') && is_dir(UPLOAD_PATH)): ?>
                <span class="status error">✗ Not writable</span>
            <?php elseif (defined('UPLOAD_PATH')): ?>
                <span class="status warning">⚠️ Directory doesn't exist</span>
            <?php endif; ?>
        </div>
    </div>
    
    <div class="card">
        <h2>Computed URLs</h2>
        <div class="config-item">
            <span class="config-key">M-Pesa Base URL:</span>
            <span class="config-value"><?php echo defined('MPESA_BASE_URL') ? MPESA_BASE_URL : 'Not set'; ?></span>
        </div>
        <div class="config-item">
            <span class="config-key">KCB Base URL:</span>
            <span class="config-value"><?php echo defined('KCB_BASE_URL') ? KCB_BASE_URL : 'Not set'; ?></span>
        </div>
    </div>
    
    <div class="card" style="background: #e8f5e9; border-left: 4px solid #4CAF50;">
        <h2 style="color: #2e7d32;">✓ Configuration System Working</h2>
        <p>
            The configuration system is properly loading <strong><?php echo $environment; ?></strong> environment settings.
            <?php if (!$isProduction): ?>
                You can safely test the application.
            <?php else: ?>
                <strong style="color: #f44336;">Production environment detected - ensure all secrets are properly configured!</strong>
            <?php endif; ?>
        </p>
        <?php if ($isDebug && $isProduction): ?>
            <p style="color: #f44336; font-weight: bold;">
                ⚠️ WARNING: Debug mode is enabled in production! Set DEBUG=false in .env file.
            </p>
        <?php endif; ?>
    </div>
    
    <p style="text-align: center; color: #999; margin-top: 40px;">
        <small>Kingsway Academy Management System | Configuration Test v1.0</small>
    </p>
</body>
</html>
