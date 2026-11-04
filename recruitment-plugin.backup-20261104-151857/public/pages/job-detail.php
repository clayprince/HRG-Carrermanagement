<?php

$job_id = absint( $_GET['job_id'] ?? 0 );
$vacancy = $job_id ? get_post( $job_id ) : null;
$job = recruitment_get_public_job( $job_id ) ?: recruitment_get_public_job( 1 );
$job['responsibilities'] = [ 'Melayani calon pembeli kendaraan Honda', 'Mencapai target penjualan bulanan', 'Melakukan follow-up kepada prospek pelanggan', 'Membuat laporan penjualan harian' ];
if ( $vacancy && 'daw_vacancy' === $vacancy->post_type ) {
    $job = recruitment_map_vacancy( $vacancy );
    $job['description'] = wpautop( wp_kses_post( $vacancy->post_content ) );
    $job['responsibilities'] = [ 'Melayani calon pembeli kendaraan Honda', 'Mencapai target penjualan bulanan', 'Melakukan follow-up kepada prospek pelanggan', 'Membuat laporan penjualan harian' ];
}
?>
<div class="daw-recruitment">
    <?php require recruitment_get_plugin_path( 'public/components/header.php' ); ?>
    <main class="daw-recruitment__section"><div class="daw-recruitment__container">
        <a class="daw-recruitment__back" href="<?= esc_url( recruitment_get_public_url( 'careers' ) ) ?>">&larr; Kembali ke Lowongan</a>
        <div class="daw-recruitment__detail-grid">
            <div>
                <section class="daw-recruitment__panel"><div class="daw-recruitment__job-head"><div><h1><?= esc_html( $job['title'] ) ?></h1><div class="daw-recruitment__job-meta"><span><?= esc_html( $job['type'] ) ?></span><span><?= esc_html( $job['location'] ) ?></span><span><?= esc_html( $job['dealer'] ) ?></span></div></div><span class="daw-recruitment__job-type">Open</span></div><p class="daw-recruitment__detail-meta">Diposting: <?= esc_html( $job['postedDate'] ) ?> &middot; Batas lamaran: <strong><?= esc_html( $job['deadline'] ) ?></strong></p></section>
                <section class="daw-recruitment__panel"><h2>Tentang Posisi Ini</h2><div class="daw-recruitment__detail-copy"><?= wp_kses_post( $job['description'] ) ?></div></section>
                <section class="daw-recruitment__panel"><h2>Tanggung Jawab</h2><ol class="daw-recruitment__detail-list"><?php foreach ( $job['responsibilities'] as $item ) : ?><li><?= esc_html( $item ) ?></li><?php endforeach; ?></ol></section>
                <section class="daw-recruitment__panel"><h2>Persyaratan</h2><ul class="daw-recruitment__detail-list daw-recruitment__detail-list--checks"><?php foreach ( $job['requirements'] as $item ) : ?><li><?= esc_html( $item ) ?></li><?php endforeach; ?></ul></section>
            </div>
            <aside><section class="daw-recruitment__panel daw-recruitment__detail-cta"><h2>Tertarik dengan posisi ini?</h2><a class="daw-recruitment__button" href="<?= esc_url( recruitment_get_public_url( 'application', [ 'job_id' => $job['id'] ] ) ) ?>">Lamar Sekarang</a><a class="daw-recruitment__detail-secondary" href="<?= esc_url( recruitment_get_public_url( recruitment_is_applicant_user() ? 'application-history' : 'applicant-login' ) ) ?>">Masuk ke Akun Pelamar</a><p>Batas lamaran: <strong><?= esc_html( $job['deadline'] ) ?></strong></p></section><section class="daw-recruitment__panel"><h2>Informasi Pekerjaan</h2><dl class="daw-recruitment__detail-info"><dt>Lokasi</dt><dd><?= esc_html( $job['location'] ) ?></dd><dt>Dealer</dt><dd><?= esc_html( $job['dealer'] ) ?></dd><dt>Wilayah</dt><dd><?= esc_html( $job['region'] ) ?></dd><dt>Jenis Pekerjaan</dt><dd><?= esc_html( $job['type'] ) ?></dd></dl></section></aside>
        </div>
    </div></main>
    <?php require recruitment_get_plugin_path( 'public/components/footer.php' ); ?>
</div>
