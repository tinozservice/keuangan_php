<?php
/** Modul AI Orchestrator — pengelolaan pool provider/model & pemeriksaan kesehatan (FR-039–FR-046). */
require_once __DIR__ . '/db.php';

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

/** Periksa semua model aktif sekaligus (FR-043) & simpan hasil terakhir (FR-045). */
function ai_health_check_all(): array
{
    $pdo = db();
    $models = $pdo->query('SELECT id FROM ai_models WHERE is_active = 1 ORDER BY priority ASC, id ASC')->fetchAll();
    $ok = 0;
    $error = 0;
    $st = $pdo->prepare('UPDATE ai_models SET health_status = ?, health_detail = ?, health_checked_at = ? WHERE id = ?');
    foreach ($models as $row) {
        [$status, $detail] = ai_health_ping((int) $row['id']);
        $st->execute([$status, $detail, date('Y-m-d H:i:s'), (int) $row['id']]);
        if ($status === 'ok') {
            $ok++;
        } else {
            $error++;
        }
    }
    return ['total' => count($models), 'ok' => $ok, 'error' => $error];
}
