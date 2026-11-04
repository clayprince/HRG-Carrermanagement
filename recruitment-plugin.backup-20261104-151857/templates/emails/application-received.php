<?php
/**
 * templates/emails/application-received.php
 *
 * Template email konfirmasi penerimaan lamaran.
 *
 * Variabel yang diharapkan tersedia di scope:
 *   $applicant_name (string) — nama pelamar
 *   $position       (string) — posisi yang dilamar
 *   $token          (string) — token untuk cek status
 *
 * TODO: Implementasi pengiriman email menggunakan wp_mail().
 */
$applicant_name = $applicant_name ?? 'Pelamar';
$position       = $position ?? 'Posisi';
$token          = $token ?? '';
?>
<!doctype html>
<html lang="id">
<head><meta charset="utf-8"><title>Lamaran Diterima</title></head>
<body style="font-family: sans-serif; color: #17211d; background: #f4f1e9; padding: 40px;">
    <h1>Terima kasih, <?= htmlspecialchars( $applicant_name, ENT_QUOTES, 'UTF-8' ) ?>!</h1>
    <p>Lamaran kamu untuk posisi <strong><?= htmlspecialchars( $position, ENT_QUOTES, 'UTF-8' ) ?></strong> telah kami terima.</p>
    <p>Gunakan kode berikut untuk memantau status lamaran kamu:</p>
    <p style="font-size: 1.5rem; font-weight: bold;"><?= htmlspecialchars( $token, ENT_QUOTES, 'UTF-8' ) ?></p>
    <p>Tim Rekrutmen</p>
</body>
</html>
