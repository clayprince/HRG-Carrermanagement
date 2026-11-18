<?php
$global_defaults = [
    'camera_required' => true,
    'shuffle_questions' => true,
    'status' => 'active',
    'mode' => 'offline',
    'test_date' => '2026-09-21',
    'closing_date' => '',
    'closing_time' => '',
    'confirmation_deadline' => '',
    'start_time' => '08:00',
    'end_time' => '11:00',
    'location' => 'PT Daya Adicipta Wisesa',
    'address' => 'Jl. A.A. Maramis, Sulawesi Utara',
    'contact_person' => 'HR Recruitment DAW',
    'dress_code' => 'Formal / Smart Casual',
    'max_participants' => 30,
    'attendance_confirmation' => true,
    'online_tests' => [ 'iq', 'pauli', 'personality', 'disc', 'papi' ],
    'documents' => [ 'ktp', 'writing_tools' ],
    'instructions' => '',
];
$test_defaults = [
    'iq' => [ 'name' => 'IQ', 'duration' => 20, 'question_count' => 10, 'passing_score' => 70, 'active' => true, 'shuffle' => true ],
    'pauli' => [ 'name' => 'PAULI', 'duration' => 60, 'question_count' => 10, 'passing_score' => 0, 'active' => true, 'shuffle' => false ],
    'personality' => [ 'name' => 'KEPRIBADIAN', 'duration' => 15, 'question_count' => 30, 'passing_score' => 0, 'active' => true, 'shuffle' => true ],
    'disc' => [ 'name' => 'DISC', 'duration' => 10, 'question_count' => 5, 'passing_score' => 0, 'active' => true, 'shuffle' => true ],
    'papi' => [ 'name' => 'PAPI KOSTICK', 'duration' => 10, 'question_count' => 8, 'passing_score' => 0, 'active' => true, 'shuffle' => true ],
];
$global_settings = wp_parse_args( (array) get_option( 'recruitment_psychotest_settings', [] ), $global_defaults );
$global_settings = recruitment_sync_psychotest_closing( $global_settings );

$pauli_config = function_exists( 'recruitment_get_pauli_config' )
    ? recruitment_get_pauli_config()
    : [ 'duration_minutes' => 60, 'columns' => 10, 'numbers_per_column' => 50, 'interval_seconds' => 60, 'randomize' => true ];

$test_settings = (array) get_option( 'recruitment_psychotest_tests', [] );
foreach ( $test_defaults as $test_key => $defaults ) {
    $test_settings[ $test_key ] = wp_parse_args( (array) ( $test_settings[ $test_key ] ?? [] ), $defaults );
}
$test_settings['pauli']['duration'] = $pauli_config['duration_minutes'];
$test_settings['pauli']['question_count'] = $pauli_config['columns'];

$selected_test = sanitize_key( $_GET['test'] ?? 'iq' );
if ( ! isset( $test_settings[ $selected_test ] ) ) {
    $selected_test = 'iq';
}
$notice = '';
$admin_user = wp_get_current_user();
$hr_identity = recruitment_get_current_hr_identity();
$logout_form = recruitment_logout_form( 'Keluar', home_url( '/' ) );
$proctoring_settings = new Recruitment_Psychotest_Proctoring();
$violation_config = $proctoring_settings->get_violation_config();

if ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['psychotest_settings_action'] ) ) {
    check_admin_referer( 'recruitment_psychotest_settings' );
    if ( ! current_user_can( 'manage_recruitment' ) ) {
        wp_die( esc_html__( 'Anda tidak memiliki izin untuk mengubah pengaturan psikotes.', 'recruitment-plugin' ) );
    }

    $action = sanitize_key( wp_unslash( $_POST['psychotest_settings_action'] ) );
    if ( 'save_global' === $action ) {
        $posted = isset( $_POST['global_settings'] ) && is_array( $_POST['global_settings'] ) ? wp_unslash( $_POST['global_settings'] ) : [];
        $documents = isset( $posted['documents'] ) && is_array( $posted['documents'] ) ? array_map( 'sanitize_key', $posted['documents'] ) : [];
        $global_settings = [
            'camera_required' => ! empty( $posted['camera_required'] ),
            'shuffle_questions' => ! empty( $posted['shuffle_questions'] ),
            'status' => in_array( $posted['status'] ?? '', [ 'active', 'inactive' ], true ) ? $posted['status'] : 'inactive',
            'mode' => in_array( $posted['mode'] ?? '', [ 'online', 'offline' ], true ) ? $posted['mode'] : 'offline',
            'test_date' => sanitize_text_field( $posted['test_date'] ?? '' ),
            'closing_date' => sanitize_text_field( $posted['closing_date'] ?? '' ),
            'closing_time' => sanitize_text_field( $posted['closing_time'] ?? '' ),
            'confirmation_deadline' => sanitize_text_field( $posted['confirmation_deadline'] ?? '' ),
            'start_time' => sanitize_text_field( $posted['start_time'] ?? '' ),
            'end_time' => sanitize_text_field( $posted['end_time'] ?? '' ),
            'location' => sanitize_text_field( $posted['location'] ?? '' ),
            'address' => sanitize_text_field( $posted['address'] ?? '' ),
            'contact_person' => sanitize_text_field( $posted['contact_person'] ?? '' ),
            'dress_code' => sanitize_text_field( $posted['dress_code'] ?? '' ),
            'max_participants' => max( 1, absint( $posted['max_participants'] ?? 1 ) ),
            'attendance_confirmation' => ! empty( $posted['attendance_confirmation'] ),
            'online_tests' => isset( $posted['online_tests'] ) && is_array( $posted['online_tests'] )
                ? array_values( array_intersect( array_map( static fn ( $test ): string => sanitize_key( (string) $test ), array_filter( $posted['online_tests'], 'is_scalar' ) ), [ 'iq', 'pauli', 'personality', 'disc', 'papi' ] ) )
                : [],
            'documents' => array_values( array_intersect( $documents, [ 'ktp', 'writing_tools', 'application_letter', 'other' ] ) ),
            'instructions' => sanitize_textarea_field( $posted['instructions'] ?? '' ),
        ];
        if ( 'online' === $global_settings['mode'] && 'active' === $global_settings['status'] ) {
            if ( null === recruitment_psychotest_start_timestamp( $global_settings ) ) {
                wp_die( esc_html__( 'Tanggal dan jam mulai wajib diisi dengan benar sebelum psikotes online dapat diaktifkan.', 'recruitment-plugin' ), '', [ 'response' => 400 ] );
            }
            if ( null === recruitment_psychotest_closing_timestamp( $global_settings ) ) {
                wp_die( esc_html__( 'Tanggal dan jam penutupan wajib diisi dengan benar sebelum psikotes online dapat diaktifkan.', 'recruitment-plugin' ), '', [ 'response' => 400 ] );
            }
        }
        update_option( 'recruitment_psychotest_settings', $global_settings );
        recruitment_schedule_psychotest_closing( $global_settings );
        $notice = 'Konfigurasi global berhasil disimpan.';
    } elseif ( 'save_test' === $action ) {
        $test_key = sanitize_key( $_POST['test_key'] ?? '' );
        if ( 'pauli' === $test_key ) {
            $pauli_posted = isset( $_POST['pauli_config'] ) && is_array( $_POST['pauli_config'] ) ? wp_unslash( $_POST['pauli_config'] ) : [];
            $pauli_config = [
                'duration_minutes' => max( 5, absint( $pauli_posted['duration_minutes'] ?? 60 ) ),
                'columns' => max( 1, absint( $pauli_posted['columns'] ?? 10 ) ),
                'numbers_per_column' => max( 10, absint( $pauli_posted['numbers_per_column'] ?? 50 ) ),
                'interval_seconds' => max( 5, absint( $pauli_posted['interval_seconds'] ?? 60 ) ),
                'randomize' => ! empty( $pauli_posted['randomize'] ),
            ];
            update_option( 'recruitment_psychotest_pauli_config', $pauli_config );
            $posted = isset( $_POST['test_settings'] ) && is_array( $_POST['test_settings'] ) ? wp_unslash( $_POST['test_settings'] ) : [];
            $test_settings['pauli'] = array_merge( $test_settings['pauli'], [
                'duration' => $pauli_config['duration_minutes'],
                'question_count' => $pauli_config['columns'],
                'camera_required' => ! empty( $posted['camera_required'] ),
                'active' => ! empty( $posted['active'] ),
            ] );
            update_option( 'recruitment_psychotest_tests', $test_settings );
            $notice = 'Konfigurasi tes Pauli berhasil disimpan.';
        } elseif ( isset( $test_settings[ $test_key ] ) ) {
            $posted = isset( $_POST['test_settings'] ) && is_array( $_POST['test_settings'] ) ? wp_unslash( $_POST['test_settings'] ) : [];
            $test_settings[ $test_key ] = array_merge( $test_settings[ $test_key ], [
                'duration' => max( 1, absint( $posted['duration'] ?? 1 ) ),
                'question_count' => max( 1, absint( $posted['question_count'] ?? 1 ) ),
                'passing_score' => min( 100, max( 0, absint( $posted['passing_score'] ?? 0 ) ) ),
                'camera_required' => ! empty( $posted['camera_required'] ),
                'active' => ! empty( $posted['active'] ),
                'shuffle' => ! empty( $posted['shuffle'] ),
            ] );
            update_option( 'recruitment_psychotest_tests', $test_settings );
            $notice = 'Konfigurasi tes ' . esc_html( $test_settings[ $test_key ]['name'] ) . ' berhasil disimpan.';
        }
    } elseif ( 'save_proctoring' === $action ) {
        // Handle unified camera switch
        if ( isset( $_POST['camera_required_unified'] ) ) {
            $global_settings['camera_required'] = '1' === (string) $_POST['camera_required_unified'];
            update_option( 'recruitment_psychotest_settings', $global_settings );
        }

        $preset = sanitize_key( $_POST['sensitivity_preset'] ?? 'standard' );
        $posted = isset( $_POST['violation_config'] ) && is_array( $_POST['violation_config'] )
            ? wp_unslash( $_POST['violation_config'] )
            : [];

        // Apply preset defaults if standard/strict/lenient chosen
        if ( in_array( $preset, [ 'strict', 'standard', 'lenient' ], true ) && empty( $posted['custom_applied'] ) ) {
            $preset_settings = [
                'strict' => [
                    'threshold' => 2.0,
                    'grace' => 1.0,
                    'recovery' => 1.0,
                    'confidence' => 60,
                ],
                'standard' => [
                    'threshold' => 4.0,
                    'grace' => 2.0,
                    'recovery' => 2.0,
                    'confidence' => 50,
                ],
                'lenient' => [
                    'threshold' => 7.0,
                    'grace' => 3.0,
                    'recovery' => 2.5,
                    'confidence' => 40,
                ],
            ][ $preset ];

            foreach ( [ 'FACE_NOT_DETECTED', 'MULTIPLE_FACES', 'FACE_TURNED_AWAY', 'CAMERA_STOPPED' ] as $ev_type ) {
                if ( ! isset( $posted[ $ev_type ] ) ) {
                    $posted[ $ev_type ] = [];
                }
                $posted[ $ev_type ]['enabled'] = ! empty( $_POST['detections_enabled'][ $ev_type ] ) ? 1 : 0;
                $posted[ $ev_type ]['threshold_seconds'] = $preset_settings['threshold'];
                $posted[ $ev_type ]['grace_seconds'] = $preset_settings['grace'];
                $posted[ $ev_type ]['recovery_seconds'] = $preset_settings['recovery'];
                $posted[ $ev_type ]['confidence_percent'] = $preset_settings['confidence'];
                $posted[ $ev_type ]['cooldown_seconds'] = $violation_config[ $ev_type ]['cooldown_ms'] / 1000;
                $posted[ $ev_type ]['repeat_warning_count'] = $violation_config[ $ev_type ]['repeat_warning_count'];
                $posted[ $ev_type ]['repeat_warning_window_seconds'] = $violation_config[ $ev_type ]['repeat_warning_window_ms'] / 1000;
                $posted[ $ev_type ]['repeat_serious_count'] = $violation_config[ $ev_type ]['repeat_serious_count'];
                $posted[ $ev_type ]['repeat_serious_window_seconds'] = $violation_config[ $ev_type ]['repeat_serious_window_ms'] / 1000;
                $posted[ $ev_type ]['severity'] = $violation_config[ $ev_type ]['severity'];
                $posted[ $ev_type ]['warning_message'] = $violation_config[ $ev_type ]['warning_message'];
                if ( 'FACE_TURNED_AWAY' === $ev_type ) {
                    $posted[ $ev_type ]['signal_threshold'] = 'strict' === $preset ? 0.25 : ( 'lenient' === $preset ? 0.45 : 0.35 );
                }
            }
        }

        if ( $proctoring_settings->save_violation_config( $posted ) ) {
            $violation_config = $proctoring_settings->get_violation_config();
            update_option( 'recruitment_psychotest_proctoring_preset', $preset );
            $notice = 'Pengaturan kamera & pemantauan pengawasan berhasil disimpan.';
        } else {
            wp_die( esc_html__( 'Konfigurasi pemantauan tidak dapat disimpan. Periksa penyimpanan WordPress lalu coba kembali.', 'recruitment-plugin' ), '', [ 'response' => 503 ] );
        }
    }
}

$current_preset = (string) get_option( 'recruitment_psychotest_proctoring_preset', 'standard' );
$selected_config = $test_settings[ $selected_test ];
$document_labels = [ 'ktp' => 'KTP', 'writing_tools' => 'Alat Tulis', 'application_letter' => 'Surat Lamaran', 'other' => 'Dokumen lainnya' ];
$test_order = [ 'iq', 'pauli', 'personality', 'disc', 'papi' ];
$is_online = 'online' === $global_settings['mode'];
?>
<div class="wrap daw-psychotest-settings" data-psychotest-settings>
    <header class="daw-psychotest-settings__header">
        <p class="daw-psychotest-settings__breadcrumb">DAW Admin <span>/</span> Setting Psikotes</p>
        <div class="daw-psychotest-settings__heading"><div><h1>Setting Psikotes</h1><p>Kelola jadwal, persyaratan, dan konfigurasi setiap tes.</p></div><div class="daw-psychotest-settings__header-account"><span class="daw-psychotest-settings__avatar" aria-hidden="true"><?= esc_html( $hr_identity['avatar'] ) ?></span><span><strong><?= esc_html( $hr_identity['name'] ) ?></strong><small><?= esc_html( $hr_identity['email'] ) ?></small></span><?= $logout_form ?></div></div>
    </header>
    <?php if ( $notice ) : ?><div class="notice notice-success is-dismissible"><p><?= esc_html( $notice ) ?></p></div><?php endif; ?>

    <!-- 1. Global Session / Schedule Settings -->
    <section class="daw-psychotest-settings__panel" aria-labelledby="psychotest-global-title">
        <div class="daw-psychotest-settings__panel-heading">
            <div>
                <h2 id="psychotest-global-title">Konfigurasi Jadwal &amp; Mode Pelaksanaan</h2>
                <p>Pilih mode pelaksanaan (Online / Offline) serta jadwal pelaksanaan psikotes.</p>
            </div>
            <span class="daw-psychotest-settings__badge daw-psychotest-settings__badge--<?= esc_attr( $global_settings['status'] ) ?>">
                <?= 'active' === $global_settings['status'] ? 'Aktif' : 'Nonaktif' ?>
            </span>
        </div>
        <form method="post" class="daw-psychotest-settings__form">
            <?php wp_nonce_field( 'recruitment_psychotest_settings' ); ?>
            <input type="hidden" name="psychotest_settings_action" value="save_global">

            <div class="daw-psychotest-settings__global-row">
                <label class="daw-psychotest-settings__switch" data-online-only <?= ! $is_online ? 'style="display:none;"' : '' ?>>
                    <span><strong>Kamera Wajib (Global)</strong><small>Peserta wajib menggunakan kamera selama tes.</small></span>
                    <input type="checkbox" name="global_settings[camera_required]" value="1" <?= checked( $global_settings['camera_required'], true, false ) ?>>
                    <span class="daw-psychotest-settings__switch-ui" aria-hidden="true"></span>
                </label>
                <label class="daw-psychotest-settings__switch" data-online-only <?= ! $is_online ? 'style="display:none;"' : '' ?>>
                    <span><strong>Acak Soal</strong><small>Urutan soal diacak per sesi.</small></span>
                    <input type="checkbox" name="global_settings[shuffle_questions]" value="1" <?= checked( $global_settings['shuffle_questions'], true, false ) ?>>
                    <span class="daw-psychotest-settings__switch-ui" aria-hidden="true"></span>
                </label>
                <label class="daw-psychotest-settings__switch daw-psychotest-settings__status-toggle">
                    <span><strong>Status Sesi Tes</strong><small data-psychotest-status-label><?= 'active' === $global_settings['status'] ? 'Aktif' : 'Tidak aktif' ?></small></span>
                    <input type="checkbox" name="global_settings[status]" value="active" data-psychotest-status-toggle <?= checked( $global_settings['status'], 'active', false ) ?>>
                    <span class="daw-psychotest-settings__switch-ui" aria-hidden="true"></span>
                </label>
            </div>

            <div class="daw-psychotest-settings__section-heading">
                <div>
                    <h3 data-psychotest-mode-heading>Jadwal / Informasi Psikotes <?= $is_online ? 'Online' : 'Offline' ?></h3>
                    <p data-psychotest-mode-description><?= $is_online ? 'Atur jadwal, tenggat penutupan, dan informasi pelaksanaan online.' : 'Atur rentang tanggal dan detail pelaksanaan psikotes secara langsung.' ?></p>
                </div>
                <span class="daw-psychotest-settings__badge daw-psychotest-settings__mode-badge daw-psychotest-settings__badge--<?= esc_attr( $global_settings['mode'] ) ?>" data-psychotest-mode-badge><?= $is_online ? 'Online' : 'Offline' ?></span>
            </div>

            <div class="daw-psychotest-settings__fields daw-psychotest-settings__fields--schedule">
                <label class="daw-psychotest-settings__field">
                    <span>Metode Psikotes</span>
                    <select name="global_settings[mode]" id="daw-psychotest-mode-select">
                        <option value="offline" <?= selected( $global_settings['mode'], 'offline', false ) ?>>Offline (Tatap Muka)</option>
                        <option value="online" <?= selected( $global_settings['mode'], 'online', false ) ?>>Online (Website &amp; Kamera)</option>
                    </select>
                </label>
                <label class="daw-psychotest-settings__field"><span>Tanggal Pelaksanaan</span><input type="date" name="global_settings[test_date]" value="<?= esc_attr( $global_settings['test_date'] ) ?>"></label>
                <label class="daw-psychotest-settings__field"><span data-closing-date-label><?= $is_online ? 'Tanggal Psikotes Ditutup' : 'Tanggal Akhir Psikotes' ?></span><input type="date" name="global_settings[closing_date]" value="<?= esc_attr( $global_settings['closing_date'] ) ?>"></label>
                <label class="daw-psychotest-settings__field" data-psychotest-closing-time data-online-only <?= ! $is_online ? 'style="display:none;"' : '' ?>><span>Jam Psikotes Ditutup</span><input type="time" name="global_settings[closing_time]" value="<?= esc_attr( $global_settings['closing_time'] ) ?>"></label>
                <label class="daw-psychotest-settings__field"><span>Waktu Mulai</span><input type="time" name="global_settings[start_time]" value="<?= esc_attr( $global_settings['start_time'] ) ?>"></label>
                <label class="daw-psychotest-settings__field"><span>Waktu Selesai</span><input type="time" name="global_settings[end_time]" value="<?= esc_attr( $global_settings['end_time'] ) ?>"></label>
                
                <!-- Offline-specific fields -->
                <label class="daw-psychotest-settings__field" data-offline-only <?= $is_online ? 'style="display:none;"' : '' ?>><span>Lokasi Ujian</span><input name="global_settings[location]" value="<?= esc_attr( $global_settings['location'] ) ?>"></label>
                <label class="daw-psychotest-settings__field" data-offline-only <?= $is_online ? 'style="display:none;"' : '' ?>><span>Alamat Lengkap</span><input name="global_settings[address]" value="<?= esc_attr( $global_settings['address'] ) ?>"></label>
                <label class="daw-psychotest-settings__field" data-offline-only <?= $is_online ? 'style="display:none;"' : '' ?>><span>Dress Code</span><input name="global_settings[dress_code]" value="<?= esc_attr( $global_settings['dress_code'] ) ?>"></label>
                <label class="daw-psychotest-settings__field" data-offline-only <?= $is_online ? 'style="display:none;"' : '' ?>><span>Maks. Peserta</span><input type="number" min="1" name="global_settings[max_participants]" value="<?= esc_attr( (string) $global_settings['max_participants'] ) ?>"></label>
                <label class="daw-psychotest-settings__switch daw-psychotest-settings__switch--inline"><span><strong>Perlu Konfirmasi Kehadiran</strong><small>Peserta harus konfirmasi.</small></span><input type="checkbox" name="global_settings[attendance_confirmation]" value="1" <?= checked( $global_settings['attendance_confirmation'], true, false ) ?>><span class="daw-psychotest-settings__switch-ui" aria-hidden="true"></span></label>
            </div>

            <label class="daw-psychotest-settings__field daw-psychotest-settings__deadline"><span>Batas Konfirmasi Kehadiran</span><input type="datetime-local" name="global_settings[confirmation_deadline]" value="<?= esc_attr( $global_settings['confirmation_deadline'] ) ?>"><small>Tanggal dan jam terakhir pelamar dapat mengonfirmasi hadir.</small></label>
            
            <fieldset class="daw-psychotest-settings__documents" data-offline-only <?= $is_online ? 'style="display:none;"' : '' ?>>
                <legend>Dokumen yang Wajib Dibawa (Khusus Offline)</legend>
                <?php foreach ( $document_labels as $document_key => $document_label ) : ?>
                    <label><input type="checkbox" name="global_settings[documents][]" value="<?= esc_attr( $document_key ) ?>" <?= checked( in_array( $document_key, $global_settings['documents'], true ), true, false ) ?>> <?= esc_html( $document_label ) ?></label>
                <?php endforeach; ?>
            </fieldset>

            <fieldset class="daw-psychotest-settings__documents daw-psychotest-settings__online-tests" data-online-only <?= ! $is_online ? 'style="display:none;"' : '' ?>>
                <legend>Modul Tes yang Diaktifkan untuk Psikotes Online</legend>
                <?php foreach ( [ 'iq' => 'Tes IQ (TIKI)', 'pauli' => 'Tes PAULI', 'disc' => 'Tes DISC (Perilaku)', 'papi' => 'Tes PAPI KOSTICK (Kepribadian Kerja)' ] as $test_key => $test_label ) : ?>
                    <label><input type="checkbox" name="global_settings[online_tests][]" value="<?= esc_attr( $test_key ) ?>" <?= checked( in_array( $test_key, $global_settings['online_tests'], true ), true, false ) ?>> <?= esc_html( $test_label ) ?></label>
                <?php endforeach; ?>
                <small>Hanya modul yang dicentang di sini yang akan muncul bagi peserta ujian online.</small>
            </fieldset>

            <label class="daw-psychotest-settings__field"><span>Informasi Tambahan untuk Peserta</span><textarea rows="3" name="global_settings[instructions]" placeholder="Instruksi atau informasi tambahan untuk peserta..."><?= esc_textarea( $global_settings['instructions'] ) ?></textarea></label>
            <div class="daw-psychotest-settings__actions"><button type="submit" class="button button-primary">Simpan Konfigurasi Jadwal</button></div>
        </form>
    </section>

    <!-- 2. Simplified Proctoring & Camera Card (Online Only) -->
    <section class="daw-psychotest-settings__panel daw-online-only-section" data-online-only <?= ! $is_online ? 'style="display:none;"' : '' ?> aria-labelledby="psychotest-proctoring-title">
        <div class="daw-psychotest-settings__panel-heading">
            <div>
                <h2 id="psychotest-proctoring-title">Pemantauan &amp; Pengawasan Kamera (Proctoring Online)</h2>
                <p>Pengaturan kamera wajib dan deteksi indikasi kecurangan selama pengerjaan tes psikotes online.</p>
            </div>
        </div>
        <form method="post" class="daw-psychotest-settings__form">
            <?php wp_nonce_field( 'recruitment_psychotest_settings' ); ?>
            <input type="hidden" name="psychotest_settings_action" value="save_proctoring">

            <div style="background:#f8fafc;border:1px solid #e2e8f0;border-radius:8px;padding:18px;margin-bottom:18px;">
                <label style="display:flex;align-items:center;justify-content:space-between;cursor:pointer;">
                    <div>
                        <strong style="font-size:15px;color:#0f172a;display:block;">Wajibkan Kamera Selama Ujian Online</strong>
                        <span style="font-size:12px;color:#64748b;">Peserta wajib mengaktifkan kamera sebelum dan selama mengerjakan soal tes psikotes.</span>
                    </div>
                    <input type="checkbox" name="camera_required_unified" value="1" <?= checked( ! empty( $global_settings['camera_required'] ), true, false ) ?> style="width:20px;height:20px;accent-color:#c8102e;">
                </label>
            </div>

            <div style="margin-bottom:20px;">
                <label style="display:block;font-size:13px;font-weight:700;color:#1e293b;margin-bottom:8px;">Tingkat Sensitivitas Pengawasan AI:</label>
                <div style="display:grid;grid-template-columns:repeat(auto-fit, minmax(220px, 1fr));gap:12px;">
                    <label style="border:2px solid <?= 'standard' === $current_preset ? '#c8102e' : '#e2e8f0' ?>;background:<?= 'standard' === $current_preset ? '#fff5f5' : '#fff' ?>;border-radius:8px;padding:14px;cursor:pointer;display:block;">
                        <input type="radio" name="sensitivity_preset" value="standard" <?= checked( $current_preset, 'standard', false ) ?> style="margin-right:6px;">
                        <strong style="font-size:13px;color:#0f172a;">🛡️ Standar (Disarankan)</strong>
                        <p style="margin:6px 0 0;font-size:11px;color:#64748b;line-height:1.4;">Toleransi wajar 4 detik sebelum dicatat sebagai pelanggaran. Cocok untuk koneksi umum tanpa banyak false alarm.</p>
                    </label>
                    <label style="border:2px solid <?= 'strict' === $current_preset ? '#c8102e' : '#e2e8f0' ?>;background:<?= 'strict' === $current_preset ? '#fff5f5' : '#fff' ?>;border-radius:8px;padding:14px;cursor:pointer;display:block;">
                        <input type="radio" name="sensitivity_preset" value="strict" <?= checked( $current_preset, 'strict', false ) ?> style="margin-right:6px;">
                        <strong style="font-size:13px;color:#0f172a;">🔒 Ketat (Tinggi)</strong>
                        <p style="margin:6px 0 0;font-size:11px;color:#64748b;line-height:1.4;">Toleransi cepat 2 detik, respon sensitif untuk pengawasan ketat terhadap gerakan kepala dan orang lain.</p>
                    </label>
                    <label style="border:2px solid <?= 'lenient' === $current_preset ? '#c8102e' : '#e2e8f0' ?>;background:<?= 'lenient' === $current_preset ? '#fff5f5' : '#fff' ?>;border-radius:8px;padding:14px;cursor:pointer;display:block;">
                        <input type="radio" name="sensitivity_preset" value="lenient" <?= checked( $current_preset, 'lenient', false ) ?> style="margin-right:6px;">
                        <strong style="font-size:13px;color:#0f172a;">🍃 Longgar (Rendah)</strong>
                        <p style="margin:6px 0 0;font-size:11px;color:#64748b;line-height:1.4;">Toleransi santai 7 detik, meminimalkan notifikasi saat peserta bergerak wajar atau berpindah posisi.</p>
                    </label>
                </div>
            </div>

            <div style="margin-bottom:20px;">
                <label style="display:block;font-size:13px;font-weight:700;color:#1e293b;margin-bottom:8px;">Jenis Deteksi yang Dipantau:</label>
                <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;background:#fff;border:1px solid #e2e8f0;border-radius:8px;padding:14px;">
                    <?php
                    $violation_labels = [
                        'FACE_NOT_DETECTED' => 'Wajah tidak terlihat di kamera',
                        'MULTIPLE_FACES'    => 'Terdeteksi lebih dari satu wajah',
                        'FACE_TURNED_AWAY'  => 'Wajah menoleh menjauhi layar komputer',
                        'CAMERA_STOPPED'    => 'Kamera terputus atau dinonaktifkan',
                    ];
                    foreach ( $violation_labels as $ev_k => $ev_title ) :
                        $is_checked = ! empty( $violation_config[ $ev_k ]['enabled'] );
                    ?>
                        <label style="display:flex;align-items:center;gap:8px;font-size:13px;color:#334155;cursor:pointer;">
                            <input type="checkbox" name="detections_enabled[<?= esc_attr( $ev_k ) ?>]" value="1" <?= checked( $is_checked, true, false ) ?> style="width:17px;height:17px;accent-color:#c8102e;">
                            <?= esc_html( $ev_title ) ?>
                        </label>
                    <?php endforeach; ?>
                </div>
            </div>

            <details style="background:#f9fafb;border:1px solid #e5e7eb;border-radius:6px;padding:10px 14px;margin-bottom:18px;">
                <summary style="font-size:12px;font-weight:600;color:#4b5563;cursor:pointer;">⚙️ Pengaturan Lanjutan / Parameter Teknis Detektor (Klik untuk melihat)</summary>
                <div style="margin-top:12px;overflow-x:auto;">
                    <table class="widefat striped" style="font-size:11px;">
                        <thead>
                            <tr>
                                <th>Deteksi</th>
                                <th>Konfirmasi (detik)</th>
                                <th>Grace (detik)</th>
                                <th>Confidence (%)</th>
                                <th>Sensitivitas Arah</th>
                                <th>Pesan Peringatan</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $violation_labels as $event_type => $event_label ) :
                                $rule = $violation_config[ $event_type ];
                            ?>
                                <tr>
                                    <td><strong><?= esc_html( $event_label ) ?></strong></td>
                                    <td><?= esc_html( number_format( $rule['threshold_ms'] / 1000, 1, '.', '' ) ) ?>s</td>
                                    <td><?= esc_html( number_format( $rule['grace_ms'] / 1000, 1, '.', '' ) ) ?>s</td>
                                    <td><?= esc_html( (string) round( $rule['confidence_threshold'] * 100 ) ) ?>%</td>
                                    <td><?= 'FACE_TURNED_AWAY' === $event_type ? esc_html( number_format( (float) $rule['signal_threshold'], 2, '.', '' ) ) : '—' ?></td>
                                    <td><?= esc_html( $rule['warning_message'] ) ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </details>

            <div class="daw-psychotest-settings__actions"><button type="submit" class="button button-primary">Simpan Pengaturan Pengawasan</button></div>
        </form>
    </section>

    <!-- 3. Test Modules & Bank Soal Configuration (Online Only) -->
    <section class="daw-psychotest-settings__test-layout daw-online-only-section" data-online-only <?= ! $is_online ? 'style="display:none;"' : '' ?> aria-label="Konfigurasi setiap tes">
        <nav class="daw-psychotest-settings__test-list" aria-label="Pilih jenis tes">
            <h2>Jenis Tes</h2>
            <?php foreach ( $test_order as $test_key ) : $test = $test_settings[ $test_key ]; ?>
                <a href="<?= esc_url( add_query_arg( [ 'page' => 'recruitment-psychotest-settings', 'test' => $test_key ], admin_url( 'admin.php' ) ) ) ?>" class="<?= $selected_test === $test_key ? 'is-active' : '' ?>" <?= $selected_test === $test_key ? 'aria-current="page"' : '' ?>>
                    <span class="daw-psychotest-settings__test-icon" aria-hidden="true"><?= esc_html( strtoupper( substr( $test['name'], 0, 2 ) ) ) ?></span>
                    <span>
                        <strong><?= esc_html( $test['name'] ) ?></strong>
                        <small><?= 'pauli' === $test_key ? ( (int) $pauli_config['duration_minutes'] . ' menit · ' . (int) $pauli_config['columns'] . ' kolom' ) : esc_html( (string) $test['duration'] ) . ' menit · ' . esc_html( (string) $test['question_count'] ) . ' soal' ?></small>
                    </span>
                    <i class="<?= $test['active'] ? 'is-active' : '' ?>" aria-label="<?= $test['active'] ? 'Aktif' : 'Nonaktif' ?>"></i>
                </a>
            <?php endforeach; ?>
        </nav>
        <section class="daw-psychotest-settings__panel daw-psychotest-settings__test-panel" aria-labelledby="psychotest-test-title">
            <div class="daw-psychotest-settings__panel-heading">
                <div>
                    <h2 id="psychotest-test-title"><?= esc_html( $selected_config['name'] ) ?></h2>
                    <p><?= 'pauli' === $selected_test ? 'Atur durasi kolom, jumlah angka, dan interval perpindahan tes Pauli.' : 'Atur durasi, jumlah soal, nilai lulus, dan perilaku tes.' ?></p>
                </div>
                <?php if ( 'pauli' !== $selected_test ) : ?>
                    <a class="button" href="<?= esc_url( add_query_arg( [ 'page' => 'recruitment-question-bank', 'test' => $selected_test ], admin_url( 'admin.php' ) ) ) ?>">Bank Soal</a>
                <?php endif; ?>
            </div>
            
            <form method="post" class="daw-psychotest-settings__form">
                <?php wp_nonce_field( 'recruitment_psychotest_settings' ); ?>
                <input type="hidden" name="psychotest_settings_action" value="save_test">
                <input type="hidden" name="test_key" value="<?= esc_attr( $selected_test ) ?>">

                <?php if ( 'pauli' === $selected_test ) : ?>
                    <!-- Pauli configuration card matching Image 1 -->
                    <div class="daw-pauli-config-box" style="background:#fff;border:1px solid #e2e8f0;border-radius:8px;padding:20px;margin-bottom:20px;">
                        <h3 style="margin:0 0 4px;font-size:16px;color:#1e293b;">Contoh konfigurasi tes Pauli</h3>
                        <p style="margin:0 0 18px;font-size:11px;color:#64748b;text-transform:uppercase;font-weight:700;letter-spacing:0.5px;">SIMULASI PENGATURAN ADMIN</p>

                        <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;margin-bottom:16px;">
                            <div>
                                <label style="display:block;font-size:13px;font-weight:600;color:#334155;margin-bottom:6px;">Durasi tes (menit)</label>
                                <select name="pauli_config[duration_minutes]" id="pauli-duration-select" style="width:100%;height:40px;border-radius:6px;border:1px solid #cbd5e1;padding:6px 12px;font-size:13px;">
                                    <?php foreach ( [ 15 => '15 menit', 30 => '30 menit', 45 => '45 menit', 60 => '60 menit' ] as $d_val => $d_lbl ) : ?>
                                        <option value="<?= esc_attr( $d_val ) ?>" <?= selected( (int) $pauli_config['duration_minutes'], $d_val, false ) ?>><?= esc_html( $d_lbl ) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label style="display:block;font-size:13px;font-weight:600;color:#334155;margin-bottom:6px;">Jumlah kolom</label>
                                <select name="pauli_config[columns]" id="pauli-columns-select" style="width:100%;height:40px;border-radius:6px;border:1px solid #cbd5e1;padding:6px 12px;font-size:13px;">
                                    <?php foreach ( [ 5 => '5 kolom', 10 => '10 kolom', 15 => '15 kolom', 20 => '20 kolom' ] as $c_val => $c_lbl ) : ?>
                                        <option value="<?= esc_attr( $c_val ) ?>" <?= selected( (int) $pauli_config['columns'], $c_val, false ) ?>><?= esc_html( $c_lbl ) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label style="display:block;font-size:13px;font-weight:600;color:#334155;margin-bottom:6px;">Jumlah angka per kolom</label>
                                <select name="pauli_config[numbers_per_column]" id="pauli-numbers-select" style="width:100%;height:40px;border-radius:6px;border:1px solid #cbd5e1;padding:6px 12px;font-size:13px;">
                                    <?php foreach ( [ 20 => '20 angka', 25 => '25 angka', 30 => '30 angka', 40 => '40 angka', 50 => '50 angka', 60 => '60 angka' ] as $n_val => $n_lbl ) : ?>
                                        <option value="<?= esc_attr( $n_val ) ?>" <?= selected( (int) $pauli_config['numbers_per_column'], $n_val, false ) ?>><?= esc_html( $n_lbl ) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div>
                                <label style="display:block;font-size:13px;font-weight:600;color:#334155;margin-bottom:6px;">Interval perpindahan (detik)</label>
                                <select name="pauli_config[interval_seconds]" id="pauli-interval-select" style="width:100%;height:40px;border-radius:6px;border:1px solid #cbd5e1;padding:6px 12px;font-size:13px;">
                                    <?php foreach ( [ 15 => '15 detik', 30 => '30 detik', 45 => '45 detik', 60 => '60 detik' ] as $i_val => $i_lbl ) : ?>
                                        <option value="<?= esc_attr( $i_val ) ?>" <?= selected( (int) $pauli_config['interval_seconds'], $i_val, false ) ?>><?= esc_html( $i_lbl ) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div style="margin-bottom:20px;">
                            <label style="display:flex;align-items:center;gap:8px;font-size:13px;font-weight:600;color:#1e293b;cursor:pointer;">
                                <input type="checkbox" name="pauli_config[randomize]" id="pauli-random-check" value="1" <?= checked( ! empty( $pauli_config['randomize'] ), true, false ) ?> style="width:18px;height:18px;border-radius:4px;accent-color:#c8102e;">
                                Randomisasi angka untuk setiap peserta
                            </label>
                        </div>

                        <div style="border-top:1px solid #e2e8f0;padding-top:16px;">
                            <h4 style="margin:0 0 12px;font-size:13px;color:#1e293b;font-weight:700;">Ringkasan konfigurasi</h4>
                            <div style="display:grid;grid-template-columns:1fr 1fr;gap:14px;background:#f8fafc;padding:14px;border-radius:8px;border:1px solid #e2e8f0;">
                                <div>
                                    <span style="font-size:11px;color:#64748b;display:block;">Durasi</span>
                                    <strong id="summary-durasi" style="font-size:16px;color:#0f172a;"><?= esc_html( (string) $pauli_config['duration_minutes'] ) ?> menit</strong>
                                </div>
                                <div>
                                    <span style="font-size:11px;color:#64748b;display:block;">Jumlah kolom</span>
                                    <strong id="summary-kolom" style="font-size:16px;color:#0f172a;"><?= esc_html( (string) $pauli_config['columns'] ) ?></strong>
                                </div>
                                <div>
                                    <span style="font-size:11px;color:#64748b;display:block;">Angka per kolom</span>
                                    <strong id="summary-angka" style="font-size:16px;color:#0f172a;"><?= esc_html( (string) $pauli_config['numbers_per_column'] ) ?></strong>
                                </div>
                                <div>
                                    <span style="font-size:11px;color:#64748b;display:block;">Interval</span>
                                    <strong id="summary-interval" style="font-size:16px;color:#0f172a;"><?= esc_html( (string) $pauli_config['interval_seconds'] ) ?> detik</strong>
                                </div>
                                <div style="grid-column: 1 / -1;border-top:1px dashed #cbd5e1;padding-top:8px;font-size:12px;color:#64748b;">
                                    Randomisasi: <strong id="summary-random" style="color:#0f172a;"><?= ! empty( $pauli_config['randomize'] ) ? 'Aktif' : 'Nonaktif' ?></strong>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php else : ?>
                    <div class="daw-psychotest-settings__fields daw-psychotest-settings__fields--test">
                        <label class="daw-psychotest-settings__field">
                            <span>Durasi (menit)</span>
                            <input type="number" min="1" name="test_settings[duration]" value="<?= esc_attr( (string) $selected_config['duration'] ) ?>" required>
                        </label>
                        <label class="daw-psychotest-settings__field">
                            <span>Jumlah Soal</span>
                            <input type="number" min="1" name="test_settings[question_count]" value="<?= esc_attr( (string) $selected_config['question_count'] ) ?>" required>
                        </label>
                        <label class="daw-psychotest-settings__field">
                            <span>Nilai Lulus (%)</span>
                            <input type="number" min="0" max="100" name="test_settings[passing_score]" value="<?= esc_attr( (string) $selected_config['passing_score'] ) ?>">
                        </label>
                    </div>
                <?php endif; ?>

                <div class="daw-psychotest-settings__switch-list">
                    <label class="daw-psychotest-settings__switch">
                        <span><strong>Wajib Kamera</strong><small>Kandidat wajib mengaktifkan kamera untuk tes ini.</small></span>
                        <input type="checkbox" name="test_settings[camera_required]" value="1" <?= checked( $selected_config['camera_required'] ?? $global_settings['camera_required'], true, false ) ?>>
                        <span class="daw-psychotest-settings__switch-ui" aria-hidden="true"></span>
                    </label>
                    <label class="daw-psychotest-settings__switch">
                        <span><strong>Aktifkan Tes</strong><small>Tampilkan tes ini pada rangkaian psikotes.</small></span>
                        <input type="checkbox" name="test_settings[active]" value="1" <?= checked( $selected_config['active'], true, false ) ?>>
                        <span class="daw-psychotest-settings__switch-ui" aria-hidden="true"></span>
                    </label>
                </div>

                <div class="daw-psychotest-settings__preview">
                    <h3>Pratinjau Konfigurasi</h3>
                    <dl>
                        <div>
                            <dt>Durasi</dt>
                            <dd><?= esc_html( (string) ( 'pauli' === $selected_test ? $pauli_config['duration_minutes'] : $selected_config['duration'] ) ) ?> menit</dd>
                        </div>
                        <div>
                            <dt><?= 'pauli' === $selected_test ? 'Kolom' : 'Soal' ?></dt>
                            <dd><?= esc_html( (string) ( 'pauli' === $selected_test ? $pauli_config['columns'] . ' kolom' : $selected_config['question_count'] . ' soal' ) ) ?></dd>
                        </div>
                        <?php if ( 'pauli' !== $selected_test ) : ?>
                            <div>
                                <dt>Nilai Lulus</dt>
                                <dd><?= esc_html( (string) $selected_config['passing_score'] ) ?>%</dd>
                            </div>
                        <?php endif; ?>
                        <div>
                            <dt>Kamera</dt>
                            <dd><?= ! empty( $selected_config['camera_required'] ?? $global_settings['camera_required'] ) ? 'Wajib' : 'Tidak wajib' ?></dd>
                        </div>
                        <div>
                            <dt>Status</dt>
                            <dd><?= $selected_config['active'] ? 'Aktif' : 'Nonaktif' ?></dd>
                        </div>
                    </dl>
                </div>

                <div class="daw-psychotest-settings__actions">
                    <button type="submit" class="button button-primary">Simpan Konfigurasi Tes</button>
                </div>
            </form>
        </section>
    </section>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Mode Switcher (Online vs Offline)
    const modeSelect = document.getElementById('daw-psychotest-mode-select');
    if (modeSelect) {
        function syncMode() {
            const mode = modeSelect.value;
            const isOnline = mode === 'online';
            document.querySelectorAll('[data-online-only]').forEach(function(el) {
                el.style.display = isOnline ? '' : 'none';
            });
            document.querySelectorAll('[data-offline-only]').forEach(function(el) {
                el.style.display = isOnline ? 'none' : '';
            });
            const badge = document.querySelector('[data-psychotest-mode-badge]');
            if (badge) {
                badge.textContent = isOnline ? 'Online' : 'Offline';
                badge.className = 'daw-psychotest-settings__badge daw-psychotest-settings__mode-badge daw-psychotest-settings__badge--' + mode;
            }
            const desc = document.querySelector('[data-psychotest-mode-description]');
            if (desc) {
                desc.textContent = isOnline ? 'Atur jadwal, tenggat penutupan, dan informasi pelaksanaan online.' : 'Atur rentang tanggal dan detail pelaksanaan psikotes secara langsung.';
            }
            const head = document.querySelector('[data-psychotest-mode-heading]');
            if (head) {
                head.textContent = 'Jadwal / Informasi Psikotes ' + (isOnline ? 'Online' : 'Offline');
            }
        }
        modeSelect.addEventListener('change', syncMode);
        syncMode();
    }

    // Pauli Summary Card live updater
    const pauliDur = document.getElementById('pauli-duration-select');
    const pauliCol = document.getElementById('pauli-columns-select');
    const pauliNum = document.getElementById('pauli-numbers-select');
    const pauliInt = document.getElementById('pauli-interval-select');
    const pauliRnd = document.getElementById('pauli-random-check');

    if (pauliDur && pauliCol && pauliNum && pauliInt && pauliRnd) {
        function updatePauliSummary() {
            const sumDur = document.getElementById('summary-durasi');
            const sumCol = document.getElementById('summary-kolom');
            const sumNum = document.getElementById('summary-angka');
            const sumInt = document.getElementById('summary-interval');
            const sumRnd = document.getElementById('summary-random');

            if (sumDur) sumDur.textContent = pauliDur.value + ' menit';
            if (sumCol) sumCol.textContent = pauliCol.value;
            if (sumNum) sumNum.textContent = pauliNum.value;
            if (sumInt) sumInt.textContent = pauliInt.value + ' detik';
            if (sumRnd) sumRnd.textContent = pauliRnd.checked ? 'Aktif' : 'Nonaktif';
        }

        pauliDur.addEventListener('change', updatePauliSummary);
        pauliCol.addEventListener('change', updatePauliSummary);
        pauliNum.addEventListener('change', updatePauliSummary);
        pauliInt.addEventListener('change', updatePauliSummary);
        pauliRnd.addEventListener('change', updatePauliSummary);
    }
});
</script>
