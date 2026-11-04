<?php
/**
 * public/components/application-status-card.php
 *
 * Komponen untuk menampilkan status lamaran pelamar.
 *
 * Variabel yang diharapkan tersedia di scope:
 *   $application (array) — data lamaran: position, status, date.
 *
 * Contoh tampilan:
 *   Application Status
 *   Frontend Developer
 *   Status: Under Review
 */
$application = $application ?? [
    'position' => 'Frontend Developer',
    'status'   => 'Under Review',
    'date'     => date( 'Y-m-d' ),
];
?>
<section class="status-table">
    <p class="eyebrow">Application Status</p>
    <div class="details">
        <div>
            <dt>Posisi</dt>
            <dd><?= htmlspecialchars( $application['position'], ENT_QUOTES, 'UTF-8' ) ?></dd>
        </div>
        <div>
            <dt>Status</dt>
            <dd><?= htmlspecialchars( $application['status'], ENT_QUOTES, 'UTF-8' ) ?></dd>
        </div>
        <div>
            <dt>Tanggal Daftar</dt>
            <dd><?= htmlspecialchars( $application['date'], ENT_QUOTES, 'UTF-8' ) ?></dd>
        </div>
    </div>
</section>
