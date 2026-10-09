<?php
/* Pencatat Keuangan — ekstraksi transaksi dari suara/foto via pool AI (FR-022–FR-026). */
declare(strict_types=1);

require_once __DIR__ . '/includes/init.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/workspace.php';
require_once __DIR__ . '/includes/rekening.php';
require_once __DIR__ . '/includes/ai.php';

header('Content-Type: application/json; charset=UTF-8');

$user = auth_require_login();
$uid = (int) $user['id'];

$id = (int) ($_GET['id'] ?? 0);
$ws = $id > 0 ? ws_get($id) : null;
if ($ws === null || ws_role_for_user($id, $uid) === null) {
    http_response_code(403);
    echo json_encode(['ok' => false, 'message' => 'Akses workspace tidak valid.']);
    exit;
}

csrf_require();

$jenis = (string) ($_POST['jenis'] ?? '');
$file = $_FILES['berkas'] ?? null;

if ($jenis !== 'suara' && $jenis !== 'foto') {
    echo json_encode(['ok' => false, 'message' => 'Jenis ekstraksi tidak dikenali.']);
    exit;
}
if (!is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
    echo json_encode(['ok' => false, 'message' => 'Berkas tidak terunggah (mungkin melebihi batas ukuran server).']);
    exit;
}
if ((int) $file['size'] <= 0 || (int) $file['size'] > 8 * 1024 * 1024) {
    echo json_encode(['ok' => false, 'message' => 'Ukuran berkas tidak wajar (maksimal 8 MB).']);
    exit;
}

$finfo = new finfo(FILEINFO_MIME_TYPE);
$mime = (string) $finfo->file((string) $file['tmp_name']);
$allowed = $jenis === 'suara'
    ? ['audio/webm', 'audio/ogg', 'audio/mpeg', 'audio/mp4', 'audio/wav', 'audio/x-wav', 'audio/x-m4a', 'video/webm']
    : ['image/jpeg', 'image/png', 'image/webp', 'image/gif'];
if (!in_array($mime, $allowed, true)) {
    echo json_encode(['ok' => false, 'message' => 'Jenis berkas tidak didukung (' . $mime . ').']);
    exit;
}

@set_time_limit(180);

$accounts = rek_list($id);

if ($jenis === 'suara') {
    $extension = str_contains($mime, 'webm') ? 'webm' : (str_contains($mime, 'ogg') ? 'ogg' : (str_contains($mime, 'mp4') || str_contains($mime, 'm4a') ? 'm4a' : 'wav'));
    $transcribe = ai_pool_transcribe((string) $file['tmp_name'], 'rekaman.' . $extension, $mime, ['language' => 'id']);
    if (!$transcribe['ok']) {
        echo json_encode(['ok' => false, 'message' => $transcribe['message']]);
        exit;
    }
    $transcript = trim((string) $transcribe['content']);
    if ($transcript === '') {
        echo json_encode(['ok' => false, 'message' => 'Transkrip kosong — coba bicara lebih jelas.']);
        exit;
    }
    $extract = ai_extract_from_audio_text($transcript, $accounts);
    if (!$extract['ok']) {
        echo json_encode(['ok' => false, 'message' => $extract['message'], 'transcript' => $transcript]);
        exit;
    }
    echo json_encode(['ok' => true, 'fields' => $extract['fields'], 'model' => $extract['model'], 'transcript' => $transcript]);
    exit;
}

$extract = ai_extract_from_image((string) $file['tmp_name'], $mime, $accounts);
if (!$extract['ok']) {
    echo json_encode(['ok' => false, 'message' => $extract['message']]);
    exit;
}
echo json_encode(['ok' => true, 'fields' => $extract['fields'], 'model' => $extract['model']]);
