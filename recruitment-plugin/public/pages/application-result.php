<?php
nocache_headers();
$applicant = recruitment_get_current_applicant();
$application_id = isset( $_GET['application_id'] ) && is_scalar( $_GET['application_id'] )
    ? sanitize_text_field( wp_unslash( (string) $_GET['application_id'] ) )
    : '';
$database = new Recruitment_Database();
$application = $applicant && ctype_digit( $application_id ) && '0' !== $application_id
    ? $database->get_applicant_application( (string) $applicant['id'], $application_id )
    : null;
$timeline = $application && ! $database->has_error()
    ? $database->get_applicant_application_timeline( (string) $applicant['id'], (string) $application['id'] )
    : [];
$database_error = $database->has_error();
$timeline_latest = $timeline ? $timeline[ count( $timeline ) - 1 ] : [];
$timeline_previous = 1 < count( $timeline ) ? $timeline[ count( $timeline ) - 2 ] : null;
$stage_announcement = $timeline_latest ? recruitment_get_application_stage_announcement( $timeline_latest, $timeline_previous ) : null;
$result_event_key = $timeline_latest && is_array( $stage_announcement )
    ? recruitment_application_result_event_key( $timeline_latest )
    : '';
$requested_result_event_key = isset( $_GET['result_event'] ) && is_scalar( $_GET['result_event'] )
    ? strtolower( sanitize_text_field( wp_unslash( (string) $_GET['result_event'] ) ) )
    : '';
$show_result_requested = isset( $_GET['show_result'] )
    && is_scalar( $_GET['show_result'] )
    && '1' === sanitize_text_field( wp_unslash( (string) $_GET['show_result'] ) )
    && '' !== $result_event_key
    && hash_equals( $result_event_key, $requested_result_event_key );
$result_already_viewed = $application && $applicant && '' !== $result_event_key
    && recruitment_has_viewed_application_result( (string) $applicant['id'], (string) $application['id'], $result_event_key );
$is_final = is_array( $stage_announcement ) && ! empty( $stage_announcement['is_final'] );
$is_accepted = $is_final && 'passed' === $stage_announcement['outcome'];
$tracking_url = recruitment_get_public_url(
    'application-process',
    $application
        ? ( ! empty( $application['token'] )
            ? [ 'token' => $application['token'] ]
            : [ 'application_id' => $application['id'] ] )
        : []
);
$applied_at = $application ? strtotime( (string) ( $application['created_at'] ?? '' ) ) : false;

$has_current_announcement = ! $database_error && $application && ( $is_final || is_array( $stage_announcement ) );
if ( $has_current_announcement && ( ! $show_result_requested || $result_already_viewed ) ) {
    wp_safe_redirect( $tracking_url );
    exit;
}
$is_announcement = $has_current_announcement && $show_result_requested;
if ( $is_announcement && ! recruitment_mark_application_result_viewed( (string) $applicant['id'], (string) $application['id'], $result_event_key ) ) {
    if ( recruitment_has_viewed_application_result( (string) $applicant['id'], (string) $application['id'], $result_event_key ) ) {
        wp_safe_redirect( $tracking_url );
        exit;
    }

    wp_die( esc_html__( 'Hasil seleksi belum dapat ditampilkan. Silakan coba kembali nanti.', 'recruitment-plugin' ), '', [ 'response' => 503 ] );
}
$announcement_eyebrow = '';
$announcement_passed = false;
$announcement_badge = '';
$announcement_title = '';
$announcement_desc = '';
$announcement_enc = '';
$next_action_type = '';

if ( $is_announcement ) {
    if ( $is_final ) {
        $announcement_eyebrow = 'KEPUTUSAN AKHIR';
        $announcement_passed = $is_accepted;
        $announcement_badge = $is_accepted ? 'DITERIMA' : 'TIDAK DITERIMA';
        $announcement_title = $is_accepted
            ? 'Selamat, ' . esc_html( (string) $applicant['full_name'] ) . '!'
            : 'Terima kasih telah mengikuti proses seleksi.';
        $announcement_desc = $is_accepted
            ? 'Anda dinyatakan diterima untuk posisi ' . esc_html( (string) $application['title'] ) . '.'
            : 'Untuk saat ini, Anda belum dapat melanjutkan pada lowongan ' . esc_html( (string) $application['title'] ) . '.';
        $announcement_enc = $is_accepted
            ? 'Kami menantikan kontribusi Anda dan berharap perjalanan baru ini menjadi awal yang baik.'
            : 'Jangan berkecil hati. Terus kembangkan potensi Anda dan tetap semangat menantikan kesempatan berikutnya.';
    } else {
        $is_stage_passed = 'passed' === $stage_announcement['outcome'];
        $stage_name = (string) ( $stage_announcement['stage_label'] ?? 'Seleksi' );
        $announcement_eyebrow = 'PENGUMUMAN TAHAP SELEKSI';
        $announcement_passed = $is_stage_passed;
        $announcement_badge = $is_stage_passed ? 'LOLOS TAHAP ' . strtoupper( $stage_name ) : 'TIDAK LOLOS';
        $announcement_title = $is_stage_passed
            ? 'Selamat, ' . esc_html( (string) $applicant['full_name'] ) . '!'
            : 'Mohon Maaf, ' . esc_html( (string) $applicant['full_name'] );
        $announcement_desc = $is_stage_passed
            ? 'Anda dinyatakan lolos tahap <strong>' . esc_html( $stage_name ) . '</strong> untuk posisi <strong>' . esc_html( (string) $application['title'] ) . '</strong>.'
            : 'Untuk saat ini, Anda belum dapat melanjutkan pada tahap seleksi <strong>' . esc_html( $stage_name ) . '</strong> lowongan <strong>' . esc_html( (string) $application['title'] ) . '</strong>.';
        $announcement_enc = $is_stage_passed
            ? 'Hasil seleksi ini membuka kesempatan Anda untuk melangkah ke tahap berikutnya. Persiapkan diri Anda sebaik mungkin.'
            : 'Jangan berkecil hati. Terus kembangkan potensi Anda dan tetap semangat dalam proses pencarian kesempatan berikutnya.';
        $next_action_type = $is_stage_passed ? (string) ( $stage_announcement['next_page'] ?? '' ) : '';
    }
}
?>
<div class="daw-recruitment daw-account daw-recruitment--tracking">
    <?php require recruitment_get_plugin_path( 'public/components/header.php' ); ?>
    <main class="daw-account__main daw-account__main--tracking daw-application-result">
        <header class="daw-account__heading daw-application-result__heading">
            <div>
                <span class="daw-recruitment__eyebrow">HASIL SELEKSI</span>
                <h1>Pengumuman Hasil Seleksi</h1>
                <p><?= $is_final ? 'Informasi keputusan akhir dan ringkasan lamaran Anda.' : 'Informasi status hasil seleksi dan langkah selanjutnya.' ?></p>
            </div>
        </header>

        <?php if ( $database_error ) : ?>
            <section class="daw-application-result__message" role="alert">
                <span aria-hidden="true">!</span>
                <h2>Hasil belum dapat dimuat</h2>
                <p>Layanan data sedang bermasalah. Silakan coba kembali nanti.</p>
            </section>
        <?php elseif ( ! $application ) : ?>
            <section class="daw-application-result__message" role="status">
                <span aria-hidden="true">?</span>
                <h2>Lamaran tidak ditemukan</h2>
                <p>Lamaran ini tidak tersedia untuk akun Anda.</p>
            </section>
        <?php elseif ( ! $is_announcement ) : ?>
            <section class="daw-application-result__message" role="status">
                <span aria-hidden="true">…</span>
                <h2>Pengumuman belum tersedia</h2>
                <p>Pengumuman akan muncul setelah tim rekrutmen memperbarui tahapan seleksi Anda.</p>
            </section>
        <?php else : ?>
            <section class="daw-application-result__hero <?= $announcement_passed ? 'is-accepted' : 'is-rejected' ?>" role="status" aria-live="polite">
                <span class="daw-application-result__icon" aria-hidden="true"><?= $announcement_passed ? '&#10003;' : '&#8212;' ?></span>
                <div class="daw-application-result__hero-copy">
                    <span class="daw-application-result__eyebrow"><?= esc_html( $announcement_eyebrow ) ?></span>
                    <h2><?= $announcement_title ?></h2>
                    <p><?= $announcement_desc ?></p>
                    <p class="daw-application-result__encouragement"><?= esc_html( $announcement_enc ) ?></p>
                    <?php if ( 'psychotest-preparation' === $next_action_type ) : ?>
                        <div style="margin-top: 18px;">
                            <a class="daw-recruitment__button" href="<?= esc_url( recruitment_get_public_url( 'application-process', [ 'application_id' => (string) $application['id'], 'show_psychotest_info' => '1' ] ) ) ?>">Lihat Informasi Psikotes</a>
                        </div>
                    <?php elseif ( 'interview-booking' === $next_action_type ) : ?>
                        <div style="margin-top: 18px;">
                            <a class="daw-recruitment__button" href="<?= esc_url( recruitment_get_public_url( 'interview-booking', [ 'application_id' => (string) $application['id'] ] ) ) ?>"><?= esc_html( $stage_announcement['next_label'] ?: 'Pilih Jadwal Wawancara' ) ?></a>
                        </div>
                    <?php endif; ?>
                </div>
                <strong class="daw-application-result__badge"><?= esc_html( $announcement_badge ) ?></strong>
            </section>

            <div class="daw-application-result__grid">
                <section class="daw-application-result__card" aria-labelledby="daw-application-result-details">
                    <div class="daw-application-result__card-heading">
                        <span class="daw-application-result__card-icon" aria-hidden="true">&#9776;</span>
                        <div><h2 id="daw-application-result-details">Detail Lamaran</h2><p>Informasi lowongan yang Anda ikuti.</p></div>
                    </div>
                    <dl class="daw-application-result__details">
                        <div><dt>Posisi</dt><dd><?= esc_html( (string) $application['title'] ) ?></dd></div>
                        <div><dt>Departemen</dt><dd><?= esc_html( (string) $application['department'] ?: '—' ) ?></dd></div>
                        <div><dt>Lokasi</dt><dd><?= esc_html( (string) $application['location'] ?: '—' ) ?></dd></div>
                        <div><dt>Tanggal melamar</dt><dd><?= esc_html( $applied_at ? wp_date( 'j F Y', $applied_at ) : '—' ) ?></dd></div>
                        <div><dt>Nomor lamaran</dt><dd><?= esc_html( (string) $application['id'] ) ?></dd></div>
                        <div><dt>Pelamar</dt><dd><?= esc_html( (string) $applicant['full_name'] ) ?></dd></div>
                    </dl>
                </section>

                <aside class="daw-application-result__tracking">
                    <span class="daw-application-result__tracking-icon" aria-hidden="true">&#8634;</span>
                    <h2>Riwayat lamaran tetap tersedia</h2>
                    <p>Gunakan nomor lamaran atau token ini kapan saja untuk membuka kembali tracking dan riwayat seleksi akun Anda.</p>
                    <span class="daw-application-result__token-label">TOKEN TRACKING</span>
                    <code><?= esc_html( (string) ( $application['token'] ?? '' ) ?: 'Token belum tersedia' ) ?></code>
                    <a class="daw-application-result__tracking-link" href="<?= esc_url( $tracking_url ) ?>">Kembali ke Tracking <span aria-hidden="true">&rarr;</span></a>
                </aside>
            </div>
        <?php endif; ?>

        <?php if ( ! $application || ! $is_announcement || $database_error ) : ?>
            <a class="daw-application-result__back-link" href="<?= esc_url( $tracking_url ) ?>">&larr; Kembali ke Tracking Lamaran</a>
        <?php endif; ?>
    </main>
    <?php require recruitment_get_plugin_path( 'public/components/footer.php' ); ?>
</div>
