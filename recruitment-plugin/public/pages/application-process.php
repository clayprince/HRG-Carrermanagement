<?php
$preview = recruitment_is_ui_preview();
$tracking_token = isset( $_GET['tracking_token'] ) && is_scalar( $_GET['tracking_token'] )
    ? sanitize_text_field( wp_unslash( (string) $_GET['tracking_token'] ) )
    : '';
$tracking_data = null;
$tracking_error = '';

if ( $preview ) {
    $tracking_data = [
        'candidate_name' => 'Aiko Rahayu',
        'position' => 'Sales Executive',
        'dealer' => 'DAW Airmadidi',
        'location' => 'Minahasa Utara',
        'applied_at' => '5 Agustus 2026',
        'current_status' => 'Sedang Berlangsung',
        'final_status' => '',
        'decision' => '',
        'status_history' => [
            [ 'stage' => 'Lamaran Dikirim', 'status' => 'Selesai', 'status_date' => '5 Agustus 2026' ],
            [ 'stage' => 'Seleksi Administrasi', 'status' => 'Lolos', 'status_date' => '10 Agustus 2026' ],
            [ 'stage' => 'Tes Psikologi', 'status' => 'Sedang Berlangsung', 'status_date' => '15 Agustus 2026' ],
        ],
    ];
} elseif ( 1 !== preg_match( '/\A[a-f0-9]{64}\z/', $tracking_token ) ) {
    $tracking_error = 'Gunakan tautan tracking rahasia yang dikirim setelah lamaran Anda diterima.';
} else {
    $database = new Recruitment_Database();
    $tracking_data = $database->get_application_tracking_by_token( $tracking_token );
    if ( ! is_array( $tracking_data ) ) {
        $tracking_error = $database->has_error()
            ? 'Data tracking belum dapat dimuat. Silakan coba kembali nanti.'
            : 'Tautan tracking tidak valid atau lamaran tidak ditemukan.';
    }
}

$status_history = is_array( $tracking_data['status_history'] ?? null ) ? $tracking_data['status_history'] : [];
$decision = trim( (string) ( $tracking_data['decision'] ?? '' ) );
$current_status = '' !== $decision
    ? $decision
    : trim( (string) ( $tracking_data['current_status'] ?? '' ) );
$status_label = static function ( string $value ): string {
    $labels = [
        'SUBMITTED' => 'Lamaran diterima',
        'DITERIMA' => 'Diterima',
        'TIDAK DITERIMA' => 'Tidak diterima',
    ];

    return $labels[ strtoupper( trim( $value ) ) ] ?? ucwords( strtolower( str_replace( '_', ' ', trim( $value ) ) ) );
};
?>
<div class="daw-recruitment daw-account daw-recruitment--tracking">
    <?php require recruitment_get_plugin_path( 'public/components/header.php' ); ?>
    <main class="daw-account__main daw-account__main--tracking">
        <header class="daw-account__heading"><div><span class="daw-recruitment__eyebrow">TRACKING LAMARAN</span><h1>Tracking Lamaran</h1><p>Pantau tahapan seleksi berdasarkan tautan rahasia Anda.</p></div></header>
        <?php if ( '' !== $tracking_error ) : ?>
            <section class="daw-account__empty"><h2>Status lamaran belum dapat ditampilkan</h2><p><?= esc_html( $tracking_error ) ?></p><a class="daw-recruitment__button" href="<?= esc_url( recruitment_get_public_url( 'careers' ) ) ?>">Lihat Lowongan</a></section>
        <?php elseif ( is_array( $tracking_data ) ) : ?>
            <section class="daw-account__card daw-account__process-summary"><div class="daw-account__process-identity"><span class="daw-account__tracking-label">Status lamaran</span><span class="daw-account__status <?= '' !== $decision ? 'is-closed' : 'is-active' ?>"><?= esc_html( $status_label( $current_status ) ) ?></span><h2><?= esc_html( (string) ( $tracking_data['candidate_name'] ?? '' ) ) ?></h2><strong><?= esc_html( (string) ( $tracking_data['position'] ?? '' ) ) ?></strong><small><?= esc_html( trim( (string) ( $tracking_data['dealer'] ?? '' ) . ' · ' . (string) ( $tracking_data['location'] ?? '' ), ' ·' ) ) ?></small></div><div class="daw-account__fields"><span>Tanggal melamar<strong><?= esc_html( (string) ( $tracking_data['applied_at'] ?? '' ) ) ?></strong></span><span>Keputusan akhir<strong><?= esc_html( '' !== $decision ? $status_label( $decision ) : 'Menunggu' ) ?></strong></span></div></section>
            <section class="daw-account__card"><h2>Timeline Seleksi</h2><p class="daw-account__muted">Informasi internal, nilai wawancara, dan catatan penilai tidak ditampilkan pada halaman publik.</p>
                <?php if ( $status_history ) : ?>
                    <div class="daw-account__timeline"><?php foreach ( $status_history as $index => $stage ) : ?><article class="<?= $index === array_key_last( $status_history ) ? 'is-current' : 'is-done' ?>"><span class="daw-account__timeline-marker"><?= $index === array_key_last( $status_history ) ? '○' : '&#10003;' ?></span><div><strong><?= esc_html( $status_label( (string) ( $stage['stage'] ?? '' ) ) ) ?></strong><small><?= esc_html( (string) ( $stage['status_date'] ?? '' ) ) ?></small></div><b><?= esc_html( $status_label( (string) ( $stage['status'] ?? '' ) ) ) ?></b></article><?php endforeach; ?></div>
                <?php else : ?>
                    <p class="daw-account__muted">Belum ada pembaruan tahapan seleksi.</p>
                <?php endif; ?>
            </section>
        <?php endif; ?>
        <div class="daw-account__tracking-footer"><span>Jangan bagikan tautan tracking rahasia ini kepada orang lain.</span><a href="<?= esc_url( recruitment_get_public_url( 'careers' ) ) ?>">Lihat lowongan lain &rarr;</a></div>
    </main>
    <?php require recruitment_get_plugin_path( 'public/components/footer.php' ); ?>
</div>
