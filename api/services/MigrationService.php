<?php
namespace App\API\Services;

use PDO;

class MigrationService
{
    private PDO $db;
    private string $migrationsDir;

    public function __construct(PDO $db, ?string $migrationsDir = null)
    {
        $this->db = $db;
        $this->migrationsDir = $migrationsDir ?: dirname(__DIR__, 2) . '/database/migrations';
        $this->ensureMigrationsTable();
    }

    /**
     * Resolve the MySQL client binary.
     *
     * The stack is system Apache + system MariaDB (XAMPP was removed), so the
     * client that ships with the OS package is the one that exists. A packaged
     * XAMPP path is deliberately NOT a fallback any more: it would make the
     * migration runner fail on every clean install with a misleading
     * "mysql client not executable" error instead of finding the real client.
     *
     * An explicit MYSQL_BIN still overrides everything (CI, odd PATHs).
     */
    private static function mysqlBinary(): string
    {
        $env = getenv('MYSQL_BIN');
        if (is_string($env) && $env !== '' && is_executable($env)) {
            return $env;
        }

        foreach (['/usr/bin/mysql', '/usr/local/bin/mysql'] as $candidate) {
            if (is_executable($candidate)) {
                return $candidate;
            }
        }

        $which = @shell_exec('command -v mysql 2>/dev/null');
        return is_string($which) && trim($which) !== '' ? trim($which) : '/usr/bin/mysql';
    }

    private function ensureMigrationsTable(): void
    {
        $this->db->exec("CREATE TABLE IF NOT EXISTS migrations (
            id INT AUTO_INCREMENT PRIMARY KEY,
            filename VARCHAR(255) NOT NULL UNIQUE,
            checksum VARCHAR(64) NOT NULL,
            applied_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            duration_ms INT DEFAULT 0
        ) ENGINE=InnoDB");
    }

    public function getPending(): array
    {
        $files = glob($this->migrationsDir . '/*.sql');
        sort($files);
        $applied = $this->db->query("SELECT filename, checksum FROM migrations")->fetchAll(PDO::FETCH_KEY_PAIR);

        $pending = [];
        foreach ($files as $file) {
            $basename = basename($file);
            $checksum = md5_file($file);
            if (!isset($applied[$basename])) {
                $pending[] = ['filename' => $basename, 'path' => $file, 'checksum' => $checksum];
            } elseif ($applied[$basename] !== $checksum) {
                $pending[] = ['filename' => $basename, 'path' => $file, 'checksum' => $checksum, 'modified' => true];
            }
        }
        return $pending;
    }

    public function migrate(): array
    {
        $results = [];
        foreach ($this->getPending() as $migration) {
            $start = microtime(true);
            $sql = file_get_contents($migration['path']);
            $statements = explode(';', $sql);
            $this->db->beginTransaction();
            try {
                foreach ($statements as $stmt) {
                    $stmt = trim($stmt);
                    if (!empty($stmt)) {
                        $this->db->exec($stmt);
                    }
                }
                $duration = (int)((microtime(true) - $start) * 1000);
                $this->db->exec("INSERT INTO migrations (filename, checksum, duration_ms) VALUES ("
                    . $this->db->quote($migration['filename']) . ", "
                    . $this->db->quote($migration['checksum']) . ", $duration)");
                $this->db->commit();
                $results[] = ['filename' => $migration['filename'], 'status' => 'applied', 'duration_ms' => $duration];
            } catch (\Exception $e) {
                $this->db->rollBack();
                $results[] = ['filename' => $migration['filename'], 'status' => 'failed', 'error' => $e->getMessage()];
                break;
            }
        }
        return $results;
    }

    public function getStatus(): array
    {
        $stmt = $this->db->query("SELECT filename, checksum, applied_at, duration_ms FROM migrations ORDER BY applied_at");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Apply ONLY the explicitly named migration files.
     *
     * This deliberately refuses to apply the whole migrations directory. Each
     * file is executed through the mysql CLI client (not a ';' split, which
     * breaks DELIMITER-based procedure/trigger bodies). Files already recorded
     * in the ledger are skipped unless $force is true. Successful applies are
     * recorded in the migrations ledger.
     *
     * @param array $filenames Exact file basenames, e.g. ['042_....sql']
     * @param bool  $force     Re-run files that are already in the ledger
     * @return array<int, array{filename:string,status:string,error?:string,duration_ms?:int}>
     */
    public function applyFiles(array $filenames, bool $force = false): array
    {
        if (empty($filenames)) {
            throw new \InvalidArgumentException('At least one migration filename is required');
        }

        $mysql = self::mysqlBinary();
        if (!is_executable($mysql)) {
            throw new \RuntimeException("mysql client not executable: {$mysql}");
        }

        $applied = $this->db->query("SELECT filename, checksum FROM migrations")
            ->fetchAll(PDO::FETCH_KEY_PAIR);
        $results = [];

        foreach ($filenames as $filename) {
            $basename = basename((string) $filename);
            if (strpos($basename, '.sql') !== strlen($basename) - 4) {
                $results[] = ['filename' => $basename, 'status' => 'failed', 'error' => 'Not a .sql migration file'];
                break;
            }
            $path = $this->migrationsDir . '/' . $basename;
            if (!is_file($path)) {
                $results[] = ['filename' => $basename, 'status' => 'failed', 'error' => 'Migration file not found'];
                break;
            }
            if (isset($applied[$basename]) && !$force) {
                $results[] = ['filename' => $basename, 'status' => 'skipped', 'duration_ms' => 0];
                continue;
            }

            $checksum = md5_file($path);
            $start = microtime(true);

            $dbname = defined('DB_NAME') ? DB_NAME : 'KingsWayAcademy';
            $user = defined('DB_USER') ? DB_USER : 'root';
            $pass = defined('DB_PASS') ? DB_PASS : '';

            $command = escapeshellcmd($mysql) . ' --batch --force=false -u ' . escapeshellarg($user)
                . ' -p' . escapeshellarg($pass) . ' ' . escapeshellarg($dbname)
                . ' < ' . escapeshellarg($path) . ' 2>&1';
            exec($command, $outputLines, $exitCode);

            if ($exitCode !== 0) {
                $results[] = [
                    'filename' => $basename,
                    'status' => 'failed',
                    'error' => implode(' | ', array_slice($outputLines, 0, 8)),
                ];
                break;
            }

            $duration = (int) ((microtime(true) - $start) * 1000);
            $this->db->exec(
                "INSERT INTO migrations (filename, checksum, applied_at, duration_ms) VALUES ("
                . $this->db->quote($basename) . ', '
                . $this->db->quote($checksum) . ', NOW(), ' . $duration . ')'
                . " ON DUPLICATE KEY UPDATE checksum = VALUES(checksum), applied_at = NOW(), duration_ms = VALUES(duration_ms)"
            );
            $results[] = ['filename' => $basename, 'status' => 'applied', 'duration_ms' => $duration];
        }

        return $results;
    }
}
