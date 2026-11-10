<?php
/**
 * templates/emails/attendance-reminder.php
 *
 * Template email pengingat kehadiran otomatis (H-3 dan H-1).
 *
 * Variabel yang diharapkan:
 *   $applicant_name         (string) — Nama pelamar
 *   $position               (string) — Judul lowongan / posisi
 *   $stage_label            (string) — Tahap seleksi (Psikotes / Wawancara)
 *   $reminder_stage         (string) — Pengingat H-3 atau H-1
 *   $confirmed_datetime_str (string) — Tanggal & jam kehadiran terkonfirmasi
 *   $mode                   (string) — 'Online' atau 'Offline'
 *   $location               (string) — Lokasi tes / cabang / link
 *   $instructions           (string) — Catatan atau instruksi khusus
 *   $contact_person         (string) — Kontak HR
 *   $tracking_url           (string) — Tautan portal pelamar
 */

$applicant_name         = $applicant_name ?? 'Pelamar';
$position               = $position ?? 'Posisi';
$stage_label            = $stage_label ?? 'Psikotes';
$reminder_stage         = strtoupper( (string) ( $reminder_stage ?? 'H-1' ) );
$confirmed_datetime_str = $confirmed_datetime_str ?? 'Sesuai jadwal';
$mode                   = ucfirst( (string) ( $mode ?? 'Online' ) );
$location               = $location ?? '-';
$instructions           = $instructions ?? '';
$contact_person         = $contact_person ?? 'Tim Rekrutmen HR DAW';
$tracking_url           = $tracking_url ?? home_url();

$badge_color = 'H-1' === $reminder_stage ? '#c8102e' : '#1e40af';
$badge_text  = 'H-1' === $reminder_stage ? 'PENGINGAT H-1 (BESOK)' : 'PENGINGAT H-3 (3 HARI LAGI)';

$escape = static function ( $value ): string {
    return htmlspecialchars( (string) $value, ENT_QUOTES, 'UTF-8' );
};
?>
<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Pengingat Jadwal Kehadiran <?= $escape( $stage_label ) ?> - <?= $escape( $reminder_stage ) ?></title>
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
                <div style="display:inline-block;padding:6px 14px;background:<?= esc_attr( $badge_color ) ?>;color:#fff;font-size:11px;font-weight:800;border-radius:20px;letter-spacing:.05em;margin-bottom:16px;">
                    <?= esc_html( $badge_text ) ?>
                </div>

                <h1 style="margin:0 0 16px;font-size:22px;line-height:1.3;color:#111827;">Pengingat Jadwal <?= $escape( $stage_label ) ?></h1>
                <p style="margin:0 0 14px;color:#374151;font-size:15px;">Halo <strong><?= $escape( $applicant_name ) ?></strong>,</p>
                <p style="margin:0 0 20px;color:#4b5563;line-height:1.65;font-size:14px;">
                    Ini adalah pengingat otomatis mengenai jadwal kehadiran <strong><?= $escape( $stage_label ) ?></strong> untuk posisi <strong><?= $escape( $position ) ?></strong> yang telah Anda konfirmasi:
                </p>

                <!-- Jadwal Card -->
                <div style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:10px;padding:20px;margin-bottom:24px;">
                    <table role="presentation" style="width:100%;border-collapse:collapse;font-size:14px;">
                        <tr>
                            <td style="padding:8px 0;color:#6b7280;width:140px;vertical-align:top;">Tahap Seleksi</td>
                            <td style="padding:8px 0;color:#111827;font-weight:700;">: <?= $escape( $stage_label ) ?></td>
                        </tr>
                        <tr>
                            <td style="padding:8px 0;color:#6b7280;vertical-align:top;">Waktu Kehadiran</td>
                            <td style="padding:8px 0;color:#c8102e;font-weight:700;">: <?= $escape( $confirmed_datetime_str ) ?></td>
                        </tr>
                        <tr>
                            <td style="padding:8px 0;color:#6b7280;vertical-align:top;">Metode</td>
                            <td style="padding:8px 0;color:#111827;font-weight:600;">: <?= $escape( $mode ) ?></td>
                        </tr>
                        <?php if ( ! empty( $location ) && '-' !== $location ) : ?>
                        <tr>
                            <td style="padding:8px 0;color:#6b7280;vertical-align:top;">Lokasi / Penempatan</td>
                            <td style="padding:8px 0;color:#111827;">: <?= $escape( $location ) ?></td>
                        </tr>
                        <?php endif; ?>
                        <?php if ( ! empty( $contact_person ) ) : ?>
                        <tr>
                            <td style="padding:8px 0;color:#6b7280;vertical-align:top;">Kontak HR</td>
                            <td style="padding:8px 0;color:#111827;">: <?= $escape( $contact_person ) ?></td>
                        </tr>
                        <?php endif; ?>
                    </table>
                </div>

                <?php if ( ! empty( $instructions ) ) : ?>
                <div style="background:#fffbeb;border:1px solid #fef3c7;border-radius:8px;padding:16px;margin-bottom:24px;">
                    <strong style="display:block;margin-bottom:6px;color:#92400e;font-size:13px;text-transform:uppercase;letter-spacing:.03em;">Instruksi &amp; Persiapan:</strong>
                    <p style="margin:0;color:#78350f;font-size:13px;line-height:1.6;white-space:pre-line;"><?= $escape( $instructions ) ?></p>
                </div>
                <?php endif; ?>

                <div style="text-align:center;margin:28px 0 20px;">
                    <a href="<?= esc_url( $tracking_url ) ?>" style="display:inline-block;padding:12px 28px;background:#c8102e;color:#ffffff;text-decoration:none;font-weight:700;font-size:14px;border-radius:8px;letter-spacing:.02em;">
                        Buka Portal Pelamar &amp; Tracking
                    </a>
                </div>

                <p style="margin:0;color:#6b7280;font-size:13px;line-height:1.6;">
                    Mohon hadir tepat waktu atau bersiap 15 menit sebelum jadwal dimulai. Jika ada kendala darurat, silakan segera hubungi tim HR kami.
                </p>

                <p style="margin:24px 0 0;color:#374151;font-size:14px;">
                    Salam sukses,<br>
                    <strong>Tim Rekrutmen PT Daya Adicipta Wisesa</strong>
                </p>
            </td>
        </tr>
        <tr>
            <td style="padding:16px 28px;border-top:1px solid #e5e7eb;background:#f9fafb;color:#6b7280;font-size:12px;line-height:1.5;">
                Email ini merupakan pengingat otomatis resmi terkait proses rekrutmen DAW. Mohon tidak membalas langsung ke alamat ini.
            </td>
        </tr>
    </table>
</body>
</html>
