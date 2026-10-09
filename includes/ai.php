<?php
/** Modul AI Orchestrator — pengelolaan pool provider/model & pemeriksaan kesehatan (FR-039–FR-046). */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/pagination.php';

/** Kapabilitas input model (FR-041). */
function ai_input_caps(): array
{
    return ['text' => 'Teks', 'image' => 'Vision', 'audio' => 'Audio', 'file' => 'PDF/Berkas'];
}

/** Kapabilitas output model (FR-041). */
function ai_output_caps(): array
{
    return ['text' => 'Teks', 'audio' => 'Audio', 'image' => 'Gambar', 'video' => 'Video'];
}

/** Masking API key untuk tampilan — jangan pernah tampilkan utuh. */
function ai_mask_key(string $key): string
{
    $key = trim($key);
    if ($key === '') {
        return '—';
    }
    if (strlen($key) <= 12) {
        return substr($key, 0, 3) . '••••';
    }
    return substr($key, 0, 6) . '••••' . substr($key, -4);
}

function ai_price_display(float $value): string
{
    if ($value <= 0) {
        return '—';
    }
    return rtrim(rtrim(number_format($value, 4, '.', ''), '0'), '.');
}

// ---------------- Provider ----------------

function ai_provider_list(): array
{
    $st = db()->query('SELECT p.*, (SELECT COUNT(*) FROM ai_models m WHERE m.provider_id = p.id) AS model_count FROM ai_providers p ORDER BY p.name COLLATE NOCASE ASC, p.id ASC');
    return $st->fetchAll();
}

/** Jumlah provider. */
function ai_provider_count(): int
{
    return (int) db()->query('SELECT COUNT(*) AS c FROM ai_providers')->fetch()['c'];
}

/** Daftar provider berhalaman (urut nama) untuk tampilan Pool AI. */
function ai_provider_page(int $limit = PER_PAGE, int $offset = 0): array
{
    $limit = max(1, $limit);
    $offset = max(0, $offset);
    $st = db()->query('SELECT p.*, (SELECT COUNT(*) FROM ai_models m WHERE m.provider_id = p.id) AS model_count FROM ai_providers p ORDER BY p.name COLLATE NOCASE ASC, p.id ASC LIMIT ' . $limit . ' OFFSET ' . $offset);
    return $st->fetchAll();
}

function ai_provider_get(int $id): ?array
{
    $st = db()->prepare('SELECT * FROM ai_providers WHERE id = ? LIMIT 1');
    $st->execute([$id]);
    $row = $st->fetch();
    return $row === false ? null : $row;
}

/** Tambah/ubah provider; kembalikan [ok, pesan, id]. API key kosong saat ubah = pertahankan yang lama. */
function ai_provider_save(?int $id, string $name, string $baseUrl, string $apiKey): array
{
    $name = trim($name);
    $baseUrl = rtrim(trim($baseUrl), '/');
    $apiKey = trim($apiKey);

    if ($name === '' || mb_strlen($name) > 80) {
        return [false, 'Nama provider harus 1–80 karakter.', 0];
    }
    if ($baseUrl === '' || mb_strlen($baseUrl) > 200 || !preg_match('#^https?://[^\s]+$#i', $baseUrl)) {
        return [false, 'Base URL tidak valid (cth: https://api.openai.com/v1).', 0];
    }
    if ($id === null && $apiKey === '') {
        return [false, 'API Key wajib diisi.', 0];
    }
    if (mb_strlen($apiKey) > 300) {
        return [false, 'API Key terlalu panjang.', 0];
    }

    $pdo = db();
    $now = date('Y-m-d H:i:s');

    if ($id === null) {
        $pdo->prepare('INSERT INTO ai_providers (name, base_url, api_key, created_at, updated_at) VALUES (?, ?, ?, ?, ?)')
            ->execute([$name, $baseUrl, $apiKey, $now, $now]);
        return [true, 'Provider "' . $name . '" ditambahkan.', (int) $pdo->lastInsertId()];
    }

    $existing = ai_provider_get($id);
    if ($existing === null) {
        return [false, 'Provider tidak ditemukan.', 0];
    }
    if ($apiKey === '') {
        $pdo->prepare('UPDATE ai_providers SET name = ?, base_url = ?, updated_at = ? WHERE id = ?')
            ->execute([$name, $baseUrl, $now, $id]);
    } else {
        $pdo->prepare('UPDATE ai_providers SET name = ?, base_url = ?, api_key = ?, updated_at = ? WHERE id = ?')
            ->execute([$name, $baseUrl, $apiKey, $now, $id]);
    }
    return [true, 'Provider "' . $name . '" diperbarui.', $id];
}

/** Hapus provider (model di dalamnya ikut terhapus — ON DELETE CASCADE). */
function ai_provider_delete(int $id): void
{
    db()->prepare('DELETE FROM ai_providers WHERE id = ?')->execute([$id]);
}

// ---------------- Model ----------------

function ai_model_list(): array
{
    $st = db()->query('SELECT m.*, p.name AS provider_name FROM ai_models m JOIN ai_providers p ON p.id = m.provider_id ORDER BY m.priority ASC, m.id ASC');
    return $st->fetchAll();
}

/** Jumlah model pool. */
function ai_model_count(): int
{
    return (int) db()->query('SELECT COUNT(*) AS c FROM ai_models')->fetch()['c'];
}

/** Daftar model berhalaman (urut prioritas — urutan fallback) untuk tampilan Pool AI. */
function ai_model_page(int $limit = PER_PAGE, int $offset = 0): array
{
    $limit = max(1, $limit);
    $offset = max(0, $offset);
    $st = db()->query('SELECT m.*, p.name AS provider_name FROM ai_models m JOIN ai_providers p ON p.id = m.provider_id ORDER BY m.priority ASC, m.id ASC LIMIT ' . $limit . ' OFFSET ' . $offset);
    return $st->fetchAll();
}

function ai_model_get(int $id): ?array
{
    $st = db()->prepare('SELECT m.*, p.name AS provider_name FROM ai_models m JOIN ai_providers p ON p.id = m.provider_id WHERE m.id = ? LIMIT 1');
    $st->execute([$id]);
    $row = $st->fetch();
    return $row === false ? null : $row;
}

/** Susun ulang prioritas sesuai daftar id — unik dan berjarak 10 (10, 20, 30, …) sesuai aturan pengguna. */
function ai_model_assign_priority(array $orderedIds): void
{
    $st = db()->prepare('UPDATE ai_models SET priority = ? WHERE id = ?');
    $position = 1;
    foreach ($orderedIds as $modelId) {
        $st->execute([$position * 10, (int) $modelId]);
        $position++;
    }
}

/** Simpan model (tambah/ubah); kembalikan [ok, pesan, id]. Prioritas null = otomatis (maks + 10). */
function ai_model_save(?int $id, int $providerId, string $modelId, string $label, array $inCaps, array $outCaps, float $priceIn, float $priceOut, bool $active, ?int $priority = null): array
{
    $modelId = trim($modelId);
    $label = trim($label);

    if ($providerId <= 0 || ai_provider_get($providerId) === null) {
        return [false, 'Provider tidak valid.', 0];
    }
    if ($modelId === '' || mb_strlen($modelId) > 150 || preg_match('/[\x00-\x1F\x7F]/', $modelId) === 1) {
        return [false, 'ID model harus 1–150 karakter tanpa karakter kontrol.', 0];
    }
    if (mb_strlen($label) > 100) {
        return [false, 'Label maksimal 100 karakter.', 0];
    }

    $inCaps = array_values(array_intersect($inCaps, array_keys(ai_input_caps())));
    $outCaps = array_values(array_intersect($outCaps, array_keys(ai_output_caps())));
    if ($inCaps === []) {
        return [false, 'Pilih minimal satu kapabilitas input.', 0];
    }
    if ($priceIn < 0 || $priceIn > 100000 || $priceOut < 0 || $priceOut > 100000) {
        return [false, 'Harga token di luar rentang wajar (0–100000 USD per 1 juta token).', 0];
    }
    if ($priority !== null && ($priority < 1 || $priority > 1000000)) {
        return [false, 'Prioritas harus antara 1 dan 1.000.000.', 0];
    }

    $pdo = db();
    $now = date('Y-m-d H:i:s');

    $st = $pdo->prepare('SELECT id FROM ai_models WHERE provider_id = ? AND model_id = ? COLLATE NOCASE AND (? = 0 OR id <> ?) LIMIT 1');
    $st->execute([$providerId, $modelId, (int) ($id ?? 0), (int) ($id ?? 0)]);
    if ($st->fetch() !== false) {
        return [false, 'Model "' . $modelId . '" sudah ada pada provider tersebut.', 0];
    }

    $flags = [
        'input_text' => in_array('text', $inCaps, true) ? 1 : 0,
        'input_image' => in_array('image', $inCaps, true) ? 1 : 0,
        'input_audio' => in_array('audio', $inCaps, true) ? 1 : 0,
        'input_file' => in_array('file', $inCaps, true) ? 1 : 0,
        'output_text' => in_array('text', $outCaps, true) ? 1 : 0,
        'output_audio' => in_array('audio', $outCaps, true) ? 1 : 0,
        'output_image' => in_array('image', $outCaps, true) ? 1 : 0,
        'output_video' => in_array('video', $outCaps, true) ? 1 : 0,
    ];
    $activeFlag = $active ? 1 : 0;

    if ($id === null) {
        $max = (int) $pdo->query('SELECT COALESCE(MAX(priority), 0) AS m FROM ai_models')->fetch()['m'];
        $priorityValue = $priority ?? ($max + 10);
        $pdo->prepare('INSERT INTO ai_models (provider_id, model_id, label, input_text, input_image, input_audio, input_file, output_text, output_audio, output_image, output_video, price_in, price_out, is_active, priority, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)')
            ->execute([
                $providerId, $modelId, $label,
                $flags['input_text'], $flags['input_image'], $flags['input_audio'], $flags['input_file'],
                $flags['output_text'], $flags['output_audio'], $flags['output_image'], $flags['output_video'],
                $priceIn, $priceOut, $activeFlag, $priorityValue, $now, $now,
            ]);
        return [true, 'Model "' . $modelId . '" ditambahkan.', (int) $pdo->lastInsertId()];
    }

    $existing = ai_model_get($id);
    if ($existing === null) {
        return [false, 'Model tidak ditemukan.', 0];
    }
    $priorityValue = $priority ?? (int) $existing['priority'];
    $pdo->prepare('UPDATE ai_models SET provider_id = ?, model_id = ?, label = ?, input_text = ?, input_image = ?, input_audio = ?, input_file = ?, output_text = ?, output_audio = ?, output_image = ?, output_video = ?, price_in = ?, price_out = ?, is_active = ?, priority = ?, updated_at = ? WHERE id = ?')
        ->execute([
            $providerId, $modelId, $label,
            $flags['input_text'], $flags['input_image'], $flags['input_audio'], $flags['input_file'],
            $flags['output_text'], $flags['output_audio'], $flags['output_image'], $flags['output_video'],
            $priceIn, $priceOut, $activeFlag, $priorityValue, $now, $id,
        ]);
    return [true, 'Model "' . $modelId . '" diperbarui.', $id];
}

/** Aktif/nonaktif model (FR-046). */
function ai_model_toggle(int $id): void
{
    db()->prepare('UPDATE ai_models SET is_active = CASE WHEN is_active = 1 THEN 0 ELSE 1 END, updated_at = ? WHERE id = ?')
        ->execute([date('Y-m-d H:i:s'), $id]);
}

/** Geser prioritas model (FR-042): naik/turun satu posisi; prioritas dinormalkan 1..n. */
function ai_model_move(int $id, string $direction): void
{
    $rows = db()->query('SELECT id FROM ai_models ORDER BY priority ASC, id ASC')->fetchAll();
    $ids = array_map(static fn (array $row): int => (int) $row['id'], $rows);
    $index = array_search($id, $ids, true);
    if ($index === false) {
        return;
    }
    $target = $direction === 'naik' ? $index - 1 : $index + 1;
    if ($target < 0 || $target >= count($ids)) {
        return;
    }
    [$ids[$index], $ids[$target]] = [$ids[$target], $ids[$index]];
    ai_model_assign_priority($ids);
}

function ai_model_delete(int $id): void
{
    db()->prepare('DELETE FROM ai_models WHERE id = ?')->execute([$id]);
}

/** Label kapabilitas model untuk tampilan (kind: 'input' | 'output'). */
function ai_model_cap_labels(array $model, string $kind): array
{
    $map = $kind === 'input' ? ai_input_caps() : ai_output_caps();
    $labels = [];
    foreach ($map as $key => $label) {
        if ((int) ($model[$kind . '_' . $key] ?? 0) === 1) {
            $labels[] = $label;
        }
    }
    return $labels;
}

// ---------------- Pemeriksaan kesehatan (FR-043–FR-045) ----------------

/** Ringkasan pool untuk halaman kesehatan (FR-044). */
function ai_pool_summary(): array
{
    $pdo = db();
    return [
        'providers' => (int) $pdo->query('SELECT COUNT(*) AS c FROM ai_providers')->fetch()['c'],
        'models' => (int) $pdo->query('SELECT COUNT(*) AS c FROM ai_models')->fetch()['c'],
        'active' => (int) $pdo->query('SELECT COUNT(*) AS c FROM ai_models WHERE is_active = 1')->fetch()['c'],
        'ok' => (int) $pdo->query("SELECT COUNT(*) AS c FROM ai_models WHERE is_active = 1 AND health_status = 'ok'")->fetch()['c'],
        'error' => (int) $pdo->query("SELECT COUNT(*) AS c FROM ai_models WHERE is_active = 1 AND health_status = 'error'")->fetch()['c'],
        'unchecked' => (int) $pdo->query("SELECT COUNT(*) AS c FROM ai_models WHERE is_active = 1 AND (health_status IS NULL OR health_status = '')")->fetch()['c'],
    ];
}

/** Detail pesan error HTTP yang ringkas. */
function ai_http_error_detail(int $status, ?array $data, string $curlError): string
{
    if ($status === 0) {
        return mb_substr('Koneksi gagal: ' . ($curlError !== '' ? $curlError : 'tidak diketahui'), 0, 200);
    }
    $message = '';
    if (is_array($data)) {
        $error = $data['error'] ?? null;
        if (is_array($error)) {
            $message = (string) ($error['message'] ?? '');
        } elseif (is_string($error)) {
            $message = $error;
        }
        if ($message === '') {
            $message = (string) ($data['message'] ?? '');
        }
    }
    $message = trim($message);
    return mb_substr('HTTP ' . $status . ($message !== '' ? ': ' . $message : ''), 0, 200);
}

/** Panggilan HTTP JSON ke provider (cURL). Kembalikan [status, data, error]. */
function ai_http_json(string $method, string $url, string $apiKey, ?array $jsonBody = null, int $timeout = 20): array
{
    $headers = ['Authorization: Bearer ' . $apiKey, 'Accept: application/json'];
    if ($jsonBody !== null) {
        $headers[] = 'Content-Type: application/json';
    }

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => $timeout,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_HTTPHEADER => $headers,
    ]);
    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonBody === null ? '' : (string) json_encode($jsonBody));
    }

    $body = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $error = (string) curl_error($ch);
    curl_close($ch);

    if ($body === false) {
        return [0, null, $error];
    }
    $data = json_decode((string) $body, true);
    return [$status, is_array($data) ? $data : null, ''];
}

/** WAV senyap kecil untuk uji endpoint audio (0,3 detik, 16 kHz mono 16-bit). */
function ai_silent_wav(): string
{
    $rate = 16000;
    $dataSize = (int) ($rate * 0.3) * 2;
    $header = 'RIFF' . pack('V', 36 + $dataSize) . 'WAVE'
        . 'fmt ' . pack('V', 16) . pack('v', 1) . pack('v', 1)
        . pack('V', $rate) . pack('V', $rate * 2) . pack('v', 2) . pack('v', 16)
        . 'data' . pack('V', $dataSize);
    return $header . str_repeat("\x00", $dataSize);
}

/** Ping model lewat chat/completions; untuk model audio-saja lewat audio/transcriptions. */
function ai_health_ping(int $modelId): array
{
    $model = ai_model_get($modelId);
    if ($model === null) {
        return ['error', 'Model tidak ditemukan.'];
    }
    $provider = ai_provider_get((int) $model['provider_id']);
    if ($provider === null) {
        return ['error', 'Provider tidak ditemukan.'];
    }

    $base = rtrim((string) $provider['base_url'], '/');
    $key = (string) $provider['api_key'];
    $apiModel = (string) $model['model_id'];

    $textCapable = (int) $model['input_text'] === 1 || (int) $model['input_image'] === 1 || (int) $model['input_file'] === 1;
    if (!$textCapable && (int) $model['input_audio'] === 1) {
        return ai_health_ping_audio($base, $key, $apiModel);
    }

    [$status, $data, $error] = ai_http_json('POST', $base . '/chat/completions', $key, [
        'model' => $apiModel,
        'messages' => [['role' => 'user', 'content' => 'ping']],
        'max_tokens' => 5,
    ]);
    if ($status === 200 && is_array($data) && isset($data['choices'])) {
        return ['ok', 'HTTP 200'];
    }
    return ['error', ai_http_error_detail($status, $data, $error)];
}

/** Ping model audio-only via audio/transcriptions dengan WAV senyap. */
function ai_health_ping_audio(string $base, string $apiKey, string $model): array
{
    $path = tempnam(sys_get_temp_dir(), 'aiwav');
    if ($path === false) {
        return ['error', 'Gagal menyiapkan berkas uji audio.'];
    }
    file_put_contents($path, ai_silent_wav());

    $ch = curl_init($base . '/audio/transcriptions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => ['model' => $model, 'file' => new CURLFile($path, 'audio/wav', 'uji.wav')],
        CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $apiKey, 'Accept: application/json'],
    ]);
    $body = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $error = (string) curl_error($ch);
    curl_close($ch);
    @unlink($path);

    if ($body === false) {
        return ['error', ai_http_error_detail(0, null, $error)];
    }
    $data = json_decode((string) $body, true);
    if ($status === 200 && is_array($data) && array_key_exists('text', $data)) {
        return ['ok', 'HTTP 200'];
    }
    return ['error', ai_http_error_detail($status, is_array($data) ? $data : null, '')];
}

/** Uji satu model (tombol Uji/selektif) & simpan hasil terakhir (FR-045). Kembalikan [status, detail]. */
function ai_health_check_model(int $modelId): array
{
    [$status, $detail] = ai_health_ping($modelId);
    db()->prepare('UPDATE ai_models SET health_status = ?, health_detail = ?, health_checked_at = ? WHERE id = ?')
        ->execute([$status, $detail, date('Y-m-d H:i:s'), $modelId]);
    return [$status, $detail];
}

/** Periksa semua model aktif sekaligus (FR-043) & simpan hasil terakhir (FR-045). */
function ai_health_check_all(): array
{
    $models = db()->query('SELECT id FROM ai_models WHERE is_active = 1 ORDER BY priority ASC, id ASC')->fetchAll();
    $ok = 0;
    $error = 0;
    foreach ($models as $row) {
        [$status] = ai_health_check_model((int) $row['id']);
        if ($status === 'ok') {
            $ok++;
        } else {
            $error++;
        }
    }
    return ['total' => count($models), 'ok' => $ok, 'error' => $error];
}

// ---------------- Orkestrasi pool & pencatatan pemakaian (FR-025, FR-047) ----------------

/** Kandidat model aktif untuk kapabilitas input tertentu (urut prioritas fallback). */
function ai_pool_candidates(string $inputCap = 'text', bool $requireOutputText = true): array
{
    $valid = ['text', 'image', 'audio', 'file'];
    if (!in_array($inputCap, $valid, true)) {
        $inputCap = 'text';
    }
    $column = 'input_' . $inputCap;

    $st = db()->query('SELECT m.*, p.name AS provider_name, p.base_url, p.api_key FROM ai_models m JOIN ai_providers p ON p.id = m.provider_id WHERE m.is_active = 1 ORDER BY m.priority ASC, m.id ASC');
    $candidates = [];
    foreach ($st->fetchAll() as $m) {
        if ((int) $m[$column] !== 1) {
            continue;
        }
        if ($requireOutputText && (int) $m['output_text'] !== 1) {
            continue;
        }
        $candidates[] = $m;
    }
    return $candidates;
}

/** Catat satu pemakaian model (FR-047). */
function ai_usage_record(array $model, string $kind, string $status, int $tokensIn, int $tokensOut, int $latencyMs): void
{
    db()->prepare('INSERT INTO ai_usage_logs (model_row_id, model_id, provider_name, kind, status, tokens_in, tokens_out, latency_ms, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)')
        ->execute([(int) $model['id'], (string) $model['model_id'], (string) $model['provider_name'], $kind, $status, $tokensIn, $tokensOut, $latencyMs, date('Y-m-d H:i:s')]);
}

/**
 * Panggil model pool via chat/completions mengikuti urutan prioritas (FR-025);
 * model yang gagal dilewati ke model berikutnya; setiap percobaan dicatat (FR-047).
 * $messages format chat OpenAI; $inputCap = text|image|file.
 * Kembalikan ['ok','content','model','attempts','message'].
 */
function ai_pool_chat(array $messages, string $inputCap = 'text', array $options = []): array
{
    $candidates = ai_pool_candidates($inputCap);
    if ($candidates === []) {
        return ['ok' => false, 'content' => null, 'model' => '', 'attempts' => 0, 'message' => 'Tidak ada model aktif dengan kapabilitas yang dibutuhkan.'];
    }

    $maxTokens = (int) ($options['max_tokens'] ?? 512);
    $timeout = (int) ($options['timeout'] ?? 60);
    $attempts = 0;
    $lastError = '';

    foreach ($candidates as $m) {
        $attempts++;
        $payload = ['model' => (string) $m['model_id'], 'messages' => $messages, 'max_tokens' => $maxTokens];
        if (isset($options['temperature'])) {
            $payload['temperature'] = (float) $options['temperature'];
        }

        $start = microtime(true);
        [$status, $data, $error] = ai_http_json('POST', rtrim((string) $m['base_url'], '/') . '/chat/completions', (string) $m['api_key'], $payload, $timeout);
        $latency = (int) round((microtime(true) - $start) * 1000);

        if ($status === 200 && is_array($data) && isset($data['choices'][0]['message']['content'])) {
            ai_usage_record($m, 'chat', 'ok', (int) ($data['usage']['prompt_tokens'] ?? 0), (int) ($data['usage']['completion_tokens'] ?? 0), $latency);
            return ['ok' => true, 'content' => (string) $data['choices'][0]['message']['content'], 'model' => (string) $m['model_id'], 'attempts' => $attempts, 'message' => ''];
        }

        ai_usage_record($m, 'chat', 'error', 0, 0, $latency);
        $lastError = ai_http_error_detail($status, $data, $error);
    }

    return ['ok' => false, 'content' => null, 'model' => '', 'attempts' => $attempts, 'message' => 'Semua model gagal (' . $attempts . ' percobaan). Terakhir: ' . $lastError];
}

/**
 * Transkripsi audio via model pool (FR-025 + FR-047), fallback berantai.
 * $filePath harus berkas lokal yang dapat dibaca cURL.
 */
function ai_pool_transcribe(string $filePath, string $filename, string $mime, array $options = []): array
{
    $candidates = ai_pool_candidates('audio', false);
    if ($candidates === []) {
        return ['ok' => false, 'content' => null, 'model' => '', 'attempts' => 0, 'message' => 'Tidak ada model audio aktif.'];
    }

    $timeout = (int) ($options['timeout'] ?? 120);
    $language = (string) ($options['language'] ?? 'id');
    $attempts = 0;
    $lastError = '';

    foreach ($candidates as $m) {
        $attempts++;
        $ch = curl_init(rtrim((string) $m['base_url'], '/') . '/audio/transcriptions');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT => $timeout,
            CURLOPT_CONNECTTIMEOUT => 8,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => ['model' => (string) $m['model_id'], 'file' => new CURLFile($filePath, $mime, $filename), 'language' => $language],
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . (string) $m['api_key'], 'Accept: application/json'],
        ]);
        $start = microtime(true);
        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        $error = (string) curl_error($ch);
        curl_close($ch);
        $latency = (int) round((microtime(true) - $start) * 1000);

        if ($body !== false) {
            $data = json_decode((string) $body, true);
            if ($status === 200 && is_array($data) && array_key_exists('text', $data)) {
                // Endpoint transkripsi umumnya tidak mengembalikan jumlah token.
                ai_usage_record($m, 'audio', 'ok', 0, 0, $latency);
                return ['ok' => true, 'content' => (string) $data['text'], 'model' => (string) $m['model_id'], 'attempts' => $attempts, 'message' => ''];
            }
            $lastError = ai_http_error_detail($status, is_array($data) ? $data : null, '');
        } else {
            $lastError = ai_http_error_detail(0, null, $error);
        }

        ai_usage_record($m, 'audio', 'error', 0, 0, $latency);
    }

    return ['ok' => false, 'content' => null, 'model' => '', 'attempts' => $attempts, 'message' => 'Semua model audio gagal (' . $attempts . ' percobaan). Terakhir: ' . $lastError];
}

// ---------------- Usage & biaya (FR-047–FR-049) ----------------

function ai_setting_get(string $key, string $default = ''): string
{
    $st = db()->prepare('SELECT value FROM settings WHERE key = ? LIMIT 1');
    $st->execute([$key]);
    $row = $st->fetch();
    return $row === false ? $default : (string) $row['value'];
}

function ai_setting_set(string $key, string $value): void
{
    db()->prepare('INSERT INTO settings (key, value, updated_at) VALUES (?, ?, ?) ON CONFLICT(key) DO UPDATE SET value = excluded.value, updated_at = excluded.updated_at')
        ->execute([$key, $value, date('Y-m-d H:i:s')]);
}

function ai_setting_updated(string $key): string
{
    $st = db()->prepare('SELECT updated_at FROM settings WHERE key = ? LIMIT 1');
    $st->execute([$key]);
    $row = $st->fetch();
    return $row === false ? '' : (string) $row['updated_at'];
}

/** Kurs USD→IDR aktif (0 = belum diatur). */
function ai_usd_idr_rate(): float
{
    return (float) ai_setting_get('usd_idr_rate', '0');
}

/** Rentang waktu preset untuk halaman usage. Kembalikan [from, to, label, preset]. */
function ai_usage_range(string $preset): array
{
    $now = date('Y-m-d H:i:s');
    switch ($preset) {
        case 'hari-ini':
            return [date('Y-m-d 00:00:00'), $now, 'Hari ini', 'hari-ini'];
        case '30-hari':
            return [date('Y-m-d 00:00:00', strtotime('-29 days')), $now, '30 hari terakhir', '30-hari'];
        case 'bulan-ini':
            return [date('Y-m-01 00:00:00'), $now, 'Bulan ini', 'bulan-ini'];
        default:
            return [date('Y-m-d 00:00:00', strtotime('-6 days')), $now, '7 hari terakhir', '7-hari'];
    }
}

/** Jumlah baris usage dalam rentang. */
function ai_usage_count(string $from, string $to): int
{
    $st = db()->prepare('SELECT COUNT(*) AS c FROM ai_usage_logs WHERE created_at >= ? AND created_at <= ?');
    $st->execute([$from, $to]);
    return (int) ($st->fetch()['c'] ?? 0);
}

/** Panggilan terakhir (berhalaman) untuk halaman usage. */
function ai_usage_recent(string $from, string $to, int $limit = PER_PAGE, int $offset = 0): array
{
    $limit = max(1, $limit);
    $offset = max(0, $offset);
    $st = db()->prepare('SELECT * FROM ai_usage_logs WHERE created_at >= ? AND created_at <= ? ORDER BY created_at DESC, id DESC LIMIT ' . $limit . ' OFFSET ' . $offset);
    $st->execute([$from, $to]);
    return $st->fetchAll();
}

/** Agregat usage per model + harga token terkini untuk estimasi biaya (FR-048). */
function ai_usage_by_model(string $from, string $to): array
{
    $st = db()->prepare("SELECT u.provider_name, u.model_id,
            SUM(u.tokens_in) AS tokens_in, SUM(u.tokens_out) AS tokens_out,
            COUNT(*) AS calls,
            SUM(CASE WHEN u.status = 'ok' THEN 1 ELSE 0 END) AS calls_ok,
            MAX(m.price_in) AS price_in, MAX(m.price_out) AS price_out
        FROM ai_usage_logs u
        LEFT JOIN ai_providers p ON p.name = u.provider_name
        LEFT JOIN ai_models m ON m.provider_id = p.id AND m.model_id = u.model_id COLLATE NOCASE
        WHERE u.created_at >= ? AND u.created_at <= ?
        GROUP BY u.provider_name, u.model_id
        ORDER BY u.provider_name COLLATE NOCASE ASC, u.model_id COLLATE NOCASE ASC");
    $st->execute([$from, $to]);

    $rows = [];
    foreach ($st->fetchAll() as $row) {
        $tokensIn = (int) $row['tokens_in'];
        $tokensOut = (int) $row['tokens_out'];
        $hasPrice = $row['price_in'] !== null || $row['price_out'] !== null;
        $usd = $hasPrice
            ? $tokensIn / 1000000 * (float) $row['price_in'] + $tokensOut / 1000000 * (float) $row['price_out']
            : null;
        $rows[] = [
            'provider_name' => (string) $row['provider_name'],
            'model_id' => (string) $row['model_id'],
            'calls' => (int) $row['calls'],
            'calls_ok' => (int) $row['calls_ok'],
            'tokens_in' => $tokensIn,
            'tokens_out' => $tokensOut,
            'usd' => $usd,
        ];
    }
    return $rows;
}

/** Format biaya USD ringkas (desimal lebih banyak untuk nilai sangat kecil). */
function ai_usd_display(float $usd): string
{
    if ($usd <= 0) {
        return '0';
    }
    $decimals = $usd < 0.01 ? 6 : 4;
    $text = number_format($usd, $decimals, '.', '');
    return rtrim(rtrim($text, '0'), '.');
}
