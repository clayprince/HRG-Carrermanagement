<?php

if ( ! defined( 'ABSPATH' ) ) {
    http_response_code( 403 );
    exit;
}

$vacancies_result = recruitment_get_public_jobs_result();
$jobs = $vacancies_result['jobs'];
$dealers = array_values( array_unique( array_filter( array_column( $jobs, 'dealer' ) ) ) );
$types = array_values( array_unique( array_filter( array_column( $jobs, 'type' ) ) ) );
$search = sanitize_text_field( wp_unslash( $_GET['job_search'] ?? '' ) );
$dealer = sanitize_text_field( wp_unslash( $_GET['job_dealer'] ?? '' ) );
$type = sanitize_text_field( wp_unslash( $_GET['job_type'] ?? '' ) );
$location_count = count( array_unique( array_filter( array_column( $jobs, 'location' ) ) ) );
$department_count = count( array_unique( array_filter( array_column( $jobs, 'department' ) ) ) );
$dealer_count = count( array_unique( array_filter( array_column( $jobs, 'dealer' ) ) ) );
$jobs = array_values( array_filter( $jobs, static function ( array $job ) use ( $search, $dealer, $type ): bool {
    return ( '' === $search || false !== stripos( $job['title'], $search ) )
        && ( '' === $dealer || $job['dealer'] === $dealer )
        && ( '' === $type || $job['type'] === $type );
} ) );
?>
<div class="daw-recruitment">
    <?php require recruitment_get_plugin_path( 'public/components/header.php' ); ?>
    <section class="daw-recruitment__hero">
        <div class="daw-recruitment__hero-placeholder" aria-label="Placeholder gambar workplace DAW">DAW workplace image</div>
        <div class="daw-recruitment__hero-inner">
            <span class="daw-recruitment__eyebrow">Lowongan tersedia</span>
            <h1>Bangun Kariermu<br><span>Bersama DAW</span></h1>
            <p>Temukan peluang karier dan berkembang bersama PT. Daya Adicipta Wisesa, dealer resmi Honda di Indonesia Timur.</p>
            <div class="daw-recruitment__hero-actions"><a class="daw-recruitment__button" href="#daw-vacancies">Lihat Lowongan</a><a class="daw-recruitment__button daw-recruitment__button--outline" href="<?= esc_url( recruitment_get_public_url( recruitment_is_applicant_user() ? 'applicant-dashboard' : 'applicant-login' ) ) ?>">Akun Pelamar</a></div>
            <div class="daw-recruitment__stats"><div class="daw-recruitment__stat"><strong><?= esc_html( $vacancies_result['success'] ? count( $jobs ) : '—' ) ?></strong><span>Lowongan pada hasil saat ini</span></div><div class="daw-recruitment__stat"><strong><?= esc_html( $location_count ) ?></strong><span>Lokasi</span></div><div class="daw-recruitment__stat"><strong><?= esc_html( $department_count ) ?></strong><span>Departemen</span></div><div class="daw-recruitment__stat"><strong><?= esc_html( $dealer_count ) ?></strong><span>Dealer</span></div></div>
        </div>
    </section>
    <section class="daw-recruitment__section daw-recruitment__section--light"><div class="daw-recruitment__container">
        <div class="daw-recruitment__section-title"><h2>Mengapa Bergabung dengan DAW?</h2><p>Kami percaya bahwa karyawan adalah aset terbesar perusahaan.</p></div>
        <div class="daw-recruitment__benefits"><?php foreach ( [ [ '📈', 'Pengembangan Karier', 'Jalur karier yang jelas dengan program pengembangan terstruktur.' ], [ '🤝', 'Lingkungan Profesional', 'Budaya kerja yang kolaboratif dan berorientasi pada pertumbuhan.' ], [ '🎓', 'Kesempatan Belajar', 'Pelatihan rutin dan program mentoring dari para profesional.' ], [ '🏆', 'Berkembang Bersama Tim', 'Reward berbasis kinerja dan pengakuan atas kontribusi.' ] ] as $benefit ) : ?><article class="daw-recruitment__benefit"><div class="daw-recruitment__benefit-icon"><?= esc_html( $benefit[0] ) ?></div><h3><?= esc_html( $benefit[1] ) ?></h3><p><?= esc_html( $benefit[2] ) ?></p></article><?php endforeach; ?></div>
    </div></section>
    <section class="daw-recruitment__section" id="daw-vacancies"><div class="daw-recruitment__container">
        <div class="daw-recruitment__section-title"><h2>Temukan Posisi yang Tepat untukmu</h2><p>Jelajahi berbagai kesempatan kerja yang tersedia di jaringan DAW.</p></div>
        <form class="daw-recruitment__search" method="get"><input name="job_search" value="<?= esc_attr( $search ) ?>" placeholder="Cari posisi atau kata kunci..."><button class="daw-recruitment__button" type="submit">Cari</button></form>
        <form class="daw-recruitment__filters" method="get"><div><label for="job-dealer">Kategori DAW Dealer</label><select id="job-dealer" name="job_dealer"><option value="">Semua Dealer</option><?php foreach ( $dealers as $item ) : ?><option value="<?= esc_attr( $item ) ?>" <?= selected( $dealer, $item, false ) ?>><?= esc_html( $item ) ?></option><?php endforeach; ?></select></div><div><label for="job-type">Jenis Pekerjaan</label><select id="job-type" name="job_type"><option value="">Semua Posisi</option><?php foreach ( $types as $item ) : ?><option value="<?= esc_attr( $item ) ?>" <?= selected( $type, $item, false ) ?>><?= esc_html( $item ) ?></option><?php endforeach; ?></select></div><div class="daw-recruitment__filter-actions"><button class="daw-recruitment__button" type="submit">Cari Lowongan</button><a href="<?= esc_url( recruitment_get_public_url( 'careers' ) ) ?>">Reset Filter</a></div></form>
        <p class="daw-recruitment__result-count"><strong><?= esc_html( count( $jobs ) ) ?></strong> Lowongan tersedia</p>
        <div class="daw-recruitment__job-grid"><?php if ( ! $vacancies_result['success'] ) : ?><div class="daw-recruitment__panel"><strong>Lowongan belum dapat dimuat.</strong><p>Koneksi ke layanan data sedang bermasalah. Silakan coba kembali nanti.</p></div><?php elseif ( ! $jobs ) : ?><div class="daw-recruitment__panel"><strong>Tidak ada lowongan yang sesuai.</strong><p>Belum ada posisi yang sesuai dengan filter yang dipilih.</p></div><?php endif; ?><?php foreach ( $jobs as $i => $job ) : require recruitment_get_plugin_path( 'public/components/job-card.php' ); endforeach; ?></div>
    </div></section>
    <?php require recruitment_get_plugin_path( 'public/components/footer.php' ); ?>
</div>
