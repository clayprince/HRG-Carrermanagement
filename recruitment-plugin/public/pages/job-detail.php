<?php

$job_id = absint( $_GET['job_id'] ?? 0 );
$vacancies_result = recruitment_get_public_jobs_result();
$job = null;
foreach ( $vacancies_result['jobs'] as $vacancy_item ) {
    if ( (int) $vacancy_item['id'] === $job_id ) {
        $job = $vacancy_item;
        break;
    }
}
?>
<div class="daw-recruitment">
    <?php require recruitment_get_plugin_path( 'public/components/header.php' ); ?>
    <main class="daw-recruitment__section"><div class="daw-recruitment__container">
        <a class="daw-recruitment__back" href="<?= esc_url( recruitment_get_public_url( 'careers' ) ) ?>">&larr; Kembali ke Lowongan</a>
        <?php if ( ! $job ) : ?>
            <section class="daw-recruitment__panel"><h1><?= $vacancies_result['success'] ? 'Lowongan tidak tersedia' : 'Lowongan belum dapat dimuat' ?></h1><p><?= $vacancies_result['success'] ? 'Lowongan yang dipilih tidak ditemukan atau sudah tidak aktif.' : 'Koneksi ke layanan data sedang bermasalah. Silakan coba kembali nanti.' ?></p><a class="daw-recruitment__button" href="<?= esc_url( recruitment_get_public_url( 'careers' ) ) ?>">Kembali ke Lowongan</a></section>
        <?php else : ?>
        <div class="daw-recruitment__detail-grid">
            <div>
                <section class="daw-recruitment__panel"><div class="daw-recruitment__job-head"><div><h1><?= esc_html( $job['title'] ) ?></h1><div class="daw-recruitment__job-meta"><span><?= esc_html( $job['type'] ) ?></span><span><?= esc_html( $job['location'] ) ?></span><span><?= esc_html( $job['dealer'] ) ?></span></div></div><span class="daw-recruitment__job-type">Open</span></div><p class="daw-recruitment__detail-meta">Diposting: <?= esc_html( $job['postedDate'] ) ?> &middot; Batas lamaran: <strong><?= esc_html( $job['deadline'] ) ?></strong></p></section>
                <section class="daw-recruitment__panel"><h2>Tentang Posisi Ini</h2><div class="daw-recruitment__detail-copy"><?= wp_kses_post( $job['description'] ) ?></div></section>
                <?php if ( ! empty( $job['requirements'] ) ) : ?><section class="daw-recruitment__panel"><h2>Pendidikan Minimum</h2><ul class="daw-recruitment__detail-list daw-recruitment__detail-list--checks"><?php foreach ( $job['requirements'] as $item ) : ?><li><?= esc_html( $item ) ?></li><?php endforeach; ?></ul></section><?php endif; ?>
            </div>
            <aside><section class="daw-recruitment__panel daw-recruitment__detail-cta"><h2>Tertarik dengan posisi ini?</h2><a class="daw-recruitment__button" href="<?= esc_url( recruitment_get_public_url( 'application-form', [ 'job_id' => $job['id'] ] ) ) ?>">Lamar Sekarang</a><a class="daw-recruitment__detail-secondary" href="<?= esc_url( recruitment_get_public_url( recruitment_is_applicant_user() ? 'applicant-dashboard' : 'applicant-login' ) ) ?>">Masuk ke Akun Pelamar</a><p>Batas lamaran: <strong><?= esc_html( $job['deadline'] ) ?></strong></p></section><section class="daw-recruitment__panel"><h2>Informasi Pekerjaan</h2><dl class="daw-recruitment__detail-info"><dt>Lokasi</dt><dd><?= esc_html( $job['location'] ) ?></dd><dt>Dealer</dt><dd><?= esc_html( $job['dealer'] ) ?></dd><dt>Jenis Pekerjaan</dt><dd><?= esc_html( $job['type'] ) ?></dd></dl></section></aside>
        </div>
        <?php endif; ?>
    </div></main>
    <?php require recruitment_get_plugin_path( 'public/components/footer.php' ); ?>
</div>
