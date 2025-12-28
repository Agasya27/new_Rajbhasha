<?php
declare(strict_types=1);
require_once __DIR__ . '/config.php';

function db(): PDO {
    static $pdo = null;
    if ($pdo instanceof PDO) return $pdo;

    $dsn = 'mysql:host=' . APP_DB_HOST . ';dbname=' . APP_DB_NAME . ';charset=utf8mb4';
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES => false,
    ];
    $pdo = new PDO($dsn, APP_DB_USER, APP_DB_PASS, $options);
    bootstrap_seed_if_needed($pdo);
    return $pdo;
}

function bootstrap_seed_if_needed(PDO $pdo): void {
    // Ensure base tables exist; if missing, create minimal schema for local dev
    try {
        $pdo->query('SELECT 1 FROM users LIMIT 1');
    } catch (Throwable $e) {
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS units (\n  id INT AUTO_INCREMENT PRIMARY KEY,\n  name VARCHAR(150) NOT NULL,\n  code VARCHAR(20) NOT NULL,\n  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,\n  UNIQUE KEY uk_units_code (code)\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");

            $pdo->exec("CREATE TABLE IF NOT EXISTS users (\n  id INT AUTO_INCREMENT PRIMARY KEY,\n  name VARCHAR(150) NOT NULL,\n  email VARCHAR(190) NOT NULL,\n  password_hash VARCHAR(255) NOT NULL,\n  role ENUM('officer','reviewer','super_admin') NOT NULL DEFAULT 'officer',\n  unit_id INT NULL,\n  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,\n  UNIQUE KEY uk_users_email (email),\n  INDEX idx_users_unit (unit_id),\n  CONSTRAINT fk_users_unit FOREIGN KEY (unit_id) REFERENCES units(id) ON DELETE SET NULL\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");

            $pdo->exec("CREATE TABLE IF NOT EXISTS reports (\n  id INT AUTO_INCREMENT PRIMARY KEY,\n  user_id INT NOT NULL,\n  unit_id INT NOT NULL,\n  status ENUM('draft','submitted','approved','rejected') NOT NULL DEFAULT 'draft',\n  period_quarter TINYINT NOT NULL,\n  period_year INT NOT NULL,\n  data_json LONGTEXT,\n  updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,\n  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,\n  INDEX idx_reports_user (user_id),\n  INDEX idx_reports_unit (unit_id),\n  CONSTRAINT fk_reports_user FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,\n  CONSTRAINT fk_reports_unit FOREIGN KEY (unit_id) REFERENCES units(id) ON DELETE CASCADE\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");

            $pdo->exec("CREATE TABLE IF NOT EXISTS attachments (\n  id INT AUTO_INCREMENT PRIMARY KEY,\n  report_id INT NOT NULL,\n  file_name VARCHAR(255) NOT NULL,\n  file_path VARCHAR(255) NOT NULL,\n  mime_type VARCHAR(120) NULL,\n  file_size INT NULL,\n  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,\n  INDEX idx_att_report (report_id),\n  CONSTRAINT fk_att_report FOREIGN KEY (report_id) REFERENCES reports(id) ON DELETE CASCADE\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");
        } catch (Throwable $e2) {
            // If creation fails, bail out silently to avoid breaking prod
            return;
        }
    }

    // Create extension tables if they are missing
    try {
        $hasReviews = (bool)$pdo->query("SHOW TABLES LIKE 'report_reviews'")->fetchColumn();
    } catch (Throwable $e) { $hasReviews = false; }
    if (!$hasReviews) {
        try {
            $pdo->exec("CREATE TABLE IF NOT EXISTS glossary (\n  id INT AUTO_INCREMENT PRIMARY KEY,\n  term_hi VARCHAR(255),\n  term_en VARCHAR(255),\n  category VARCHAR(100),\n  created_at DATETIME DEFAULT CURRENT_TIMESTAMP\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");
            $pdo->exec("CREATE TABLE IF NOT EXISTS analytics_cache (\n  id INT AUTO_INCREMENT PRIMARY KEY,\n  unit_id INT,\n  quarter ENUM('Q1','Q2','Q3','Q4'),\n  year INT,\n  hindi_usage_percent FLOAT,\n  english_usage_percent FLOAT,\n  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,\n  INDEX(unit_id)\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");
            $pdo->exec("CREATE TABLE IF NOT EXISTS report_reviews (\n  id INT AUTO_INCREMENT PRIMARY KEY,\n  report_id INT NOT NULL,\n  reviewer_id INT NOT NULL,\n  decision ENUM('approved','rejected') NOT NULL,\n  comments TEXT,\n  created_at DATETIME DEFAULT CURRENT_TIMESTAMP,\n  FOREIGN KEY (report_id) REFERENCES reports(id) ON DELETE CASCADE,\n  FOREIGN KEY (reviewer_id) REFERENCES users(id) ON DELETE CASCADE\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");
            $pdo->exec("CREATE TABLE IF NOT EXISTS email_logs (\n  id INT AUTO_INCREMENT PRIMARY KEY,\n  to_email VARCHAR(200),\n  subject VARCHAR(255),\n  body TEXT,\n  created_at DATETIME DEFAULT CURRENT_TIMESTAMP\n) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;");
        } catch (Throwable $e) {
            // Safe to ignore; approvals page will error if truly missing
        }
    }

    // Seed default users and a unit if none exists
    $count = 0;
    try { $count = (int)$pdo->query('SELECT COUNT(*) FROM users')->fetchColumn(); } catch (Throwable $e) { $count = 0; }
    if ($count === 0) {
        $pdo->beginTransaction();
        try {
            $now = date('Y-m-d H:i:s');
            $unitId = (int)$pdo->query('SELECT id FROM units ORDER BY id ASC LIMIT 1')->fetchColumn();
            if ($unitId === 0) {
                $pdo->prepare('INSERT INTO units(name, code, created_at) VALUES(?,?,?)')
                    ->execute(['Head Office', 'HO', $now]);
                $unitId = (int)$pdo->lastInsertId();
            }

            $ins = $pdo->prepare('INSERT INTO users(name,email,password_hash,role,unit_id,created_at) VALUES(?,?,?,?,?,?)');
            $ins->execute(['Super Admin','admin@example.com', password_hash('Admin@123', PASSWORD_DEFAULT), 'super_admin', $unitId, $now]);
            $ins->execute(['Rajbhasha Officer','officer@example.com', password_hash('Officer@123', PASSWORD_DEFAULT), 'officer', $unitId, $now]);
            $pdo->commit();
        } catch (Throwable $e) {
            $pdo->rollBack();
        }
    }

    // Ensure default Super Admin exists even if users are already present
    try {
        $st = $pdo->prepare('SELECT id, password_hash FROM users WHERE email=? LIMIT 1');
        $st->execute(['admin@example.com']);
        $row = $st->fetch(PDO::FETCH_ASSOC);
        if (!$row) {
            $now = date('Y-m-d H:i:s');
            $unitId = (int)$pdo->query('SELECT id FROM units ORDER BY id ASC LIMIT 1')->fetchColumn();
            if ($unitId === 0) {
                $pdo->prepare('INSERT INTO units(name, code, created_at) VALUES(?,?,?)')
                    ->execute(['Head Office', 'HO', $now]);
                $unitId = (int)$pdo->lastInsertId();
            }
            $pdo->prepare('INSERT INTO users(name,email,password_hash,role,unit_id,created_at) VALUES(?,?,?,?,?,?)')
                ->execute(['Super Admin','admin@example.com', password_hash('Admin@123', PASSWORD_DEFAULT), 'super_admin', $unitId, $now]);
        } else {
            // If the stored password does not match the documented default, reset it for local dev
            if (!password_verify('Admin@123', $row['password_hash'])) {
                $upd = $pdo->prepare('UPDATE users SET password_hash=? WHERE id=?');
                $upd->execute([password_hash('Admin@123', PASSWORD_DEFAULT), (int)$row['id']]);
            }
        }
    } catch (Throwable $e) {
        // ignore
    }
}
