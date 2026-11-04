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
 * TODO: Implementasi pengiriman email menggunakan wp_mail().
 */
$applicant_name = $applicant_name ?? 'Pelamar';
$position       = $position ?? 'Posisi';
$status         = $status ?? 'Under Review';
?>
<!doctype html>
<html lang="id">
<head><meta charset="utf-8"><title>Update Status Lamaran</title></head>
<body style="font-family: sans-serif; color: #17211d; background: #f4f1e9; padding: 40px;">
    <h1>Update Status Lamaran</h1>
    <p>Hai, <?= htmlspecialchars( $applicant_name, ENT_QUOTES, 'UTF-8' ) ?>!</p>
    <p>Ada pembaruan untuk lamaran kamu di posisi <strong><?= htmlspecialchars( $position, ENT_QUOTES, 'UTF-8' ) ?></strong>.</p>
    <p>Status terbaru: <strong><?= htmlspecialchars( $status, ENT_QUOTES, 'UTF-8' ) ?></strong></p>
    <p>Tim Rekrutmen</p>
</body>
</html>
