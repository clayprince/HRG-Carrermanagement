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
$timeline_latest = $timeline ? $timeline[ count( $timeline ) - 1 ] : [];
$psychotest_stage_source = (string) ( $timeline_latest['stage'] ?? $selected_application['latest_stage'] ?? $selected_application['status'] ?? '' );
$psychotest_stage_key = strtolower( trim( preg_replace( '/[^a-z0-9]+/i', '_', $psychotest_stage_source ), '_' ) );
$application_final_status = strtoupper( trim( (string) ( $selected_application['final_status'] ?? '' ) ) );
$is_psychotest_stage = (
    false !== strpos( $psychotest_stage_key, 'psikotes' )
    || false !== strpos( $psychotest_stage_key, 'psycholog' )
    || 'tes_psikologi' === $psychotest_stage_key
) && ! in_array( $application_final_status, [ 'DITERIMA', 'DITOLAK', 'ACCEPTED', 'REJECTED' ], true );
$psychotest_defaults = [
    'camera_required' => true,
    'status' => 'active',
    'mode' => 'offline',
    'test_date' => '',
    'closing_date' => '',
    'closing_time' => '',
    'confirmation_deadline' => '',
    'start_time' => '',
    'end_time' => '',
    'location' => '',
    'address' => '',
    'contact_person' => '',
    'dress_code' => '',
    'max_participants' => 0,
    'attendance_confirmation' => false,
    'online_tests' => [ 'iq', 'pauli', 'personality' ],
    'documents' => [],
    'instructions' => '',
];
$psychotest_settings = wp_parse_args( (array) get_option( 'recruitment_psychotest_settings', [] ), $psychotest_defaults );
$psychotest_settings = recruitment_sync_psychotest_closing( $psychotest_settings );
$psychotest_mode = in_array( $psychotest_settings['mode'], [ 'online', 'offline' ], true ) ? $psychotest_settings['mode'] : 'offline';
$psychotest_date = 'Belum diatur';
if ( is_string( $psychotest_settings['test_date'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $psychotest_settings['test_date'] ) ) {
    $parsed_psychotest_date = DateTimeImmutable::createFromFormat( '!Y-m-d', $psychotest_settings['test_date'], wp_timezone() );
    if ( $parsed_psychotest_date instanceof DateTimeImmutable && $parsed_psychotest_date->format( 'Y-m-d' ) === $psychotest_settings['test_date'] ) {
        $psychotest_date = wp_date( 'j F Y', $parsed_psychotest_date->getTimestamp() );
    }
}
$psychotest_start_time = is_string( $psychotest_settings['start_time'] ) && preg_match( '/^(?:[01]\d|2[0-3]):[0-5]\d$/', $psychotest_settings['start_time'] )
    ? $psychotest_settings['start_time']
    : 'Belum diatur';
$psychotest_end_time = is_string( $psychotest_settings['end_time'] ) && preg_match( '/^(?:[01]\d|2[0-3]):[0-5]\d$/', $psychotest_settings['end_time'] )
    ? $psychotest_settings['end_time']
    : 'Belum diatur';
$psychotest_documents = [
    'ktp' => 'KTP',
    'writing_tools' => 'Alat tulis',
    'application_letter' => 'Surat lamaran',
    'other' => 'Dokumen lainnya',
];
$psychotest_document_keys = [];
if ( is_array( $psychotest_settings['documents'] ) ) {
    foreach ( $psychotest_settings['documents'] as $document_key ) {
        if ( is_string( $document_key ) ) {
            $psychotest_document_keys[] = sanitize_key( $document_key );
        }
    }
}
$psychotest_required_documents = array_values( array_intersect_key( $psychotest_documents, array_flip( $psychotest_document_keys ) ) );
$psychotest_test_defaults = [
    'iq' => [ 'name' => 'IQ', 'duration' => 20, 'active' => true ],
    'pauli' => [ 'name' => 'PAULI', 'duration' => 60, 'active' => true ],
    'personality' => [ 'name' => 'KEPRIBADIAN', 'duration' => 15, 'active' => true ],
    'disc' => [ 'name' => 'DISC', 'duration' => 10, 'active' => true ],
    'papi' => [ 'name' => 'PAPI KOSTICK', 'duration' => 10, 'active' => true ],
];
$psychotest_tests = (array) get_option( 'recruitment_psychotest_tests', [] );
$active_psychotest_tests = [];
$psychotest_online_test_groups = [
    'iq' => [ [ 'key' => 'iq', 'label' => 'IQ' ] ],
    'pauli' => [ [ 'key' => 'pauli', 'label' => 'PAULI' ] ],
    'personality' => [
        [ 'key' => 'disc', 'label' => 'DISC' ],
        [ 'key' => 'papi', 'label' => 'PAPI Kostick' ],
    ],
];
$psychotest_selected_tests = is_array( $psychotest_settings['online_tests'] )
    ? array_map( static fn ( $test ): string => sanitize_key( (string) $test ), array_filter( $psychotest_settings['online_tests'], 'is_scalar' ) )
    : [];
foreach ( $psychotest_online_test_groups as $group_key => $group_tests ) {
    if ( ! in_array( $group_key, $psychotest_selected_tests, true ) ) {
        continue;
    }
    foreach ( $group_tests as $test_info ) {
        $test = wp_parse_args( (array) ( $psychotest_tests[ $test_info['key'] ] ?? [] ), $psychotest_test_defaults[ $test_info['key'] ] );
        if ( ! empty( $test['active'] ) ) {
            $active_psychotest_tests[] = $test_info['label'] . ' (' . absint( $test['duration'] ) . ' menit)';
        }
    }
}
$psychotest_assessment_user_id = $preview ? 0 : recruitment_get_assessment_user_id();
$psychotest_iq_state = $psychotest_assessment_user_id > 0
    ? recruitment_get_assessment_state( $psychotest_assessment_user_id, 'recruitment_iq_assessment' )
    : [];
$psychotest_pauli_state = $psychotest_assessment_user_id > 0
    ? recruitment_get_assessment_state( $psychotest_assessment_user_id, 'recruitment_pauli_assessment' )
    : [];
$psychotest_personality_state = $psychotest_assessment_user_id > 0
    ? recruitment_get_assessment_state( $psychotest_assessment_user_id, 'recruitment_personality_assessment' )
    : [];
$psychotest_personality_tests_state = is_array( $psychotest_personality_state['tests'] ?? null )
    ? $psychotest_personality_state['tests']
    : [];
$psychotest_active_personality_test = sanitize_key( (string) ( $psychotest_personality_state['active_test'] ?? '' ) );
$psychotest_personality_enabled = recruitment_is_psychotest_test_enabled( 'disc' )
    && recruitment_is_psychotest_test_enabled( 'papi' );
$psychotest_preparation_test = '';
if ( 'active' === ( $psychotest_iq_state['status'] ?? '' ) && recruitment_is_psychotest_test_enabled( 'iq' ) ) {
    $psychotest_preparation_test = 'iq';
} elseif ( 'active' === ( $psychotest_pauli_state['status'] ?? '' ) && recruitment_is_psychotest_test_enabled( 'pauli' ) ) {
    $psychotest_preparation_test = 'pauli';
} elseif (
    in_array( $psychotest_active_personality_test, [ 'disc', 'papi' ], true )
    && 'active' === ( $psychotest_personality_tests_state[ $psychotest_active_personality_test ]['status'] ?? '' )
    && $psychotest_personality_enabled
) {
    $psychotest_preparation_test = $psychotest_active_personality_test;
} elseif ( 'complete' !== ( $psychotest_iq_state['status'] ?? '' ) && recruitment_is_psychotest_test_enabled( 'iq' ) ) {
    $psychotest_preparation_test = 'iq';
} elseif ( 'complete' !== ( $psychotest_pauli_state['status'] ?? '' ) && recruitment_is_psychotest_test_enabled( 'pauli' ) ) {
    $psychotest_preparation_test = 'pauli';
} elseif ( 'complete' !== ( $psychotest_personality_tests_state['disc']['status'] ?? '' ) && $psychotest_personality_enabled ) {
    $psychotest_preparation_test = 'disc';
} elseif ( 'complete' !== ( $psychotest_personality_tests_state['papi']['status'] ?? '' ) && $psychotest_personality_enabled ) {
    $psychotest_preparation_test = 'papi';
}
$psychotest_start_timestamp = recruitment_psychotest_start_timestamp( $psychotest_settings );
$psychotest_closing_timestamp = recruitment_psychotest_closing_timestamp( $psychotest_settings );
$psychotest_online_window_open = 'online' === $psychotest_mode
    && 'active' === $psychotest_settings['status']
    && null !== $psychotest_start_timestamp
    && null !== $psychotest_closing_timestamp
    && $psychotest_start_timestamp <= time()
    && $psychotest_closing_timestamp > time();
$psychotest_attendance_window = recruitment_psychotest_attendance_window( $psychotest_settings );
$psychotest_closing_date = 'Belum diatur';
if ( is_string( $psychotest_settings['closing_date'] ) && preg_match( '/^\d{4}-\d{2}-\d{2}$/', $psychotest_settings['closing_date'] ) ) {
    $parsed_closing_date = DateTimeImmutable::createFromFormat( '!Y-m-d', $psychotest_settings['closing_date'], wp_timezone() );
    if ( $parsed_closing_date instanceof DateTimeImmutable && $parsed_closing_date->format( 'Y-m-d' ) === $psychotest_settings['closing_date'] ) {
        $psychotest_closing_date = wp_date( 'j F Y', $parsed_closing_date->getTimestamp() );
    }
}
$psychotest_closing_time = is_string( $psychotest_settings['closing_time'] ) && preg_match( '/^(?:[01]\d|2[0-3]):[0-5]\d$/', $psychotest_settings['closing_time'] )
    ? $psychotest_settings['closing_time']
    : 'Belum diatur';
$psychotest_confirmation_deadline = null;
$psychotest_confirmation_deadline_configured = is_string( $psychotest_settings['confirmation_deadline'] ) && '' !== $psychotest_settings['confirmation_deadline'];
if ( $psychotest_confirmation_deadline_configured ) {
    $psychotest_confirmation_deadline = DateTimeImmutable::createFromFormat( '!Y-m-d\TH:i', $psychotest_settings['confirmation_deadline'], wp_timezone() );
    if ( $psychotest_confirmation_deadline instanceof DateTimeImmutable && $psychotest_confirmation_deadline->format( 'Y-m-d\TH:i' ) !== $psychotest_settings['confirmation_deadline'] ) {
        $psychotest_confirmation_deadline = null;
    }
}
$database_error = ! $preview && $database->has_error();
$tracking_stage_labels = [
    'ADMINISTRASI' => 'Seleksi Administrasi',
    'SCREENING' => 'Seleksi Administrasi',
    'PSIKOTES' => 'Psikotes',
    'INTERVIEW_HR' => 'Wawancara HR',
    'INTERVIEW_USER' => 'Wawancara Departemen',
    'WAWANCARA_USER' => 'Wawancara Departemen',
    'FINAL' => 'Keputusan Akhir',
];
$tracking_status_labels = [
    'SUBMITTED' => 'Lamaran Diterima',
    'SCREENING' => 'Seleksi Administrasi',
    'PSIKOTES' => 'Psikotes',
    'INTERVIEW_HR' => 'Wawancara HR',
    'INTERVIEW_USER' => 'Wawancara Departemen',
    'DITERIMA' => 'Diterima',
    'DITOLAK' => 'Tidak Lolos',
];
$psychotest_attendance = null;
$psychotest_attendance_lookup_failed = false;
if ( ! $preview && $selected_application && $is_psychotest_stage && ! empty( $psychotest_settings['attendance_confirmation'] ) ) {
    $psychotest_attendance = $database->get_psychotest_attendance( (string) $applicant['id'], $selected_application_id );
    $psychotest_attendance_lookup_failed = $database->has_error();
}
$attendance_result = isset( $_GET['attendance_result'] ) && is_scalar( $_GET['attendance_result'] )
    ? sanitize_key( wp_unslash( (string) $_GET['attendance_result'] ) )
    : '';
$attendance_messages = [
    'confirmed' => 'Konfirmasi kehadiran berhasil dicatat. Pengingat jadwal telah dikirim ke email Anda.',
    'confirmed_offline' => 'Konfirmasi kehadiran berhasil dicatat.',
    'already_confirmed' => 'Konfirmasi kehadiran Anda sudah tercatat.',
    'email_failed' => 'Konfirmasi kehadiran tersimpan, tetapi email pengingat gagal dikirim. Silakan coba tombol kirim ulang atau hubungi HR.',
    'email_record_failed' => 'Email pengingat telah dikirim, tetapi sistem belum dapat mencatat status pengirimannya. Hubungi HR sebelum meminta pengiriman ulang.',
    'save_failed' => 'Konfirmasi belum dapat disimpan. Silakan coba kembali atau hubungi HR.',
    'expired' => 'Batas waktu konfirmasi kehadiran sudah lewat.',
    'closed' => 'Psikotes sedang tidak aktif sehingga konfirmasi kehadiran ditutup.',
    'disabled' => 'Konfirmasi kehadiran tidak diwajibkan untuk jadwal ini.',
    'assessment_open' => 'Jadwal psikotes online sudah dibuka. Silakan mulai persiapan tes dari informasi psikotes.',
    'time_updated' => 'Tanggal dan waktu konfirmasi berhasil diperbarui.',
    'invalid_time' => 'Tanggal atau jam di luar jadwal yang ditentukan HR. Pilih kembali dalam batas jadwal.',
    'time_update_failed' => 'Tanggal dan waktu konfirmasi belum dapat diperbarui. Silakan coba kembali.',
];
$reopen_psychotest_info = isset( $_GET['show_psychotest_info'] )
    && is_scalar( $_GET['show_psychotest_info'] )
    && '1' === sanitize_text_field( wp_unslash( (string) $_GET['show_psychotest_info'] ) );
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
            $status_label = $tracking_status_labels[ strtoupper( $status ) ] ?? $status;
            $timestamp = strtotime( (string) ( $selected_application['created_at'] ?? '' ) );
            ?>
            <section class="daw-account__card daw-account__process-summary"><div class="daw-account__process-identity"><span class="daw-account__tracking-label">Status lamaran</span><span class="daw-account__status"><?= esc_html( $status_label ?: 'Menunggu pembaruan' ) ?></span><h2><?= esc_html( $selected_application['title'] ) ?></h2><small><?= esc_html( trim( (string) $selected_application['department'] . ' · ' . (string) $selected_application['location'], ' ·' ) ) ?></small></div><div class="daw-account__fields"><span>No. Lamaran<strong><?= esc_html( $selected_application['id'] ) ?></strong></span><span>Token Tracking<strong><?= esc_html( (string) ( $selected_application['token'] ?? '' ) ?: 'Belum tersedia' ) ?></strong></span><span>Tanggal melamar<strong><?= esc_html( $timestamp ? wp_date( 'j F Y', $timestamp ) : '—' ) ?></strong></span><span>Tahap saat ini<strong><?= esc_html( $timeline ? ( $tracking_stage_labels[ strtoupper( trim( (string) $timeline[ count( $timeline ) - 1 ]['stage'] ) ) ] ?? $timeline[ count( $timeline ) - 1 ]['stage'] ) : 'Belum ada riwayat tahap' ) ?></strong></span></div><?php if ( $is_psychotest_stage && ! $timeline ) : ?><button class="daw-recruitment__button daw-psychotest-info__trigger" type="button" data-open-psychotest-info>Lihat Informasi Psikotes</button><?php endif; ?></section>
            <section class="daw-account__card"><h2>Riwayat Status Seleksi</h2><p class="daw-account__muted">Menampilkan pembaruan yang tercatat oleh tim rekrutmen.</p>
                <?php if ( $timeline ) : ?><div class="daw-account__timeline"><?php foreach ( $timeline as $index => $event ) :
                    $event_time = strtotime( (string) ( $event['changed_at'] ?? '' ) );
                    $state = $index === count( $timeline ) - 1 ? 'current' : 'done';
                    ?><article class="is-<?= esc_attr( $state ) ?>"><span class="daw-account__timeline-marker"><?= 'done' === $state ? '&#10003;' : '●' ?></span><div><strong><?= esc_html( $tracking_stage_labels[ strtoupper( trim( (string) $event['stage'] ) ) ] ?? ( $event['stage'] ?: 'Tahap seleksi' ) ) ?></strong><small><?= esc_html( $event_time ? wp_date( 'j F Y H:i', $event_time ) : '—' ) ?></small><?php if ( '' !== trim( (string) $event['note'] ) ) : ?><p><?= esc_html( $event['note'] ) ?></p><?php endif; ?><?php if ( $is_psychotest_stage && $index === count( $timeline ) - 1 ) : ?><button class="daw-recruitment__button daw-psychotest-info__trigger" type="button" data-open-psychotest-info>Lihat Informasi Psikotes</button><?php endif; ?></div><b><?= esc_html( $tracking_status_labels[ strtoupper( trim( (string) $event['status'] ) ) ] ?? ( $event['status'] ?: 'Diperbarui' ) ) ?></b></article><?php endforeach; ?></div><?php else : ?><p class="daw-account__muted">Belum ada pembaruan status untuk lamaran ini.</p><?php endif; ?>
            </section>
            <?php if ( $is_psychotest_stage ) : ?>
                <dialog class="daw-psychotest-info" data-psychotest-info-dialog data-auto-open="<?= $reopen_psychotest_info ? 'true' : 'false' ?>" aria-labelledby="daw-psychotest-info-title">
                    <div class="daw-psychotest-info__header">
                        <div><span class="daw-recruitment__eyebrow">INFORMASI TAHAP SELEKSI</span><h2 id="daw-psychotest-info-title">Informasi Psikotes</h2></div>
                        <button class="daw-psychotest-info__close" type="button" data-close-psychotest-info aria-label="Tutup informasi psikotes">&times;</button>
                    </div>
                    <p class="daw-psychotest-info__intro">Informasi ini mengikuti pengaturan terbaru dari tim HR.</p>
                    <div class="daw-psychotest-info__grid">
                        <div><span>Status tes</span><strong><?= 'active' === $psychotest_settings['status'] ? 'Aktif' : 'Nonaktif' ?></strong></div>
                        <div><span>Metode pelaksanaan</span><strong><?= 'online' === $psychotest_mode ? 'Online' : 'Offline' ?></strong></div>
                        <div><span>Tanggal</span><strong><?= esc_html( $psychotest_date ) ?></strong></div>
                        <?php if ( 'online' === $psychotest_mode ) : ?><div><span>Psikotes ditutup</span><strong><?= esc_html( $psychotest_closing_date . ' pukul ' . $psychotest_closing_time ) ?></strong></div><?php else : ?><div><span>Rentang tanggal psikotes</span><strong><?= esc_html( $psychotest_date . ' - ' . $psychotest_closing_date ) ?></strong></div><?php endif; ?>
                        <div><span>Waktu</span><strong><?= esc_html( $psychotest_start_time . ' - ' . $psychotest_end_time ) ?></strong></div>
                        <div><span>Kamera</span><strong><?= ! empty( $psychotest_settings['camera_required'] ) ? 'Wajib diaktifkan' : 'Tidak diwajibkan' ?></strong></div>
                        <div><span>Dokumen yang diperlukan</span><strong><?= esc_html( $psychotest_required_documents ? implode( ', ', $psychotest_required_documents ) : 'Tidak ada dokumen khusus' ) ?></strong></div>
                        <?php if ( 'offline' === $psychotest_mode ) : ?>
                            <div><span>Lokasi</span><strong><?= esc_html( (string) $psychotest_settings['location'] ?: 'Belum diatur' ) ?></strong></div>
                            <div><span>Alamat lengkap</span><strong><?= esc_html( (string) $psychotest_settings['address'] ?: 'Belum diatur' ) ?></strong></div>
                            <div><span>Dress code</span><strong><?= esc_html( (string) $psychotest_settings['dress_code'] ?: 'Belum diatur' ) ?></strong></div>
                        <?php else : ?>
                            <div><span>Akses tes</span><strong>Ikuti instruksi akses dari HR pada jadwal yang ditentukan.</strong></div>
                        <?php endif; ?>
                        <div><span>Konfirmasi kehadiran</span><strong><?= 'online' === $psychotest_mode && $psychotest_online_window_open ? 'Tidak diperlukan; tes online sudah dibuka' : ( ! empty( $psychotest_settings['attendance_confirmation'] ) ? 'Wajib konfirmasi' : 'Tidak diperlukan' ) ?></strong></div>
                        <div><span>Kontak</span><strong><?= esc_html( (string) $psychotest_settings['contact_person'] ?: 'Hubungi tim HR' ) ?></strong></div>
                        <div><span>Tes yang aktif</span><strong><?= esc_html( $active_psychotest_tests ? implode( ', ', $active_psychotest_tests ) : 'Belum ada tes yang diaktifkan' ) ?></strong></div>
                    </div>
                    <?php if ( '' !== trim( (string) $psychotest_settings['instructions'] ) ) : ?><div class="daw-psychotest-info__instructions"><strong>Informasi tambahan dari HR</strong><p><?= nl2br( esc_html( (string) $psychotest_settings['instructions'] ) ) ?></p></div><?php endif; ?>
                    <?php if ( 'inactive' === $psychotest_settings['status'] ) : ?><p class="daw-psychotest-info__notice">Tes sedang dinonaktifkan. Silakan menunggu informasi lanjutan dari HR.</p><?php endif; ?>
                    <?php if ( 'online' === $psychotest_mode && $psychotest_online_window_open ) : ?>
                        <?php if ( '' !== $psychotest_preparation_test ) : ?>
                            <div class="daw-psychotest-info__actions">
                                <a class="daw-recruitment__button" href="<?= esc_url( recruitment_get_public_url( 'psychotest-preparation', [ 'test' => $psychotest_preparation_test, 'application_id' => $selected_application_id ] ) ) ?>">Mulai Persiapan Tes Psikotes</a>
                            </div>
                        <?php else : ?>
                            <p class="daw-psychotest-info__notice">Semua tes yang aktif telah diselesaikan, atau belum ada tes online aktif yang dapat dimulai.</p>
                        <?php endif; ?>
                        <?php if ( is_array( $psychotest_attendance ) && ! empty( $psychotest_attendance['confirmed'] ) ) :
                            $confirmed_at = (string) ( $psychotest_attendance['confirmed_at'] ?? '' );
                            $confirmed_datetime = '' !== $confirmed_at
                                ? DateTimeImmutable::createFromFormat( '!Y-m-d H:i:s', $confirmed_at, wp_timezone() )
                                : false;
                            $confirmed_datetime_is_valid = $confirmed_datetime instanceof DateTimeImmutable
                                && $confirmed_datetime->format( 'Y-m-d H:i:s' ) === $confirmed_at;
                            ?>
                            <?php if ( isset( $attendance_messages[ $attendance_result ] ) ) : ?><p class="daw-psychotest-attendance__notice" role="status"><?= esc_html( $attendance_messages[ $attendance_result ] ) ?></p><?php endif; ?>
                            <?php if ( $confirmed_datetime_is_valid ) : ?>
                                <section class="daw-psychotest-attendance" aria-label="Waktu konfirmasi kehadiran">
                                    <p>Konfirmasi Anda tercatat pada <?= esc_html( wp_date( 'j F Y', $confirmed_datetime->getTimestamp() ) . ' pukul ' . wp_date( 'H:i', $confirmed_datetime->getTimestamp() ) ) ?>.</p>
                                    <details class="daw-psychotest-attendance__edit">
                                        <summary>Edit tanggal dan jam</summary>
                                        <form method="post" action="<?= esc_url( recruitment_get_public_url( 'application-process', [ 'application_id' => $selected_application_id ] ) ) ?>">
                                            <?php wp_nonce_field( 'daw_edit_psychotest_attendance_' . $selected_application_id ); ?>
                                            <input type="hidden" name="psychotest_attendance_action" value="update_psychotest_attendance_time">
                                            <input type="hidden" name="application_id" value="<?= esc_attr( $selected_application_id ) ?>">
                                            <?php if ( null !== $psychotest_attendance_window ) : ?>
                                                <small>Pilih tanggal <?= esc_html( $psychotest_attendance_window['start_date'] . ' sampai ' . $psychotest_attendance_window['end_date'] ) ?> dan jam <?= esc_html( $psychotest_attendance_window['start_time'] . ' sampai ' . $psychotest_attendance_window['end_time'] ) ?> sesuai jadwal HR.</small>
                                                <label for="daw-psychotest-confirmed-date">Tanggal konfirmasi</label>
                                                <input id="daw-psychotest-confirmed-date" type="date" name="confirmed_date" min="<?= esc_attr( $psychotest_attendance_window['start_date'] ) ?>" max="<?= esc_attr( $psychotest_attendance_window['end_date'] ) ?>" value="<?= esc_attr( $confirmed_datetime->format( 'Y-m-d' ) ) ?>" required>
                                                <label for="daw-psychotest-confirmed-time">Jam konfirmasi</label>
                                                <input id="daw-psychotest-confirmed-time" type="time" name="confirmed_time" min="<?= esc_attr( $psychotest_attendance_window['start_time'] ) ?>" max="<?= esc_attr( $psychotest_attendance_window['end_time'] ) ?>" value="<?= esc_attr( $confirmed_datetime->format( 'H:i' ) ) ?>" required>
                                                <button class="daw-recruitment__button" type="submit">Simpan Perubahan</button>
                                            <?php else : ?>
                                                <small class="daw-psychotest-attendance__notice">Jadwal tanggal dan jam belum lengkap atau tidak valid. Hubungi HR sebelum mengedit waktu konfirmasi.</small>
                                            <?php endif; ?>
                                        </form>
                                    </details>
                                </section>
                            <?php endif; ?>
                        <?php endif; ?>
                    <?php elseif ( $is_psychotest_stage && ! empty( $psychotest_settings['attendance_confirmation'] ) ) :
                        $deadline_is_valid = $psychotest_confirmation_deadline instanceof DateTimeImmutable;
                        $deadline_is_open = ! $psychotest_confirmation_deadline_configured
                            || ( $deadline_is_valid && $psychotest_confirmation_deadline->getTimestamp() >= time() );
                        $attendance_confirmed = is_array( $psychotest_attendance ) && ! empty( $psychotest_attendance['confirmed'] );
                        $psychotest_closing_is_ready = 'offline' === $psychotest_mode
                            || ( null !== $psychotest_closing_timestamp && $psychotest_closing_timestamp > time() );
                        $attendance_can_confirm = 'active' === $psychotest_settings['status'] && $deadline_is_open && $psychotest_closing_is_ready;
                        ?>
                        <section class="daw-psychotest-attendance" aria-labelledby="daw-psychotest-attendance-title">
                            <h3 id="daw-psychotest-attendance-title">Konfirmasi Kehadiran Psikotes</h3>
                            <?php if ( isset( $attendance_messages[ $attendance_result ] ) ) : ?><p class="daw-psychotest-attendance__notice" role="status"><?= esc_html( $attendance_messages[ $attendance_result ] ) ?></p><?php endif; ?>
                            <?php if ( $psychotest_attendance_lookup_failed ) : ?>
                                <p class="daw-psychotest-attendance__notice">Layanan konfirmasi kehadiran belum siap. Silakan hubungi tim HR.</p>
                            <?php elseif ( $attendance_confirmed ) : ?>
                                <?php
                                $confirmed_at = (string) ( $psychotest_attendance['confirmed_at'] ?? '' );
                                $confirmed_datetime = '' !== $confirmed_at
                                    ? DateTimeImmutable::createFromFormat( '!Y-m-d H:i:s', $confirmed_at, wp_timezone() )
                                    : false;
                                $confirmed_datetime_is_valid = $confirmed_datetime instanceof DateTimeImmutable
                                    && $confirmed_datetime->format( 'Y-m-d H:i:s' ) === $confirmed_at;
                                $confirmed_datetime_label = $confirmed_datetime_is_valid
                                    ? wp_date( 'j F Y', $confirmed_datetime->getTimestamp() ) . ' pukul ' . wp_date( 'H:i', $confirmed_datetime->getTimestamp() )
                                    : 'waktu yang tercatat';
                                ?>
                                <p>Konfirmasi Anda tercatat pada <?= esc_html( $confirmed_datetime_label ) ?>.</p>
                                <?php if ( isset( $attendance_messages[ $attendance_result ] ) ) : ?><p class="daw-psychotest-attendance__notice" role="status"><?= esc_html( $attendance_messages[ $attendance_result ] ) ?></p><?php endif; ?>
                                <?php if ( $confirmed_datetime_is_valid ) : ?>
                                    <details class="daw-psychotest-attendance__edit">
                                        <summary>Edit tanggal dan jam</summary>
                                        <form method="post" action="<?= esc_url( recruitment_get_public_url( 'application-process', [ 'application_id' => $selected_application_id ] ) ) ?>">
                                            <?php wp_nonce_field( 'daw_edit_psychotest_attendance_' . $selected_application_id ); ?>
                                            <input type="hidden" name="psychotest_attendance_action" value="update_psychotest_attendance_time">
                                            <input type="hidden" name="application_id" value="<?= esc_attr( $selected_application_id ) ?>">
                                            <?php if ( null !== $psychotest_attendance_window ) : ?>
                                                <small>Pilih tanggal <?= esc_html( $psychotest_attendance_window['start_date'] . ' sampai ' . $psychotest_attendance_window['end_date'] ) ?> dan jam <?= esc_html( $psychotest_attendance_window['start_time'] . ' sampai ' . $psychotest_attendance_window['end_time'] ) ?> sesuai jadwal HR.</small>
                                                <label for="daw-psychotest-confirmed-date">Tanggal konfirmasi</label>
                                                <input id="daw-psychotest-confirmed-date" type="date" name="confirmed_date" min="<?= esc_attr( $psychotest_attendance_window['start_date'] ) ?>" max="<?= esc_attr( $psychotest_attendance_window['end_date'] ) ?>" value="<?= esc_attr( $confirmed_datetime->format( 'Y-m-d' ) ) ?>" required>
                                                <label for="daw-psychotest-confirmed-time">Jam konfirmasi</label>
                                                <input id="daw-psychotest-confirmed-time" type="time" name="confirmed_time" min="<?= esc_attr( $psychotest_attendance_window['start_time'] ) ?>" max="<?= esc_attr( $psychotest_attendance_window['end_time'] ) ?>" value="<?= esc_attr( $confirmed_datetime->format( 'H:i' ) ) ?>" required>
                                                <button class="daw-recruitment__button" type="submit">Simpan Perubahan</button>
                                            <?php else : ?>
                                                <small class="daw-psychotest-attendance__notice">Jadwal tanggal dan jam belum lengkap atau tidak valid. Hubungi HR sebelum mengedit waktu konfirmasi.</small>
                                            <?php endif; ?>
                                        </form>
                                    </details>
                                <?php endif; ?>
                                <?php if ( 'online' === $psychotest_mode ) : ?>
                                    <p><?= ! empty( $psychotest_attendance['reminder_sent'] ) ? 'Pengingat jadwal telah dikirim ke email akun Anda.' : 'Email pengingat jadwal belum terkirim.' ?></p>
                                    <?php if ( empty( $psychotest_attendance['reminder_sent'] ) && $attendance_can_confirm ) : ?><form method="post" action="<?= esc_url( recruitment_get_public_url( 'application-process', [ 'application_id' => $selected_application_id ] ) ) ?>"><?php wp_nonce_field( 'daw_confirm_psychotest_attendance' ); ?><input type="hidden" name="psychotest_attendance_action" value="confirm_psychotest_attendance"><input type="hidden" name="application_id" value="<?= esc_attr( $selected_application_id ) ?>"><button class="daw-recruitment__button" type="submit">Kirim Ulang Pengingat Email</button></form><?php endif; ?>
                                <?php endif; ?>
                            <?php else : ?>
                                <p>Mohon konfirmasi apakah Anda dapat hadir. Batas konfirmasi: <strong><?= $deadline_is_valid ? esc_html( wp_date( 'j F Y', $psychotest_confirmation_deadline->getTimestamp() ) . ' pukul ' . wp_date( 'H:i', $psychotest_confirmation_deadline->getTimestamp() ) ) : ( $psychotest_confirmation_deadline_configured ? 'Pengaturan waktu tidak valid; hubungi HR.' : 'Belum ditentukan HR; silakan konfirmasi sesegera mungkin.' ) ?></strong></p>
                                <?php if ( ! $attendance_can_confirm ) : ?>
                                    <p class="daw-psychotest-attendance__notice"><?= ! $deadline_is_open ? 'Batas waktu konfirmasi kehadiran sudah lewat.' : ( ! $psychotest_closing_is_ready ? 'Tanggal dan jam penutupan psikotes online belum diatur HR.' : 'Konfirmasi ditutup karena psikotes sedang tidak aktif.' ) ?></p>
                                <?php else : ?>
                                    <form method="post" action="<?= esc_url( recruitment_get_public_url( 'application-process', [ 'application_id' => $selected_application_id ] ) ) ?>">
                                        <?php wp_nonce_field( 'daw_confirm_psychotest_attendance' ); ?>
                                        <input type="hidden" name="psychotest_attendance_action" value="confirm_psychotest_attendance">
                                        <input type="hidden" name="application_id" value="<?= esc_attr( $selected_application_id ) ?>">
                                        <button class="daw-recruitment__button" type="submit"><?= 'online' === $psychotest_mode ? 'Konfirmasi Hadir & Kirim Pengingat Email' : 'Ya, Saya Bisa Hadir' ?></button>
                                    </form>
                                    <?php if ( 'online' === $psychotest_mode ) : ?><small>Setelah konfirmasi, sistem akan mengirim pengingat jadwal ke email akun pelamar.</small><?php endif; ?>
                                <?php endif; ?>
                            <?php endif; ?>
                        </section>
                    <?php elseif ( isset( $attendance_messages[ $attendance_result ] ) ) : ?>
                        <p class="daw-psychotest-info__notice" role="status"><?= esc_html( $attendance_messages[ $attendance_result ] ) ?></p>
                    <?php endif; ?>
                </dialog>
            <?php endif; ?>
            <div class="daw-account__tracking-footer"><span>Status diperbarui saat tim rekrutmen mencatat tahap seleksi.</span></div>
        <?php endif; ?>
    </main>
    <?php require recruitment_get_plugin_path( 'public/components/footer.php' ); ?>
</div>
