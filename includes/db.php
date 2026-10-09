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

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS accounts (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            workspace_id INTEGER NOT NULL REFERENCES workspaces(id) ON DELETE CASCADE,
            name TEXT NOT NULL COLLATE NOCASE,
            created_at TEXT NOT NULL
        )'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS transactions (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            workspace_id INTEGER NOT NULL REFERENCES workspaces(id) ON DELETE CASCADE,
            created_by INTEGER NULL REFERENCES users(id) ON DELETE SET NULL,
            account_id INTEGER NULL REFERENCES accounts(id) ON DELETE SET NULL,
            tx_date TEXT NOT NULL,
            type TEXT NOT NULL DEFAULT \'keluar\',
            amount INTEGER NOT NULL DEFAULT 0,
            description TEXT NOT NULL,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL
        )'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS activity_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            workspace_id INTEGER NOT NULL REFERENCES workspaces(id) ON DELETE CASCADE,
            actor_id INTEGER NULL REFERENCES users(id) ON DELETE SET NULL,
            actor_username TEXT NOT NULL,
            action TEXT NOT NULL,
            object_type TEXT NOT NULL,
            object_id INTEGER NULL,
            detail TEXT NOT NULL,
            created_at TEXT NOT NULL
        )'
    );

    // Log aktivitas tingkat akun (FR-055): login serta buat/hapus workspace.
    // Tanpa FK workspace agar catatan penghapusan workspace tidak ikut terhapus.
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS user_activity_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL REFERENCES users(id) ON DELETE CASCADE,
            action TEXT NOT NULL,
            object_type TEXT NOT NULL,
            detail TEXT NOT NULL,
            created_at TEXT NOT NULL
        )'
    );

    // Pool AI (FR-039–FR-046): provider OpenAI-compatible + model fallback.
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS ai_providers (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            name TEXT NOT NULL,
            base_url TEXT NOT NULL,
            api_key TEXT NOT NULL,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL
        )'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS ai_models (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            provider_id INTEGER NOT NULL REFERENCES ai_providers(id) ON DELETE CASCADE,
            model_id TEXT NOT NULL,
            label TEXT NOT NULL DEFAULT \'\',
            input_text INTEGER NOT NULL DEFAULT 0,
            input_image INTEGER NOT NULL DEFAULT 0,
            input_audio INTEGER NOT NULL DEFAULT 0,
            input_file INTEGER NOT NULL DEFAULT 0,
            output_text INTEGER NOT NULL DEFAULT 0,
            output_audio INTEGER NOT NULL DEFAULT 0,
            output_image INTEGER NOT NULL DEFAULT 0,
            output_video INTEGER NOT NULL DEFAULT 0,
            price_in REAL NOT NULL DEFAULT 0,
            price_out REAL NOT NULL DEFAULT 0,
            is_active INTEGER NOT NULL DEFAULT 1,
            priority INTEGER NOT NULL DEFAULT 0,
            health_status TEXT NULL,
            health_detail TEXT NULL,
            health_checked_at TEXT NULL,
            created_at TEXT NOT NULL,
            updated_at TEXT NOT NULL
        )'
    );

    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_ws_members_user ON workspace_members (user_id)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_ws_invitations_invitee ON workspace_invitations (invitee_id)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_transactions_ws ON transactions (workspace_id)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_logs_ws ON activity_logs (workspace_id)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_user_logs_user ON user_activity_logs (user_id)');
    $pdo->exec('CREATE INDEX IF NOT EXISTS idx_ai_models_provider ON ai_models (provider_id)');
    $pdo->exec('CREATE UNIQUE INDEX IF NOT EXISTS idx_ai_models_unique ON ai_models (provider_id, model_id COLLATE NOCASE)');
    $pdo->exec('CREATE UNIQUE INDEX IF NOT EXISTS idx_accounts_ws_name ON accounts (workspace_id, name COLLATE NOCASE)');

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
    if (!isset($userCols['google_id'])) {
        $pdo->exec('ALTER TABLE users ADD COLUMN google_id TEXT NULL');
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
    $pdo->exec('CREATE UNIQUE INDEX IF NOT EXISTS idx_users_google ON users (google_id)');

    // Kolom `account_id` pada transaksi (basis data lama).
    $txCols = [];
    foreach ($pdo->query('PRAGMA table_info(transactions)') as $col) {
        $txCols[(string) $col['name']] = true;
    }
    if (!isset($txCols['account_id'])) {
        $pdo->exec('ALTER TABLE transactions ADD COLUMN account_id INTEGER NULL REFERENCES accounts(id) ON DELETE SET NULL');
    }

    // Normalisasi prioritas model pool AI ke jarak 10 (aturan pengguna, sekali jalan via user_version).
    $aiSchemaVersion = (int) $pdo->query('PRAGMA user_version')->fetchColumn();
    if ($aiSchemaVersion < 1) {
        $aiIds = $pdo->query('SELECT id FROM ai_models ORDER BY priority ASC, id ASC')->fetchAll(PDO::FETCH_COLUMN);
        $aiUpdate = $pdo->prepare('UPDATE ai_models SET priority = ? WHERE id = ?');
        $aiPosition = 1;
        foreach ($aiIds as $aiRowId) {
            $aiUpdate->execute([$aiPosition * 10, (int) $aiRowId]);
            $aiPosition++;
        }
        $pdo->exec('PRAGMA user_version = 1');
    }
}
