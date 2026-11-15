<?php
$preview = recruitment_is_ui_preview();
$applicant = $preview ? null : recruitment_get_current_applicant();
$application_id = isset( $_GET['application_id'] ) && is_scalar( $_GET['application_id'] )
    ? sanitize_text_field( wp_unslash( (string) $_GET['application_id'] ) )
    : '';
$database = new Recruitment_Database();
$application = $applicant && ctype_digit( $application_id ) && '0' !== $application_id
    ? $database->get_applicant_application( (string) $applicant['id'], $application_id )
    : null;
$application_load_error = $database->has_error();
$timeline = $application && ! $application_load_error
    ? $database->get_applicant_application_timeline( (string) $applicant['id'], $application_id )
    : [];
if ( $database->has_error() ) {
    $application_load_error = true;
}
$user_interview_complete = recruitment_has_user_interview_result( $timeline );
$interview_type = [
    'INTERVIEW_HR' => 'HR',
    'INTERVIEW_USER' => 'USER',
][ strtoupper( trim( (string) ( $application['status'] ?? '' ) ) ) ] ?? '';
if ( $user_interview_complete ) {
    $interview_type = '';
}
$interview_title = 'HR' === $interview_type ? 'Wawancara HR' : 'Wawancara User';
$month_value = isset( $_GET['month'] ) && is_string( $_GET['month'] )
    ? sanitize_text_field( wp_unslash( $_GET['month'] ) )
    : current_time( 'Y-m' );
$month_start = DateTimeImmutable::createFromFormat( '!Y-m', $month_value, wp_timezone() );
$month_errors = DateTimeImmutable::getLastErrors();
if (
    ! $month_start instanceof DateTimeImmutable
    || $month_start->format( 'Y-m' ) !== $month_value
    || ( false !== $month_errors && ( 0 < $month_errors['warning_count'] || 0 < $month_errors['error_count'] ) )
) {
    $month_start = DateTimeImmutable::createFromFormat( '!Y-m', current_time( 'Y-m' ), wp_timezone() );
}
$month_end = $month_start->modify( 'first day of next month' );
$booking = null;
$available_slots = [];
$booking_load_error = false;
if ( $application && '' !== $interview_type ) {
    $booking = $database->get_applicant_interview_booking( (string) $applicant['id'], $application_id, $interview_type );
    $booking_load_error = $database->has_error();
    if ( ! $booking && ! $booking_load_error ) {
        $available_slots = $database->get_applicant_interview_slots(
            (string) $applicant['id'],
            $application_id,
            $interview_type,
            $month_start->format( 'Y-m-d' ),
            $month_end->format( 'Y-m-d' )
        );
        $booking_load_error = $database->has_error();
    }
}
$slots_by_date = [];
$has_open_slot = false;
foreach ( $available_slots as $slot ) {
    $slots_by_date[ (string) $slot['schedule_date'] ][] = $slot;
    $has_open_slot = $has_open_slot || 0 < (int) ( $slot['remaining_capacity'] ?? 0 );
}
$booking_result = isset( $_GET['booking_result'] ) && is_scalar( $_GET['booking_result'] )
    ? sanitize_key( wp_unslash( (string) $_GET['booking_result'] ) )
    : '';
$booking_messages = [
    'booked' => [ 'success', 'Jadwal berhasil dipesan.' ],
    'slot_full' => [ 'error', 'Maaf, slot tersebut baru saja penuh. Pilih slot lain yang masih tersedia.' ],
    'slot_unavailable' => [ 'error', 'Slot tidak tersedia lagi. Muat ulang jadwal dan pilih slot lain.' ],
    'already_booked' => [ 'error', 'Anda sudah memiliki jadwal untuk tahap ini. Hubungi tim rekrutmen jika perlu mengubahnya.' ],
    'invalid_application' => [ 'error', 'Lamaran tidak ditemukan pada akun ini. Kembali ke tracking dan pilih lamaran yang benar.' ],
    'stage_not_eligible' => [ 'error', 'Lamaran belum berada pada tahap wawancara ini atau tahapnya sudah berubah.' ],
    'invalid_request' => [ 'error', 'Permintaan tidak valid. Muat ulang halaman lalu coba kembali.' ],
    'service_unavailable' => [ 'error', 'Pemesanan belum dapat disimpan ke Oracle. Silakan coba kembali nanti.' ],
    'read_only' => [ 'error', 'Mode preview hanya untuk melihat halaman; pemesanan tidak disimpan.' ],
];
?>
<div class="daw-recruitment daw-recruitment--interview-booking">
    <?php require recruitment_get_plugin_path( 'public/components/header.php' ); ?>
    <main class="daw-recruitment__interview-main">
        <div class="daw-recruitment__interview-container">
            <a class="daw-interview-back" href="<?= esc_url( recruitment_get_public_url( 'application-process', [ 'application_id' => $application_id ] ) ) ?>">&larr; Kembali ke Tracking Lamaran</a>
            <header class="daw-interview-heading">
                <span class="daw-recruitment__eyebrow">TAHAP SELEKSI</span>
                <h1>Pilih Jadwal <?= esc_html( $interview_title ) ?></h1>
                <p>Pilih satu slot yang masih tersedia. Kapasitas diperbarui dan diperiksa kembali saat pemesanan.</p>
            </header>

            <?php if ( isset( $booking_messages[ $booking_result ] ) ) : ?>
                <p class="daw-interview-<?= esc_attr( $booking_messages[ $booking_result ][0] ) ?>" role="<?= 'success' === $booking_messages[ $booking_result ][0] ? 'status' : 'alert' ?>"><?= esc_html( $booking_messages[ $booking_result ][1] ) ?></p>
            <?php endif; ?>

            <?php if ( $preview ) : ?>
                <section class="daw-interview-state"><span class="daw-interview-state-icon" aria-hidden="true">i</span><h2>Mode preview</h2><p>Masuk ke akun pelamar untuk melihat jadwal yang tersedia dan melakukan pemesanan.</p></section>
            <?php elseif ( $application_load_error ) : ?>
                <section class="daw-interview-state"><span class="daw-interview-state-icon" aria-hidden="true">!</span><h2>Lamaran belum dapat dimuat</h2><p>Koneksi ke layanan data sedang bermasalah. Silakan coba kembali nanti.</p></section>
            <?php elseif ( ! $application ) : ?>
                <section class="daw-interview-state"><span class="daw-interview-state-icon" aria-hidden="true">!</span><h2>Lamaran tidak ditemukan</h2><p>Lamaran ini tidak tersedia pada akun Anda. Kembali ke tracking dan pilih lamaran yang benar.</p></section>
            <?php elseif ( $user_interview_complete ) : ?>
                <section class="daw-interview-state"><span class="daw-interview-state-icon" aria-hidden="true">i</span><h2>Wawancara User telah selesai</h2><p>Jadwal baru tidak dapat dipesan setelah hasil wawancara tercatat. Kembali ke tracking untuk melihat pengumuman dan status lamaran.</p></section>
            <?php elseif ( '' === $interview_type ) : ?>
                <section class="daw-interview-state"><span class="daw-interview-state-icon" aria-hidden="true">i</span><h2>Belum masuk tahap wawancara</h2><p>Pemesanan jadwal hanya tersedia setelah status lamaran Anda diperbarui ke Wawancara HR atau Wawancara User.</p></section>
            <?php else : ?>
                <section class="daw-interview-applicant">
                    <div><span>Posisi</span><strong><?= esc_html( (string) $application['title'] ) ?></strong></div>
                    <div><span>No. Lamaran</span><strong><?= esc_html( $application_id ) ?></strong></div>
                </section>

                <?php if ( $booking_load_error ) : ?>
                    <section class="daw-interview-state"><span class="daw-interview-state-icon" aria-hidden="true">!</span><h2>Jadwal belum dapat dimuat</h2><p>Koneksi database atau migrasi 018/019 belum tersedia. Hubungi tim rekrutmen; pemesanan tidak dapat dilakukan sampai layanan siap.</p></section>
                <?php elseif ( $booking ) : ?>
                    <section class="daw-interview-card daw-interview-confirmed">
                        <span class="daw-interview-success-icon" aria-hidden="true">&#10003;</span>
                        <h2>Jadwal <?= esc_html( $interview_title ) ?> Anda</h2>
                        <p><?= esc_html( wp_date( 'l, j F Y', strtotime( (string) $booking['schedule_date'] ) ?: time() ) ) ?> · <?= esc_html( (string) $booking['start_time'] . '–' . (string) $booking['end_time'] ) ?></p>
                        <dl class="daw-interview-detail">
                            <dt>Metode</dt><dd><?= 'ONLINE' === strtoupper( (string) $booking['method'] ) ? 'Online' : 'Tatap muka' ?></dd>
                            <?php if ( 'ONLINE' === strtoupper( (string) $booking['method'] ) ) : ?>
                                <dt>Platform</dt><dd><?= esc_html( (string) $booking['platform'] ) ?></dd>
                                <dt>Link wawancara</dt><dd><a href="<?= esc_url( (string) $booking['meeting_link'] ) ?>" target="_blank" rel="noopener noreferrer"><?= esc_html( (string) $booking['meeting_link'] ) ?></a></dd>
                            <?php else : ?>
                                <dt>Lokasi</dt><dd><?= esc_html( (string) $booking['location'] ) ?></dd>
                            <?php endif; ?>
                        </dl>
                        <a class="daw-recruitment__button" href="<?= esc_url( recruitment_get_public_url( 'application-process', [ 'application_id' => $application_id ] ) ) ?>">Kembali ke Tracking Lamaran</a>
                    </section>
                <?php else : ?>
                    <?php
                    $month_url = static function ( DateTimeImmutable $month ) use ( $application_id ): string {
                        return recruitment_get_public_url(
                            'interview-booking',
                            [
                                'application_id' => $application_id,
                                'month' => $month->format( 'Y-m' ),
                            ]
                        );
                    };
                    ?>
                    <section class="daw-interview-card">
                        <div class="daw-interview-month-nav">
                            <a href="<?= esc_url( $month_url( $month_start->modify( 'first day of previous month' ) ) ) ?>" aria-label="Bulan sebelumnya">&lsaquo;</a>
                            <h2><?= esc_html( wp_date( 'F Y', $month_start->getTimestamp() ) ) ?></h2>
                            <a href="<?= esc_url( $month_url( $month_start->modify( 'first day of next month' ) ) ) ?>" aria-label="Bulan berikutnya">&rsaquo;</a>
                        </div>
                        <?php if ( ! $available_slots ) : ?>
                            <p class="daw-interview-empty">Belum ada slot <?= esc_html( strtolower( $interview_title ) ) ?> yang tersedia pada bulan ini.</p>
                        <?php else : ?>
                            <?php if ( ! $has_open_slot ) : ?><p class="daw-interview-error" role="status">Semua slot pada bulan ini sudah penuh. Silakan pilih bulan lain.</p><?php endif; ?>
                            <form method="post" action="<?= esc_url( recruitment_get_public_url( 'interview-booking', [ 'application_id' => $application_id ] ) ) ?>">
                                <?php wp_nonce_field( 'daw_book_interview_' . $application_id, 'interview_booking_nonce' ); ?>
                                <input type="hidden" name="application_id" value="<?= esc_attr( $application_id ) ?>">
                                <?php foreach ( $slots_by_date as $date => $day_slots ) : ?>
                                    <fieldset class="daw-interview-day">
                                        <legend><?= esc_html( wp_date( 'l, j F Y', strtotime( $date ) ?: time() ) ) ?></legend>
                                        <div class="daw-interview-slot-list">
                                            <?php foreach ( $day_slots as $slot ) :
                                                $remaining = max( 0, (int) $slot['remaining_capacity'] );
                                                $is_full = 0 === $remaining;
                                                ?>
                                                <label class="daw-interview-slot <?= $is_full ? 'is-full' : '' ?>">
                                                    <input type="radio" name="schedule_id" value="<?= esc_attr( (string) $slot['id'] ) ?>" <?= disabled( $is_full, true, false ) ?> required>
                                                    <span class="daw-interview-slot__details">
                                                        <strong><?= esc_html( (string) $slot['start_time'] . '–' . (string) $slot['end_time'] ) ?></strong>
                                                        <small><?= 'ONLINE' === strtoupper( (string) $slot['method'] ) ? 'Online · ' . (string) $slot['platform'] : 'Tatap muka · ' . (string) $slot['location'] ?></small>
                                                    </span>
                                                    <span class="daw-interview-slot__capacity"><?= $is_full ? 'Penuh' : esc_html( $remaining . ' dari ' . (int) $slot['capacity'] . ' slot tersisa' ) ?></span>
                                                </label>
                                            <?php endforeach; ?>
                                        </div>
                                    </fieldset>
                                <?php endforeach; ?>
                                <?php if ( $has_open_slot ) : ?><button class="daw-recruitment__button daw-interview-submit" type="submit">Pesan Jadwal <?= esc_html( $interview_title ) ?></button><?php endif; ?>
                            </form>
                        <?php endif; ?>
                    </section>
                <?php endif; ?>
            <?php endif; ?>
        </div>
    </main>
    <?php require recruitment_get_plugin_path( 'public/components/footer.php' ); ?>
</div>
