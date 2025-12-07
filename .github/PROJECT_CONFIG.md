# Kingsway Academy - Project Configuration

## Development Environment

### XAMPP Configuration
- **Server**: XAMPP (Apache + MySQL + PHP)
- **Database Server**: MySQL/MariaDB
- **Database User**: `root`
- **Database Password**: `admin123`
- **Database Name**: `KingsWayAcademy`
- **Default Port**: `3306`

### Database Connection String
```bash
/opt/lampp/bin/mysql -u root -padmin123 KingsWayAcademy
```

### Running Database Migrations
```bash
# Navigate to project root
cd /home/prof_angera/Projects/php_pages/Kingsway

# Run a migration file
/opt/lampp/bin/mysql -u root -padmin123 KingsWayAcademy < database/migrations/<migration_file>.sql

# Import main schema
/opt/lampp/bin/mysql -u root -padmin123 KingsWayAcademy < database/KingsWayAcademy.sql

# Import workflows
/opt/lampp/bin/mysql -u root -padmin123 KingsWayAcademy < database/workflows_FINAL.sql
```

### PHP Configuration
- **PHP Version**: 7.4+ (recommended 8.0+)
- **Required Extensions**:
  - `pdo_mysql`
  - `mysqli`
  - `json`
  - `mbstring`
  - `openssl`
  - `curl`

### Project Structure
```
/home/prof_angera/Projects/php_pages/Kingsway/
├── api/                    # Backend API endpoints
├── config/                 # Configuration files
├── database/               # SQL schema and migrations
│   ├── migrations/        # Database migration files
│   ├── KingsWayAcademy.sql
│   └── workflows_FINAL.sql
├── js/                     # Frontend JavaScript
├── pages/                  # Frontend pages
├── vendor/                 # Composer dependencies
└── .github/               # GitHub configuration
```

### Environment Variables
Located in: `config/config.php`

Key constants:
- `DB_HOST` - Database host (default: localhost)
- `DB_NAME` - Database name
- `DB_USER` - Database username
- `DB_PASS` - Database password
- `JWT_SECRET` - JWT authentication secret
- `DEBUG` - Debug mode flag

### Quick Start Commands

#### Start XAMPP
```bash
sudo /opt/lampp/lampp start
```

#### Stop XAMPP
```bash
sudo /opt/lampp/lampp stop
```

#### Restart XAMPP
```bash
sudo /opt/lampp/lampp restart
```

#### Access MySQL CLI
```bash
/opt/lampp/bin/mysql -u root -padmin123
```

#### Check Database Status
```bash
/opt/lampp/bin/mysql -u root -padmin123 -e "SHOW DATABASES;"
```

#### Run Specific Migration
```bash
/opt/lampp/bin/mysql -u root -padmin123 KingsWayAcademy < database/migrations/inventory_workflows_expansion.sql
```

### Development Workflow

1. **Before Running Migrations**:
   - Ensure XAMPP is running
   - Verify database exists
   - Backup existing data if needed

2. **After Code Changes**:
   - Test API endpoints
   - Check error logs: `logs/errors.log`
   - Verify system logs: `logs/system_activity.log`

3. **Database Changes**:
   - Create migration file in `database/migrations/`
   - Test migration on development database
   - Document changes in migration file header
   - Update relevant documentation

### Common Issues & Solutions

#### Issue: "Access denied for user 'root'"
```bash
# Solution: Verify password
/opt/lampp/bin/mysql -u root -padmin123

# If still fails, reset MySQL password in XAMPP
sudo /opt/lampp/lampp security
```

#### Issue: "Database does not exist"
```bash
# Solution: Create database
/opt/lampp/bin/mysql -u root -padmin123 -e "CREATE DATABASE IF NOT EXISTS KingsWayAcademy;"
```

#### Issue: "Can't connect to MySQL server"
```bash
# Solution: Ensure XAMPP MySQL is running
sudo /opt/lampp/lampp status
sudo /opt/lampp/lampp start
```

### Security Notes

⚠️ **Important**: These credentials are for **DEVELOPMENT ONLY**

For production deployment:
- Change default MySQL root password
- Create dedicated database user with limited privileges
- Use environment variables for sensitive data
- Enable SSL/TLS for database connections
- Keep `DEBUG` mode disabled

### Testing Database Connection

```php
<?php
// Test database connection
$host = 'localhost';
$dbname = 'KingsWayAcademy';
$user = 'root';
$pass = 'admin123';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $user, $pass);
    echo "Database connection successful!";
} catch (PDOException $e) {
    echo "Connection failed: " . $e->getMessage();
}
?>
```

### Useful Database Queries

```sql
-- Check all tables
USE KingsWayAcademy;
SHOW TABLES;

-- Check workflow definitions
SELECT code, name, category FROM workflow_definitions;

-- Check workflow stages for inventory
SELECT ws.name, ws.sequence, ws.required_role 
FROM workflow_stages ws
JOIN workflow_definitions wd ON ws.workflow_id = wd.id
WHERE wd.code = 'inventory_management'
ORDER BY ws.sequence;

-- Check active workflow instances
SELECT wi.id, wd.name, wi.current_stage, wi.status
FROM workflow_instances wi
JOIN workflow_definitions wd ON wi.workflow_id = wd.id
WHERE wi.status IN ('pending', 'in_progress')
ORDER BY wi.started_at DESC;
```

---

## RBAC System Documentation

The RBAC (Role-Based Access Control) system has been completely normalized. For comprehensive documentation on:

- Normalized database schema
- Available procedures and functions
- PHP usage examples
- RBAC queries
- Migration files

See: **[RBAC System Documentation](./PROJECT_CONFIG_RBAC.md)**

### Quick Status

- ✅ **Status**: Fully Normalized (December 2025)
- **Total Permissions**: 4,456
- **Active Roles**: 29 (+ 1 legacy Admin)
- **Role-Permission Mappings**: 16,213
- **Helper Procedures**: 7 procedures + 1 function
- **Audit Logging**: ✅ Complete

---

**Last Updated**: 6 December 2025  
**Maintained By**: Development Team  
**For Support**: Contact system administrator
