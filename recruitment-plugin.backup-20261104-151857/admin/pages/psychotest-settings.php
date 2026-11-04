<?php
$global_defaults = [
    'camera_required' => true,
    'shuffle_questions' => true,
    'status' => 'active',
    'mode' => 'offline',
    'test_date' => '2026-09-21',
    'start_time' => '08:00',
    'end_time' => '11:00',
    'location' => 'PT Daya Adicipta Wisesa',
    'address' => 'Jl. A.A. Maramis, Sulawesi Utara',
    'contact_person' => 'HR Recruitment DAW',
    'dress_code' => 'Formal / Smart Casual',
    'max_participants' => 30,
    'attendance_confirmation' => true,
    'documents' => [ 'ktp', 'writing_tools' ],
    'instructions' => '',
];
$test_defaults = [
    'iq' => [ 'name' => 'IQ', 'duration' => 20, 'question_count' => 10, 'passing_score' => 70, 'active' => true, 'shuffle' => true ],
    'pauli' => [ 'name' => 'PAULI', 'duration' => 15, 'question_count' => 40, 'passing_score' => 0, 'active' => true, 'shuffle' => false ],
    'personality' => [ 'name' => 'KEPRIBADIAN', 'duration' => 15, 'question_count' => 30, 'passing_score' => 0, 'active' => true, 'shuffle' => true ],
    'disc' => [ 'name' => 'DISC', 'duration' => 10, 'question_count' => 5, 'passing_score' => 0, 'active' => true, 'shuffle' => true ],
    'papi' => [ 'name' => 'PAPI KOSTICK', 'duration' => 10, 'question_count' => 8, 'passing_score' => 0, 'active' => true, 'shuffle' => true ],
];
$global_settings = wp_parse_args( (array) get_option( 'recruitment_psychotest_settings', [] ), $global_defaults );
$test_settings = (array) get_option( 'recruitment_psychotest_tests', [] );
foreach ( $test_defaults as $test_key => $defaults ) {
    $test_settings[ $test_key ] = wp_parse_args( (array) ( $test_settings[ $test_key ] ?? [] ), $defaults );
}
$selected_test = sanitize_key( $_GET['test'] ?? 'iq' );
if ( ! isset( $test_settings[ $selected_test ] ) ) {
    $selected_test = 'iq';
}
$notice = '';
$admin_user = wp_get_current_user();
$logout_url = wp_logout_url( home_url( '/' ) );

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
            'start_time' => sanitize_text_field( $posted['start_time'] ?? '' ),
            'end_time' => sanitize_text_field( $posted['end_time'] ?? '' ),
            'location' => sanitize_text_field( $posted['location'] ?? '' ),
            'address' => sanitize_text_field( $posted['address'] ?? '' ),
            'contact_person' => sanitize_text_field( $posted['contact_person'] ?? '' ),
            'dress_code' => sanitize_text_field( $posted['dress_code'] ?? '' ),
            'max_participants' => max( 1, absint( $posted['max_participants'] ?? 1 ) ),
            'attendance_confirmation' => ! empty( $posted['attendance_confirmation'] ),
            'documents' => array_values( array_intersect( $documents, [ 'ktp', 'writing_tools', 'application_letter', 'other' ] ) ),
            'instructions' => sanitize_textarea_field( $posted['instructions'] ?? '' ),
        ];
        update_option( 'recruitment_psychotest_settings', $global_settings );
        $notice = 'Konfigurasi global berhasil disimpan.';
    } elseif ( 'save_test' === $action ) {
        $test_key = sanitize_key( $_POST['test_key'] ?? '' );
        if ( isset( $test_settings[ $test_key ] ) ) {
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
            $notice = 'Konfigurasi tes berhasil disimpan.';
        }
    }
}

$selected_config = $test_settings[ $selected_test ];
$document_labels = [ 'ktp' => 'KTP', 'writing_tools' => 'Alat Tulis', 'application_letter' => 'Surat Lamaran', 'other' => 'Dokumen lainnya' ];
$test_order = [ 'iq', 'pauli', 'personality', 'disc', 'papi' ];
?>
<div class="wrap daw-psychotest-settings" data-psychotest-settings>
    <header class="daw-psychotest-settings__header">
        <p class="daw-psychotest-settings__breadcrumb">DAW Admin <span>/</span> Setting Psikotes</p>
        <div class="daw-psychotest-settings__heading"><div><h1>Setting Psikotes</h1><p>Kelola jadwal, persyaratan, dan konfigurasi setiap tes.</p></div><div class="daw-psychotest-settings__header-account"><span class="daw-psychotest-settings__avatar" aria-hidden="true">HR</span><span><strong><?= esc_html( $admin_user->display_name ) ?></strong><small><?= esc_html( $admin_user->user_email ) ?></small></span><a href="<?= esc_url( $logout_url ) ?>">Keluar</a></div></div>
    </header>
    <?php if ( $notice ) : ?><div class="notice notice-success is-dismissible"><p><?= esc_html( $notice ) ?></p></div><?php endif; ?>

    <section class="daw-psychotest-settings__panel" aria-labelledby="psychotest-global-title">
        <div class="daw-psychotest-settings__panel-heading"><div><h2 id="psychotest-global-title">Konfigurasi Global</h2><p>Ketentuan umum yang digunakan untuk sesi psikotes.</p></div><span class="daw-psychotest-settings__badge daw-psychotest-settings__badge--<?= esc_attr( $global_settings['status'] ) ?>"><?= 'active' === $global_settings['status'] ? 'Aktif' : 'Nonaktif' ?></span></div>
        <form method="post" class="daw-psychotest-settings__form">
            <?php wp_nonce_field( 'recruitment_psychotest_settings' ); ?>
            <input type="hidden" name="psychotest_settings_action" value="save_global">
            <div class="daw-psychotest-settings__global-row">
                <label class="daw-psychotest-settings__switch"><span><strong>Kamera Wajib (Global)</strong><small>Peserta wajib menggunakan kamera selama tes.</small></span><input type="checkbox" name="global_settings[camera_required]" value="1" <?= checked( $global_settings['camera_required'], true, false ) ?>><span class="daw-psychotest-settings__switch-ui" aria-hidden="true"></span></label>
                <label class="daw-psychotest-settings__switch"><span><strong>Acak Soal</strong><small>Urutan soal diacak per sesi.</small></span><input type="checkbox" name="global_settings[shuffle_questions]" value="1" <?= checked( $global_settings['shuffle_questions'], true, false ) ?>><span class="daw-psychotest-settings__switch-ui" aria-hidden="true"></span></label>
                <label class="daw-psychotest-settings__field"><span>Status Tes</span><select name="global_settings[status]"><option value="active" <?= selected( $global_settings['status'], 'active', false ) ?>>Aktif</option><option value="inactive" <?= selected( $global_settings['status'], 'inactive', false ) ?>>Nonaktif</option></select></label>
            </div>

            <div class="daw-psychotest-settings__section-heading"><div><h3>Jadwal / Informasi Psikotes Offline</h3><p>Konfigurasi jadwal dan detail pelaksanaan secara langsung (offline).</p></div><span class="daw-psychotest-settings__badge daw-psychotest-settings__badge--offline">Offline</span></div>
            <div class="daw-psychotest-settings__fields daw-psychotest-settings__fields--schedule">
                <label class="daw-psychotest-settings__field"><span>Metode Psikotes</span><select name="global_settings[mode]"><option value="offline" <?= selected( $global_settings['mode'], 'offline', false ) ?>>Offline</option><option value="online" <?= selected( $global_settings['mode'], 'online', false ) ?>>Online</option></select></label>
                <label class="daw-psychotest-settings__field"><span>Tanggal</span><input type="date" name="global_settings[test_date]" value="<?= esc_attr( $global_settings['test_date'] ) ?>"></label>
                <label class="daw-psychotest-settings__field"><span>Waktu Mulai</span><input type="time" name="global_settings[start_time]" value="<?= esc_attr( $global_settings['start_time'] ) ?>"></label>
                <label class="daw-psychotest-settings__field"><span>Waktu Selesai</span><input type="time" name="global_settings[end_time]" value="<?= esc_attr( $global_settings['end_time'] ) ?>"></label>
                <label class="daw-psychotest-settings__field"><span>Lokasi</span><input name="global_settings[location]" value="<?= esc_attr( $global_settings['location'] ) ?>"></label>
                <label class="daw-psychotest-settings__field"><span>Alamat Lengkap</span><input name="global_settings[address]" value="<?= esc_attr( $global_settings['address'] ) ?>"></label>
                <label class="daw-psychotest-settings__field"><span>Dress Code</span><input name="global_settings[dress_code]" value="<?= esc_attr( $global_settings['dress_code'] ) ?>"></label>
                <label class="daw-psychotest-settings__field"><span>Maks. Peserta</span><input type="number" min="1" name="global_settings[max_participants]" value="<?= esc_attr( (string) $global_settings['max_participants'] ) ?>"></label>
                <label class="daw-psychotest-settings__switch daw-psychotest-settings__switch--inline"><span><strong>Perlu Konfirmasi Kehadiran</strong><small>Peserta harus konfirmasi.</small></span><input type="checkbox" name="global_settings[attendance_confirmation]" value="1" <?= checked( $global_settings['attendance_confirmation'], true, false ) ?>><span class="daw-psychotest-settings__switch-ui" aria-hidden="true"></span></label>
            </div>
            <fieldset class="daw-psychotest-settings__documents"><legend>Dokumen yang Dibawa</legend><?php foreach ( $document_labels as $document_key => $document_label ) : ?><label><input type="checkbox" name="global_settings[documents][]" value="<?= esc_attr( $document_key ) ?>" <?= checked( in_array( $document_key, $global_settings['documents'], true ), true, false ) ?>> <?= esc_html( $document_label ) ?></label><?php endforeach; ?></fieldset>
            <label class="daw-psychotest-settings__field"><span>Informasi Tambahan</span><textarea rows="3" name="global_settings[instructions]" placeholder="Instruksi atau informasi tambahan untuk peserta..."><?= esc_textarea( $global_settings['instructions'] ) ?></textarea></label>
            <div class="daw-psychotest-settings__actions"><button type="submit" class="button button-primary">Simpan Konfigurasi Global</button></div>
        </form>
    </section>

    <section class="daw-psychotest-settings__test-layout" aria-label="Konfigurasi setiap tes">
        <nav class="daw-psychotest-settings__test-list" aria-label="Pilih jenis tes">
            <h2>Jenis Tes</h2>
            <?php foreach ( $test_order as $test_key ) : $test = $test_settings[ $test_key ]; ?>
                <a href="<?= esc_url( add_query_arg( [ 'page' => 'recruitment-psychotest-settings', 'test' => $test_key ], admin_url( 'admin.php' ) ) ) ?>" class="<?= $selected_test === $test_key ? 'is-active' : '' ?>" <?= $selected_test === $test_key ? 'aria-current="page"' : '' ?>><span class="daw-psychotest-settings__test-icon" aria-hidden="true"><?= esc_html( strtoupper( substr( $test['name'], 0, 2 ) ) ) ?></span><span><strong><?= esc_html( $test['name'] ) ?></strong><small><?= esc_html( (string) $test['duration'] ) ?> menit · <?= esc_html( (string) $test['question_count'] ) ?> soal</small></span><i class="<?= $test['active'] ? 'is-active' : '' ?>" aria-label="<?= $test['active'] ? 'Aktif' : 'Nonaktif' ?>"></i></a>
            <?php endforeach; ?>
        </nav>
        <section class="daw-psychotest-settings__panel daw-psychotest-settings__test-panel" aria-labelledby="psychotest-test-title">
            <div class="daw-psychotest-settings__panel-heading"><div><h2 id="psychotest-test-title"><?= esc_html( $selected_config['name'] ) ?></h2><p>Atur durasi, jumlah soal, nilai lulus, dan perilaku tes.</p></div><a class="button" href="<?= esc_url( add_query_arg( [ 'page' => 'recruitment-question-bank', 'test' => $selected_test ], admin_url( 'admin.php' ) ) ) ?>">Bank Soal</a></div>
            <form method="post" class="daw-psychotest-settings__form">
                <?php wp_nonce_field( 'recruitment_psychotest_settings' ); ?>
                <input type="hidden" name="psychotest_settings_action" value="save_test"><input type="hidden" name="test_key" value="<?= esc_attr( $selected_test ) ?>">
                <div class="daw-psychotest-settings__fields daw-psychotest-settings__fields--test">
                    <label class="daw-psychotest-settings__field"><span>Durasi (menit)</span><input type="number" min="1" name="test_settings[duration]" value="<?= esc_attr( (string) $selected_config['duration'] ) ?>" required></label>
                    <label class="daw-psychotest-settings__field"><span>Jumlah Soal</span><input type="number" min="1" name="test_settings[question_count]" value="<?= esc_attr( (string) $selected_config['question_count'] ) ?>" required></label>
                    <label class="daw-psychotest-settings__field"><span>Nilai Lulus (%)</span><input type="number" min="0" max="100" name="test_settings[passing_score]" value="<?= esc_attr( (string) $selected_config['passing_score'] ) ?>"></label>
                </div>
                <div class="daw-psychotest-settings__switch-list">
                    <label class="daw-psychotest-settings__switch"><span><strong>Wajib Kamera</strong><small>Kandidat wajib mengaktifkan kamera untuk tes ini.</small></span><input type="checkbox" name="test_settings[camera_required]" value="1" <?= checked( $selected_config['camera_required'] ?? $global_settings['camera_required'], true, false ) ?>><span class="daw-psychotest-settings__switch-ui" aria-hidden="true"></span></label>
                    <label class="daw-psychotest-settings__switch"><span><strong>Aktifkan Tes</strong><small>Tampilkan tes ini pada rangkaian psikotes.</small></span><input type="checkbox" name="test_settings[active]" value="1" <?= checked( $selected_config['active'], true, false ) ?>><span class="daw-psychotest-settings__switch-ui" aria-hidden="true"></span></label>
                </div>
                <div class="daw-psychotest-settings__preview"><h3>Pratinjau Konfigurasi</h3><dl><div><dt>Durasi</dt><dd><?= esc_html( (string) $selected_config['duration'] ) ?> menit</dd></div><div><dt>Soal</dt><dd><?= esc_html( (string) $selected_config['question_count'] ) ?> soal</dd></div><div><dt>Nilai Lulus</dt><dd><?= esc_html( (string) $selected_config['passing_score'] ) ?>%</dd></div><div><dt>Kamera</dt><dd><?= ! empty( $selected_config['camera_required'] ?? $global_settings['camera_required'] ) ? 'Wajib' : 'Tidak wajib' ?></dd></div><div><dt>Status</dt><dd><?= $selected_config['active'] ? 'Aktif' : 'Nonaktif' ?></dd></div></dl></div>
                <div class="daw-psychotest-settings__actions"><button type="submit" class="button button-primary">Simpan Konfigurasi Tes</button></div>
            </form>
        </section>
    </section>
</div>
