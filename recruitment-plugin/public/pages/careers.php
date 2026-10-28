<?php

if ( ! defined( 'ABSPATH' ) ) {
    http_response_code( 403 );
    exit;
}

$jobs = recruitment_get_public_jobs();
$dealers = [ 'DAW Bitung', 'DAW Main Dealer Maumbi' ];
$types = [
    'Sales',
    'Marketing',
    'Administration',
    'Human Capital / HR',
    'Finance & Accounting',
    'Information Technology',
    'Parts',
    'Technical Service',
    'Customer Care / HC3',
    'Warehouse / Logistics',
    'Management Trainee',
    'Other',
];
$search = sanitize_text_field( wp_unslash( $_GET['job_search'] ?? '' ) );
$dealer = sanitize_text_field( wp_unslash( $_GET['job_dealer'] ?? '' ) );
$type = sanitize_text_field( wp_unslash( $_GET['job_type'] ?? '' ) );
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
            <div class="daw-recruitment__hero-actions"><a class="daw-recruitment__button" href="#daw-vacancies">Lihat Lowongan</a><a class="daw-recruitment__button daw-recruitment__button--outline" href="<?= esc_url( recruitment_get_public_url( recruitment_is_applicant_user() ? 'application-history' : 'applicant-login' ) ) ?>">Akun Pelamar</a></div>
            <div class="daw-recruitment__stats"><div class="daw-recruitment__stat"><strong><?= esc_html( count( $jobs ) ) ?></strong><span>Lowongan Aktif</span></div><div class="daw-recruitment__stat"><strong>3</strong><span>Wilayah</span></div><div class="daw-recruitment__stat"><strong>12+</strong><span>Dealer &amp; AHASS</span></div><div class="daw-recruitment__stat"><strong>500+</strong><span>Karyawan</span></div></div>
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
        <div class="daw-recruitment__job-grid"><?php if ( ! $jobs ) : ?><div class="daw-recruitment__panel"><strong>Tidak ada lowongan yang sesuai.</strong><p>Belum ada posisi yang sesuai dengan filter yang dipilih.</p></div><?php endif; ?><?php foreach ( $jobs as $i => $job ) : require recruitment_get_plugin_path( 'public/components/job-card.php' ); endforeach; ?></div>
    </div></section>
    <?php require recruitment_get_plugin_path( 'public/components/footer.php' ); ?>
</div>
