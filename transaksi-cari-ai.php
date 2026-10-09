<?php
/* Pencatat Keuangan — pencarian bahasa alami via prompt/suara (FR-027–FR-031). */
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

$jenis = (string) ($_POST['jenis'] ?? 'teks');
$accounts = rek_list($id);

if ($jenis === 'suara') {
    $file = $_FILES['berkas'] ?? null;
    if (!is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
        echo json_encode(['ok' => false, 'message' => 'Berkas tidak terunggah.']);
        exit;
    }
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = (string) $finfo->file((string) $file['tmp_name']);
    $allowed = ['audio/webm', 'audio/ogg', 'audio/mpeg', 'audio/mp4', 'audio/wav', 'audio/x-wav', 'audio/x-m4a', 'video/webm'];
    if (!in_array($mime, $allowed, true)) {
        echo json_encode(['ok' => false, 'message' => 'Jenis berkas tidak didukung (' . $mime . ').']);
        exit;
    }
    @set_time_limit(180);
    $transcribe = ai_pool_transcribe((string) $file['tmp_name'], 'cari.wav', $mime, ['language' => 'id']);
    if (!$transcribe['ok']) {
        echo json_encode(['ok' => false, 'message' => $transcribe['message']]);
        exit;
    }
    $request = trim((string) $transcribe['content']);
    if ($request === '') {
        echo json_encode(['ok' => false, 'message' => 'Transkrip kosong — coba ucapkan lagi.']);
        exit;
    }
    $search = ai_search_from_text($request, $accounts);
    if (!$search['ok']) {
        echo json_encode(['ok' => false, 'message' => $search['message'], 'transcript' => $request]);
        exit;
    }
    echo json_encode(['ok' => true, 'filter' => $search['filter'], 'model' => $search['model'], 'transcript' => $request]);
    exit;
}

$request = trim((string) ($_POST['teks'] ?? ''));
if ($request === '' || mb_strlen($request) > 200) {
    echo json_encode(['ok' => false, 'message' => 'Tulis permintaan pencarian (1–200 karakter).']);
    exit;
}
@set_time_limit(120);
$search = ai_search_from_text($request, $accounts);
if (!$search['ok']) {
    echo json_encode(['ok' => false, 'message' => $search['message']]);
    exit;
}
echo json_encode(['ok' => true, 'filter' => $search['filter'], 'model' => $search['model']]);
