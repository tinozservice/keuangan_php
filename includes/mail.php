<?php
/** Pengiriman email OTP via SMTP (Brevo) — tanpa dependensi eksternal. */

function mail_send_otp(string $to, string $name, string $code): bool
{
    if (env('MAIL_ENABLED', 'false') !== 'true') {
        return false;
    }

    return mail_send($to, 'Kode verifikasi Pencatat Keuangan', mail_otp_html($name, $code));
}

function mail_otp_html(string $name, string $code): string
{
    $safeName = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');
    $safeCode = htmlspecialchars($code, ENT_QUOTES, 'UTF-8');

    return '<div style="background:#FFF4E9;padding:24px;font-family:Inter,Arial,sans-serif">'
        . '<div style="max-width:480px;margin:0 auto;background:#FFFFFF;border:1px solid #F0DCC8;border-radius:6px;padding:24px">'
        . '<p style="margin:0 0 12px;font-size:14px;color:#6B4F33">Halo ' . $safeName . ',</p>'
        . '<p style="margin:0 0 16px;font-size:14px;color:#33210F">Masukkan kode berikut untuk memverifikasi email Anda. Kode berlaku 10 menit.</p>'
        . '<p style="margin:0 0 16px;font-family:Consolas,monospace;font-size:28px;font-weight:700;letter-spacing:6px;color:#9E4E00">' . $safeCode . '</p>'
        . '<p style="margin:0;font-size:12px;color:#7E5F44">Abaikan email ini bila Anda tidak merasa mendaftar di Pencatat Keuangan.</p>'
        . '</div></div>';
}

/** Kirim email HTML via SMTP; false bila gagal / belum dikonfigurasi. */
function mail_send(string $to, string $subject, string $html): bool
{
    $host = (string) env('MAIL_HOST', '');
    $port = (int) env('MAIL_PORT', '587');
    $encryption = strtolower((string) env('MAIL_ENCRYPTION', 'tls'));
    $user = (string) env('MAIL_USER', '');
    $pass = (string) env('MAIL_PASS', '');
    $from = (string) env('MAIL_FROM', '');
    $fromName = (string) env('MAIL_FROM_NAME', 'Pencatat Keuangan');

    if ($host === '' || $from === '' || $user === '' || $pass === '') {
        error_log('MAIL: konfigurasi SMTP belum lengkap.');
        return false;
    }

    $remote = ($encryption === 'ssl' ? 'ssl://' : '') . $host . ':' . $port;
    $fp = @stream_socket_client($remote, $errno, $errstr, 15);
    if ($fp === false) {
        error_log('MAIL: koneksi gagal — ' . $errstr);
        return false;
    }
    stream_set_timeout($fp, 15);

    try {
        smtp_read($fp); // sapaan awal 220
        smtp_cmd($fp, 'EHLO localhost', '250');

        if ($encryption === 'tls') {
            smtp_cmd($fp, 'STARTTLS', '220');
            if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
                throw new RuntimeException('Negosiasi TLS gagal.');
            }
            smtp_cmd($fp, 'EHLO localhost', '250');
        }

        smtp_cmd($fp, 'AUTH LOGIN', '334');
        smtp_cmd($fp, base64_encode($user), '334');
        smtp_cmd($fp, base64_encode($pass), '235');

        smtp_cmd($fp, 'MAIL FROM:<' . $from . '>', '250');
        smtp_cmd($fp, 'RCPT TO:<' . $to . '>', '250');
        smtp_cmd($fp, 'DATA', '354');

        $headers = 'From: ' . $fromName . ' <' . $from . ">\r\n"
            . 'To: <' . $to . ">\r\n"
            . 'Subject: ' . $subject . "\r\n"
            . "MIME-Version: 1.0\r\n"
            . "Content-Type: text/html; charset=UTF-8\r\n";

        fwrite($fp, $headers . "\r\n" . $html . "\r\n.\r\n");
        smtp_read($fp); // konfirmasi setelah DATA

        smtp_cmd($fp, 'QUIT', '221');
        fclose($fp);
        return true;
    } catch (Throwable $e) {
        error_log('MAIL: ' . $e->getMessage());
        @fclose($fp);
        return false;
    }
}

/** Baca respons SMTP (mendukung balasan multi-baris). */
function smtp_read($fp): string
{
    $data = '';
    while (($line = fgets($fp, 515)) !== false) {
        $data .= $line;
        if (preg_match('/^\d{3} /', $line) === 1) {
            break;
        }
    }
    return $data;
}

/** Kirim satu perintah SMTP dan pastikan kode respons sesuai harapan. */
function smtp_cmd($fp, string $command, string $expect): void
{
    fwrite($fp, $command . "\r\n");
    $response = smtp_read($fp);
    if (strncmp($response, $expect, 3) !== 0) {
        throw new RuntimeException('SMTP: "' . $command . '" → ' . trim($response));
    }
}
