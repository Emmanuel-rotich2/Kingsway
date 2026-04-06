# Kingsway Academy Configuration System

## Overview

The configuration system is environment-aware and automatically loads the correct settings for development, staging, or production environments.

## Files

- **`.env`** - Environment-specific variables (NOT in version control)
- **`.env.example`** - Template for creating your `.env` file
- **`config.php`** - Smart loader that detects environment and loads correct config
- **`config_development.php`** - Development environment settings
- **`config_production.php`** - Production environment settings
- **`Config.php`** - PHP class for accessing configuration values

## Setup

### 1. Create Your .env File

```bash
cp config/.env.example config/.env
```

### 2. Edit .env with Your Credentials

```env
# Development Example
APP_ENV=development
DEBUG=true
BASE_URL=http://localhost/Kingsway

DB_HOST=127.0.0.1
DB_USER=root
DB_PASS=admin123
DB_NAME=KingsWayAcademy

JWT_SECRET=your_secure_secret_key_here

# Add your API keys
MPESA_CONSUMER_KEY=your_key
MPESA_CONSUMER_SECRET=your_secret
SMS_API_KEY=your_sms_key
```

### 3. Production Setup

For production, create `/home/kingswa4/public_html/config/.env`:

```env
APP_ENV=production
DEBUG=false
BASE_URL=https://kingswaypreparatoryschool.sc.ke

DB_HOST=localhost
DB_USER=kingswa4_root
DB_PASS=secure_production_password
DB_NAME=kingswa4_kingswayacademy

JWT_SECRET=generate_a_secure_64_char_random_string

# Production API keys
MPESA_ENVIRONMENT=production
MPESA_CONSUMER_KEY=production_key
MPESA_CONSUMER_SECRET=production_secret
# ... etc
```

## How It Works

### Auto Environment Detection

The system automatically detects which environment it's running in:

1. **Checks `.env` file** for `APP_ENV` variable
2. **Auto-detects** based on hostname:
   - `localhost` or `127.0.0.1` → development
   - Contains `staging` or `test` → staging
   - Everything else → production

### Configuration Loading Order

1. Load `.env` file (if exists)
2. Detect environment
3. Load environment-specific config (`config_development.php` or `config_production.php`)
4. Load common settings from `config.php`
5. Set up computed values (URLs, paths, etc.)

## Usage

### In PHP Code

```php
// Method 1: Direct constants (traditional)
$dbHost = DB_HOST;
$apiKey = SMS_API_KEY;

// Method 2: Using Config class
use App\Config\Config;

$dbHost = Config::get('DB_HOST');
$apiKey = Config::get('SMS_API_KEY', 'default_value');

// Check environment
if (Config::isProduction()) {
    // Production-specific code
}

if (Config::isDevelopment()) {
    // Development-specific code
}

if (Config::isDebug()) {
    error_log('Debug info');
}
```

### Adding New Configuration

1. **Add to `.env.example`** (without real values):
   ```env
   NEW_API_KEY=your_api_key_here
   ```

2. **Add to environment configs** (with .env fallback):
   ```php
   // config_development.php or config_production.php
   define('NEW_API_KEY', $_ENV['NEW_API_KEY'] ?? '');
   ```

3. **Add to your `.env`** file with actual values

## Environment Variables Reference

### Core Settings
- `APP_ENV` - Environment name (development, staging, production)
- `DEBUG` - Enable debug mode (true/false)
- `BASE_URL` - Application base URL

### Database
- `DB_HOST` - Database host
- `DB_USER` - Database username
- `DB_PASS` - Database password
- `DB_NAME` - Database name
- `DB_PORT` - Database port (default: 3306)

### Authentication
- `JWT_SECRET` - Secret key for JWT tokens (MUST be secure in production)
- `JWT_EXPIRY` - Token expiry time in seconds (default: 3600)

### Email (SMTP)
- `SMTP_HOST` - Mail server hostname
- `SMTP_PORT` - Mail server port
- `SMTP_USERNAME` - SMTP username
- `SMTP_PASSWORD` - SMTP password
- `SMTP_FROM_EMAIL` - From email address
- `SMTP_FROM_NAME` - From name

### SMS (Africa's Talking)
- `SMS_PROVIDER` - SMS provider (africastalking)
- `SMS_API_KEY` - API key
- `SMS_USERNAME` - Username (sandbox for testing)
- `SMS_SENDER_ID` - Sender ID
- `SMS_SHORTCODE` - SMS shortcode

### M-Pesa
- `MPESA_ENVIRONMENT` - sandbox or production
- `MPESA_CONSUMER_KEY` - Consumer key
- `MPESA_CONSUMER_SECRET` - Consumer secret
- `MPESA_SHORTCODE` - Paybill number
- `MPESA_PASSKEY` - Lipa Na M-Pesa passkey
- `MPESA_INITIATOR_NAME` - Initiator name (for B2C)
- `MPESA_INITIATOR_PASSWORD` - Initiator password
- `MPESA_SECURITY_CREDENTIAL` - Encrypted security credential

### KCB Bank
- `KCB_ENVIRONMENT` - sandbox or production
- `KCB_CONSUMER_KEY` - Consumer key
- `KCB_CONSUMER_SECRET` - Consumer secret
- `KCB_API_KEY` - API key
- `KCB_ORGANIZATION_REFERENCE` - Organization reference
- `KCB_CREDIT_ACCOUNT` - Account number

### File Uploads
- `UPLOAD_PATH` - Base upload directory path

## Security Best Practices

### ✅ DO:
- Keep `.env` file secure and never commit it
- Use strong, unique `JWT_SECRET` in production
- Rotate API keys regularly
- Use production credentials only in production
- Set `DEBUG=false` in production
- Use HTTPS in production (`BASE_URL` should be https://)

### ❌ DON'T:
- Don't commit `.env` file to git
- Don't use development keys in production
- Don't share credentials in code or documentation
- Don't use simple/guessable secrets
- Don't expose debug info in production

## Troubleshooting

### Config not loading

**Check:** Is `.env` file present in `config/` directory?
```bash
ls -la config/.env
```

**Fix:** Copy from example
```bash
cp config/.env.example config/.env
```

### Wrong environment detected

**Check:** What environment is being detected?
```php
use App\Config\Config;
echo Config::getEnvironment();
```

**Fix:** Set explicitly in `.env`
```env
APP_ENV=development
```

### Database connection fails

**Check:** Are credentials correct in `.env`?

**Fix:** Update `.env` with correct credentials:
```env
DB_HOST=127.0.0.1
DB_USER=root
DB_PASS=admin123
DB_NAME=KingsWayAcademy
```

### API keys not working

**Check:** Are they defined in `.env` and loaded?
```php
var_dump(getenv('MPESA_CONSUMER_KEY'));
```

**Fix:** Add to `.env` and restart web server

## Migration from Old Config

If migrating from hardcoded config:

1. Create `.env` file from `.env.example`
2. Move sensitive values from old `config.php` to `.env`
3. Update code to use new config system
4. Test in development
5. Deploy to production with production `.env`
6. Remove old hardcoded credentials

## Support

For issues or questions about configuration:
- Check this README first
- Review `.env.example` for all available options
- Ensure `.env` file exists and has correct permissions
- Verify environment detection with `Config::getEnvironment()`

---

**Last Updated:** April 2026  
**Version:** 1.0.0
