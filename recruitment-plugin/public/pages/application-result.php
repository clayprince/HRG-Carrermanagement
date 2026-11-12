<?php
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
$is_final = is_array( $stage_announcement ) && ! empty( $stage_announcement['is_final'] );
$is_accepted = $is_final && 'passed' === $stage_announcement['outcome'];
$tracking_url = recruitment_get_public_url(
    'application-process',
    $application
        ? ( ! empty( $application['token'] )
            ? [ 'token' => $application['token'], 'result_viewed' => '1' ]
            : [ 'application_id' => $application['id'], 'result_viewed' => '1' ] )
        : []
);
$applied_at = $application ? strtotime( (string) ( $application['created_at'] ?? '' ) ) : false;
?>
<div class="daw-recruitment daw-account daw-recruitment--tracking">
    <?php require recruitment_get_plugin_path( 'public/components/header.php' ); ?>
    <main class="daw-account__main daw-account__main--tracking daw-application-result">
        <header class="daw-account__heading daw-application-result__heading">
            <div>
                <span class="daw-recruitment__eyebrow">HASIL SELEKSI</span>
                <h1>Pengumuman Hasil Lowongan</h1>
                <p><?= $is_final ? 'Informasi keputusan akhir dan ringkasan lamaran Anda.' : 'Informasi hasil seleksi dan ringkasan lamaran Anda.' ?></p>
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
        <?php elseif ( ! $is_final && is_array( $stage_announcement ) ) : ?>
            <section class="daw-application-result__message" role="status">
                <span aria-hidden="true"><?= 'passed' === $stage_announcement['outcome'] ? '&#10003;' : '&#8212;' ?></span>
                <h2><?= 'passed' === $stage_announcement['outcome']
                    ? 'Selamat, Anda lolos tahap ' . esc_html( $stage_announcement['stage_label'] )
                    : 'Mohon maaf, Anda belum lolos tahap ' . esc_html( $stage_announcement['stage_label'] ) ?></h2>
                <p><?= 'passed' === $stage_announcement['outcome']
                    ? 'Pengumuman tahap seleksi Anda tersedia pada halaman tracking lamaran. Silakan kembali ke tracking untuk melanjutkan.'
                    : 'Lihat riwayat tracking untuk mengetahui informasi tahapan seleksi Anda.' ?></p>
            </section>
        <?php elseif ( ! $is_final ) : ?>
            <section class="daw-application-result__message" role="status">
                <span aria-hidden="true">…</span>
                <h2>Keputusan belum tersedia</h2>
                <p>Pengumuman akan muncul setelah HR menetapkan keputusan akhir.</p>
            </section>
        <?php else : ?>
            <section class="daw-application-result__hero <?= $is_accepted ? 'is-accepted' : 'is-rejected' ?>" role="status" aria-live="polite">
                <span class="daw-application-result__icon" aria-hidden="true"><?= $is_accepted ? '&#10003;' : '&#8212;' ?></span>
                <div class="daw-application-result__hero-copy">
                    <span class="daw-application-result__eyebrow">KEPUTUSAN AKHIR</span>
                    <h2><?= $is_accepted ? 'Selamat, ' . esc_html( (string) $applicant['full_name'] ) . '!' : 'Terima kasih telah mengikuti proses seleksi.' ?></h2>
                    <p><?= $is_accepted
                        ? 'Anda dinyatakan diterima untuk posisi ' . esc_html( (string) $application['title'] ) . '.'
                        : 'Untuk saat ini, Anda belum dapat melanjutkan pada lowongan ' . esc_html( (string) $application['title'] ) . '.' ?></p>
                    <p class="daw-application-result__encouragement"><?= $is_accepted
                        ? 'Kami menantikan kontribusi Anda dan berharap perjalanan baru ini menjadi awal yang baik.'
                        : 'Jangan berkecil hati. Terus kembangkan potensi Anda dan tetap semangat menantikan kesempatan berikutnya.' ?></p>
                </div>
                <strong class="daw-application-result__badge"><?= $is_accepted ? 'DITERIMA' : 'TIDAK DITERIMA' ?></strong>
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
                    <p>Gunakan token ini kapan saja untuk membuka kembali tracking dan riwayat seleksi. Setelah keputusan final, lamaran hanya dapat dilihat dan tidak dapat diubah.</p>
                    <span class="daw-application-result__token-label">TOKEN TRACKING</span>
                    <code><?= esc_html( (string) ( $application['token'] ?? '' ) ?: 'Token belum tersedia' ) ?></code>
                    <a class="daw-application-result__tracking-link" href="<?= esc_url( $tracking_url ) ?>">Lihat Riwayat Tracking <span aria-hidden="true">&rarr;</span></a>
                </aside>
            </div>

        <?php endif; ?>

        <?php if ( ! $application || ! $is_final || $database_error ) : ?>
            <a class="daw-application-result__back-link" href="<?= esc_url( $tracking_url ) ?>">&larr; Kembali ke Tracking Lamaran</a>
        <?php endif; ?>
    </main>
    <?php require recruitment_get_plugin_path( 'public/components/footer.php' ); ?>
</div>
