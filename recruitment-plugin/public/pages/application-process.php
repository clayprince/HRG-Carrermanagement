<?php
$preview = recruitment_is_ui_preview();
$applicant = $preview ? null : recruitment_get_current_applicant();
$database = new Recruitment_Database();
$applications = $preview || ! $applicant ? [] : $database->get_applicant_applications( (string) $applicant['id'] );
$application_id = isset( $_GET['application_id'] ) && is_scalar( $_GET['application_id'] )
    ? sanitize_text_field( wp_unslash( (string) $_GET['application_id'] ) )
    : '';
$application_token = isset( $_GET['token'] ) && is_scalar( $_GET['token'] )
    ? strtoupper( sanitize_text_field( wp_unslash( (string) $_GET['token'] ) ) )
    : '';
$selected_application = '' !== $application_token && $applicant
    ? $database->get_applicant_application_by_token( (string) $applicant['id'], $application_token )
    : null;
if ( '' === $application_token ) {
    foreach ( $applications as $item ) {
        if ( $application_id === (string) $item['id'] ) {
            $selected_application = $item;
            break;
        }
    }
}
if ( '' === $application_id && '' === $application_token && ! $selected_application && $applications ) {
    $selected_application = $applications[0];
}
$invalid_application_selection = ( '' !== $application_id || '' !== $application_token ) && ! $selected_application;
$selected_application_id = (string) ( $selected_application['id'] ?? '' );
$timeline = $selected_application
    ? $database->get_applicant_application_timeline( (string) $applicant['id'], (string) $selected_application['id'] )
    : [];
$database_error = ! $preview && $database->has_error();
?>
<div class="daw-recruitment daw-account daw-recruitment--tracking">
    <?php require recruitment_get_plugin_path( 'public/components/header.php' ); ?>
    <main class="daw-account__main daw-account__main--tracking">
        <header class="daw-account__heading"><div><span class="daw-recruitment__eyebrow">AKUN PELAMAR</span><h1>Tracking Lamaran</h1><p>Pantau riwayat status seleksi milik akun Anda.</p></div>
            <?php if ( $applications && $selected_application ) : ?><form class="daw-account__select-label" method="get" action="<?= esc_url( recruitment_get_public_url( 'application-process' ) ) ?>"><input type="hidden" name="recruitment_page" value="application-process">Pilih lamaran<select name="application_id" onchange="this.form.submit()"><?php foreach ( $applications as $item ) : ?><option value="<?= esc_attr( $item['id'] ) ?>" <?= selected( $selected_application_id, (string) $item['id'], false ) ?>><?= esc_html( $item['title'] . ' · ' . $item['id'] ) ?></option><?php endforeach; ?></select></form><?php endif; ?>
        </header>
        <?php if ( ! $preview && ! $database_error ) : ?><form class="daw-account__token-search" method="get" action="<?= esc_url( recruitment_get_public_url( 'application-process' ) ) ?>"><input type="hidden" name="recruitment_page" value="application-process"><label for="daw-application-token">Lacak dengan Token Lamaran<input id="daw-application-token" name="token" type="text" value="<?= esc_attr( $application_token ) ?>" placeholder="Contoh: DAW-2026-..." autocomplete="off" required></label><button class="daw-recruitment__button" type="submit">Cari Token</button></form><?php endif; ?>
        <?php if ( $preview ) : ?>
            <section class="daw-account__empty"><h2>Tracking lamaran</h2><p>Data tracking akun tidak ditampilkan dalam mode preview.</p></section>
        <?php elseif ( $database_error ) : ?>
            <section class="daw-account__empty"><h2>Tracking belum dapat dimuat</h2><p>Koneksi ke layanan data sedang bermasalah. Silakan coba kembali nanti.</p></section>
        <?php elseif ( $invalid_application_selection ) : ?>
            <section class="daw-account__empty"><h2>Lamaran tidak ditemukan</h2><p>Token tidak valid untuk akun ini atau lamaran tidak ditemukan.</p><a href="<?= esc_url( recruitment_get_public_url( 'application-process' ) ) ?>">Coba lagi</a></section>
        <?php elseif ( ! $selected_application ) : ?>
            <section class="daw-account__empty"><h2>Belum ada lamaran untuk dilacak</h2><p>Tracking akan muncul setelah Anda mengirim lamaran menggunakan akun ini.</p><a class="daw-recruitment__button" href="<?= esc_url( recruitment_get_public_url( 'careers' ) ) ?>">Lihat Lowongan</a></section>
        <?php else :
            $status = trim( (string) ( $selected_application['final_status'] ?: $selected_application['status'] ) );
            $timestamp = strtotime( (string) ( $selected_application['created_at'] ?? '' ) );
            ?>
            <section class="daw-account__card daw-account__process-summary"><div class="daw-account__process-identity"><span class="daw-account__tracking-label">Status lamaran</span><span class="daw-account__status"><?= esc_html( $status ?: 'Menunggu pembaruan' ) ?></span><h2><?= esc_html( $selected_application['title'] ) ?></h2><small><?= esc_html( trim( (string) $selected_application['department'] . ' · ' . (string) $selected_application['location'], ' ·' ) ) ?></small></div><div class="daw-account__fields"><span>No. Lamaran<strong><?= esc_html( $selected_application['id'] ) ?></strong></span><span>Token Tracking<strong><?= esc_html( (string) ( $selected_application['token'] ?? '' ) ?: 'Belum tersedia' ) ?></strong></span><span>Tanggal melamar<strong><?= esc_html( $timestamp ? wp_date( 'j F Y', $timestamp ) : '—' ) ?></strong></span><span>Tahap saat ini<strong><?= esc_html( $timeline ? $timeline[ count( $timeline ) - 1 ]['stage'] : 'Belum ada riwayat tahap' ) ?></strong></span></div></section>
            <section class="daw-account__card"><h2>Riwayat Status Seleksi</h2><p class="daw-account__muted">Menampilkan pembaruan yang tercatat oleh tim rekrutmen.</p>
                <?php if ( $timeline ) : ?><div class="daw-account__timeline"><?php foreach ( $timeline as $index => $event ) :
                    $event_time = strtotime( (string) ( $event['changed_at'] ?? '' ) );
                    $state = $index === count( $timeline ) - 1 ? 'current' : 'done';
                    ?><article class="is-<?= esc_attr( $state ) ?>"><span class="daw-account__timeline-marker"><?= 'done' === $state ? '&#10003;' : '●' ?></span><div><strong><?= esc_html( $event['stage'] ?: 'Tahap seleksi' ) ?></strong><small><?= esc_html( $event_time ? wp_date( 'j F Y H:i', $event_time ) : '—' ) ?></small><?php if ( '' !== trim( (string) $event['note'] ) ) : ?><p><?= esc_html( $event['note'] ) ?></p><?php endif; ?></div><b><?= esc_html( $event['status'] ?: 'Diperbarui' ) ?></b></article><?php endforeach; ?></div><?php else : ?><p class="daw-account__muted">Belum ada pembaruan status untuk lamaran ini.</p><?php endif; ?>
            </section>
            <div class="daw-account__tracking-footer"><span>Status diperbarui saat tim rekrutmen mencatat tahap seleksi.</span></div>
        <?php endif; ?>
    </main>
    <?php require recruitment_get_plugin_path( 'public/components/footer.php' ); ?>
</div>
