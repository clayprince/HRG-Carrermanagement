<?php
/**
 * templates/emails/application-status.php
 *
 * Template email notifikasi perubahan status lamaran.
 *
 * Variabel yang diharapkan tersedia di scope:
 *   $applicant_name (string) — nama pelamar
 *   $position       (string) — posisi yang dilamar
 *   $status         (string) — status baru lamaran
 *
 */
$applicant_name = $applicant_name ?? 'Pelamar';
$position       = $position ?? 'Posisi';
$status         = $status ?? 'Dalam proses';
$status_labels   = [
    'SUBMITTED' => 'Lamaran telah diterima dan menunggu pemeriksaan administrasi',
    'SCREENING' => 'Lamaran sedang ditinjau pada tahap seleksi administrasi',
    'PSIKOTES' => 'Lolos seleksi administrasi — tahap berikutnya Psikotes',
    'INTERVIEW_HR' => 'Lolos seleksi administrasi — tahap berikutnya Wawancara HR',
    'INTERVIEW_USER' => 'Lolos seleksi administrasi — tahap berikutnya Wawancara User',
    'DITOLAK' => 'Tidak lolos seleksi administrasi',
    'DITERIMA' => 'Diterima',
];
$status         = $status_labels[ strtoupper( (string) $status ) ] ?? $status;
$escape         = static function ( $value ): string {
    return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' );
};
?>
<!doctype html>
<html lang="id">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Pembaruan Status Lamaran</title></head>
<body style="margin:0;padding:32px 16px;background:#f3f4f6;color:#111827;font-family:Arial,Helvetica,sans-serif;">
    <table role="presentation" style="width:100%;max-width:620px;margin:0 auto;border-collapse:separate;border-spacing:0;background:#fff;border:1px solid #e5e7eb;border-radius:14px;overflow:hidden;">
        <tr><td style="padding:22px 28px;background:#111827;color:#fff;font-size:15px;font-weight:700;letter-spacing:.04em;">DAW <span style="color:#d1d5db;font-weight:400;">| REKRUTMEN</span></td></tr>
        <tr><td style="padding:30px 28px 12px;">
            <h1 style="margin:0 0 16px;font-size:24px;line-height:1.3;">Pembaruan Status Lamaran</h1>
            <p style="margin:0 0 12px;color:#374151;">Halo <?= $escape( $applicant_name ) ?>,</p>
            <p style="margin:0 0 22px;color:#4b5563;line-height:1.65;">Ada pembaruan untuk lamaran Anda pada posisi <strong style="color:#111827;"><?= $escape( $position ) ?></strong>.</p>
            <div style="padding:16px 18px;background:#f9fafb;border:1px solid #e5e7eb;border-radius:10px;">
                <span style="display:block;margin-bottom:6px;color:#6b7280;font-size:12px;font-weight:700;letter-spacing:.04em;text-transform:uppercase;">Status Terbaru</span>
                <strong style="color:#111827;font-size:16px;"><?= $escape( $status ) ?></strong>
            </div>
            <p style="margin:22px 0;color:#4b5563;line-height:1.65;">Silakan masuk ke fitur Tracking Lamaran untuk melihat perkembangan proses seleksi Anda.</p>
            <p style="margin:0;color:#374151;">Salam,<br><strong>Tim Rekrutmen DAW</strong></p>
        </td></tr>
        <tr><td style="padding:16px 28px;border-top:1px solid #e5e7eb;color:#6b7280;font-size:12px;">Email ini dikirim otomatis terkait lamaran Anda. Mohon tidak membalas email ini.</td></tr>
    </table>
</body>
</html>
