<?php
/**
 * templates/emails/applicant-password-reset.php
 *
 * Template email permintaan reset kata sandi akun pelamar.
 *
 * Variabel yang diharapkan:
 *   $applicant_name (string)  — Nama pelamar
 *   $reset_url      (string)  — Tautan halaman reset kata sandi
 *   $expiry_minutes (int)     — Masa berlaku token (default 30 menit)
 */

$applicant_name = $applicant_name ?? 'Pelamar';
$reset_url      = $reset_url ?? home_url();
$expiry_minutes = isset( $expiry_minutes ) ? (int) $expiry_minutes : 30;

$escape = static function ( $value ): string {
    return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' );
};
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Atur Ulang Kata Sandi Akun Pelamar</title>
</head>
<body style="margin:0;padding:32px 16px;background:#f3f4f6;color:#111827;font-family:Arial,Helvetica,sans-serif;-webkit-text-size-adjust:100%;">
    <table role="presentation" style="width:100%;max-width:620px;margin:0 auto;border-collapse:separate;border-spacing:0;background:#fff;border:1px solid #e5e7eb;border-radius:14px;overflow:hidden;box-shadow:0 2px 8px rgba(0,0,0,0.04);">
        <tr>
            <td style="padding:22px 28px;background:#111827;color:#fff;font-size:15px;font-weight:700;letter-spacing:.04em;">
                DAW <span style="color:#d1d5db;font-weight:400;">| REKRUTMEN</span>
            </td>
        </tr>
        <tr>
            <td style="padding:32px 28px 24px;">
                <h1 style="margin:0 0 16px;font-size:22px;line-height:1.3;color:#111827;">Permintaan Reset Kata Sandi</h1>
                <p style="margin:0 0 14px;color:#374151;font-size:15px;">Halo <strong><?= $escape( $applicant_name ) ?></strong>,</p>
                <p style="margin:0 0 20px;color:#4b5563;line-height:1.65;font-size:14px;">
                    Kami menerima permintaan untuk mengatur ulang kata sandi akun pelamar Anda di portal Rekrutmen PT Daya Adicipta Wisesa.
                </p>
                <div style="padding:20px;background:#f9fafb;border:1px solid #e5e7eb;border-radius:10px;text-align:center;margin-bottom:24px;">
                    <p style="margin:0 0 16px;color:#4b5563;font-size:13px;line-height:1.5;">
                        Klik tombol di bawah ini untuk membuat kata sandi baru. Tautan ini hanya berlaku selama <strong><?= esc_html( $expiry_minutes ) ?> menit</strong> dan hanya dapat digunakan 1 (satu) kali:
                    </p>
                    <a href="<?= esc_url( $reset_url ) ?>" style="display:inline-block;padding:12px 28px;background:#c8102e;color:#ffffff;text-decoration:none;font-weight:700;font-size:14px;border-radius:8px;letter-spacing:.02em;">
                        Atur Ulang Kata Sandi
                    </a>
                    <?php if ( ! empty( $token ) ) : ?>
                        <div style="margin-top:16px;padding-top:14px;border-top:1px dashed #d1d5db;">
                            <span style="font-size:11px;color:#6b7280;text-transform:uppercase;letter-spacing:.05em;font-weight:700;display:block;margin-bottom:6px;">Atau Masukkan Token Ini di Halaman Reset:</span>
                            <code style="display:inline-block;background:#fff;padding:8px 14px;border:1px solid #d1d5db;border-radius:6px;font-size:13px;font-weight:700;color:#c8102e;font-family:monospace;letter-spacing:.5px;word-break:break-all;"><?= esc_html( $token ) ?></code>
                        </div>
                    <?php endif; ?>
                </div>
                <p style="margin:0 0 12px;color:#6b7280;font-size:13px;line-height:1.6;">
                    Jika tombol di atas tidak dapat diklik, salin dan tempel tautan berikut ke peramban Anda:<br>
                    <a href="<?= esc_url( $reset_url ) ?>" style="color:#c8102e;word-break:break-all;"><?= esc_html( $reset_url ) ?></a>
                </p>
                <p style="margin:20px 0 0;color:#6b7280;font-size:13px;line-height:1.6;border-top:1px dashed #e5e7eb;padding-top:16px;">
                    Jika Anda tidak pernah meminta perubahan kata sandi ini, abaikan email ini. Akun Anda tetap aman dan kata sandi tidak akan berubah.
                </p>
                <p style="margin:20px 0 0;color:#374151;font-size:14px;">
                    Salam hangat,<br>
                    <strong>Tim Rekrutmen DAW</strong>
                </p>
            </td>
        </tr>
        <tr>
            <td style="padding:16px 28px;border-top:1px solid #e5e7eb;background:#f9fafb;color:#6b7280;font-size:12px;line-height:1.5;">
                Email ini dikirim otomatis oleh sistem Rekrutmen DAW. Mohon untuk tidak membalas langsung ke alamat email ini.
            </td>
        </tr>
    </table>
</body>
</html>
