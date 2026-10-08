<?php
/** Koneksi PDO SQLite tunggal + skema dasar. */

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $path = (string) env('DB_PATH', 'storage/keuangan.sqlite');
    $full = APP_ROOT . '/' . ltrim(str_replace('\\', '/', $path), '/');
    $dir = dirname($full);
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }

    $pdo = new PDO('sqlite:' . $full, null, null, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    $pdo->exec('PRAGMA foreign_keys = ON');
    $pdo->exec('PRAGMA busy_timeout = 5000');

    db_migrate($pdo);

    return $pdo;
}

function db_migrate(PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            username TEXT NULL,
            email TEXT NOT NULL UNIQUE COLLATE NOCASE,
            password_hash TEXT NOT NULL,
            is_verified INTEGER NOT NULL DEFAULT 0,
            verified_at TEXT NULL,
            role TEXT NOT NULL DEFAULT \'user\',
            created_at TEXT NOT NULL
        )'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS otp_codes (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            code_hash TEXT NOT NULL,
            expires_at TEXT NOT NULL,
            attempts INTEGER NOT NULL DEFAULT 0,
            created_at TEXT NOT NULL
        )'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS workspaces (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            owner_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            name TEXT NOT NULL,
            created_at TEXT NOT NULL
        )'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS workspace_members (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            workspace_id INTEGER NOT NULL REFERENCES workspaces(id) ON DELETE CASCADE,
            user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            role TEXT NOT NULL DEFAULT \'collaborator\',
            joined_at TEXT NOT NULL,
            UNIQUE (workspace_id, user_id)
        )'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS workspace_invitations (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            workspace_id INTEGER NOT NULL REFERENCES workspaces(id) ON DELETE CASCADE,
            inviter_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            invitee_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            status TEXT NOT NULL DEFAULT \'pending\',
            created_at TEXT NOT NULL,
            responded_at TEXT NULL,
            UNIQUE (workspace_id, invitee_id)
        )'
    );

    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_ws_members_user ON workspace_members (user_id)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_ws_invitations_invitee ON workspace_invitations (invitee_id)');

    // --- Migrasi ringan basis data lama ---

    $userCols = [];
    foreach ($pdo->query('PRAGMA table_info(users)') as $col) {
        $userCols[(string) $col['name']] = true;
    }

    if (!isset($userCols['role'])) {
        $pdo->exec("ALTER TABLE users ADD COLUMN role TEXT NOT NULL DEFAULT 'user'");
    }
    if (!isset($userCols['username'])) {
        $pdo->exec('ALTER TABLE users ADD COLUMN username TEXT NULL');
    }

    // Isi username yang kosong (dari awalan email, dijamin unik).
    $rows = $pdo->query("SELECT id, email FROM users WHERE username IS NULL OR username = ''")->fetchAll();
    foreach ($rows as $row) {
        $base = strtolower((string) strstr((string) $row['email'], '@', true));
        $base = (string) preg_replace('/[^a-z0-9_]+/', '', $base);
        if (strlen($base) < 3) {
            $base = 'user' . (int) $row['id'];
        }
        $candidate = $base;
        $n = 1;
        $st = $pdo->prepare('SELECT 1 FROM users WHERE username = ? COLLATE NOCASE LIMIT 1');
        while (true) {
            $st->execute([$candidate]);
            if ($st->fetch() === false) {
                break;
            }
            $n++;
            $candidate = $base . $n;
        }
        $pdo->prepare('UPDATE users SET username = ? WHERE id = ?')->execute([$candidate, (int) $row['id']]);
    }

    $pdo->exec('CREATE UNIQUE INDEX IF NOT EXISTS idx_users_username ON users (username COLLATE NOCASE)');
}
