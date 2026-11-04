<?php
/**
 * public/components/job-card.php
 *
 * Komponen card untuk satu lowongan kerja.
 *
 * Variabel yang diharapkan tersedia di scope:
 *   $i   (int)    — index (0-based), digunakan untuk label nomor.
 *   $job (array)  — data lowongan: title, dept, type.
 *
 * Contoh tampilan:
 *   Frontend Developer
 *   Technology
 *   Full Time
 *   [View Job]
 */
?>
<article class="daw-recruitment__job">
    <div class="daw-recruitment__job-head"><h3><?= esc_html( $job['title'] ?? 'Lowongan' ) ?></h3><span class="daw-recruitment__job-type"><?= esc_html( $job['type'] ?? 'Full Time' ) ?></span></div>
    <div class="daw-recruitment__job-meta"><span><?= esc_html( $job['location'] ?? '' ) ?></span><span><?= esc_html( $job['dealer'] ?? '' ) ?></span></div>
    <p><?= esc_html( $job['description'] ?? '' ) ?></p>
    <div class="daw-recruitment__job-requirements"><strong>Persyaratan utama:</strong><ul><?php foreach ( array_slice( $job['requirements'] ?? [], 0, 2 ) as $requirement ) : ?><li><?= esc_html( $requirement ) ?></li><?php endforeach; ?></ul></div>
    <div class="daw-recruitment__job-actions"><a href="<?= esc_url( recruitment_get_public_url( 'job-detail', [ 'job_id' => $job['id'] ?? '' ] ) ) ?>">Lihat Detail</a><a href="<?= esc_url( recruitment_get_public_url( 'application', [ 'job_id' => $job['id'] ?? '' ] ) ) ?>">Lamar Sekarang</a></div>
</article>
