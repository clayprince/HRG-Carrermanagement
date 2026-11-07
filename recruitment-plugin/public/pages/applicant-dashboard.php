<?php
$preview = recruitment_is_ui_preview();
$applicant = $preview ? [ 'full_name' => 'Preview Pelamar', 'email' => 'preview@example.invalid' ] : recruitment_get_current_applicant();
$database = new Recruitment_Database();
$applications = $preview ? [] : $database->get_applicant_applications( (string) $applicant['id'] );
$applications_error = ! $preview && $database->has_error();
$counts = [ 'total' => count( $applications ), 'processing' => 0, 'accepted' => 0, 'rejected' => 0 ];
foreach ( $applications as $application ) {
    $status = strtoupper( trim( (string) ( $application['final_status'] ?: $application['status'] ) ) );
    if ( false !== strpos( $status, 'DITERIMA' ) || false !== strpos( $status, 'ACCEPT' ) ) {
        ++$counts['accepted'];
    } elseif ( false !== strpos( $status, 'TIDAK LOLOS' ) || false !== strpos( $status, 'DITOLAK' ) || false !== strpos( $status, 'REJECT' ) ) {
        ++$counts['rejected'];
    } else {
        ++$counts['processing'];
    }
}
?>
<div class="daw-recruitment daw-account">
    <?php require recruitment_get_plugin_path( 'public/components/header.php' ); ?>
    <main class="daw-account__main">
        <header class="daw-account__heading"><div><span class="daw-recruitment__eyebrow">DASHBOARD PELAMAR</span><h1>Halo, <?= esc_html( $applicant['full_name'] ) ?></h1><p><?= esc_html( $applicant['email'] ) ?></p></div></header>
        <?php require recruitment_get_plugin_path( 'public/components/applicant-account-nav.php' ); ?>
        <?php if ( $preview ) : ?><div class="daw-account__preview-note">Mode preview: data akun dan lamaran tidak ditampilkan.</div><?php elseif ( $applications_error ) : ?><section class="daw-account__empty"><h2>Ringkasan lamaran belum dapat dimuat</h2><p>Koneksi ke layanan data sedang bermasalah. Silakan coba kembali nanti.</p></section><?php else : ?>
            <section class="daw-account__stats" aria-label="Ringkasan lamaran">
                <div><span>Total lamaran</span><strong><?= esc_html( number_format_i18n( $counts['total'] ) ) ?></strong></div>
                <div><span>Dalam proses</span><strong><?= esc_html( number_format_i18n( $counts['processing'] ) ) ?></strong></div>
                <div><span>Diterima</span><strong><?= esc_html( number_format_i18n( $counts['accepted'] ) ) ?></strong></div>
                <div><span>Tidak lolos/ditolak</span><strong><?= esc_html( number_format_i18n( $counts['rejected'] ) ) ?></strong></div>
            </section>
        <?php endif; ?>
        <a class="daw-recruitment__button" href="<?= esc_url( recruitment_get_public_url( 'careers' ) ) ?>">Lihat Lowongan</a>
    </main>
    <?php require recruitment_get_plugin_path( 'public/components/footer.php' ); ?>
</div>
