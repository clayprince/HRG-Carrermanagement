<?php
$user = wp_get_current_user();
$preview = recruitment_is_ui_preview();
$profile_name = $preview ? 'Aiko Rahayu' : (string) $user->display_name;
$profile_email = $preview ? 'aiko.rahayu@email.com' : (string) $user->user_email;
$sample_profile = [
    'gender' => 'Perempuan',
    'birth_place' => 'Manado',
    'birth_date' => '1999-04-18',
    'phone' => '0812 3456 7890',
    'address' => 'Jl. Sam Ratulangi No. 24',
    'city' => 'Manado',
    'province' => 'Sulawesi Utara',
    'education_level' => 'S1',
    'institution' => 'Universitas Sam Ratulangi',
    'major' => 'Manajemen',
    'graduation_year' => '2022',
    'gpa' => '3.62',
    'certificate' => 'Digital Sales Fundamentals',
    'company' => 'PT Maju Bersama',
    'position' => 'Sales Associate',
    'work_period' => 'Januari 2023 – Juli 2025',
];
$profile_saved = '1' === sanitize_text_field( wp_unslash( $_GET['profile_saved'] ?? '' ) );
$error_messages = [
    'invalid_request' => 'Sesi tidak valid. Muat ulang halaman lalu coba kembali.',
    'profile_update_failed' => 'Profil belum dapat disimpan. Silakan coba kembali.',
];
$error = $error_messages[ sanitize_key( wp_unslash( $_GET['account_error'] ?? '' ) ) ] ?? '';
$value = static function ( string $field, string $fallback = '' ) use ( $preview, $sample_profile ): string {
    if ( $preview && isset( $sample_profile[ $field ] ) ) {
        return $sample_profile[ $field ];
    }
    $stored = get_user_meta( get_current_user_id(), 'daw_applicant_' . $field, true );
    return is_scalar( $stored ) && '' !== (string) $stored ? (string) $stored : $fallback;
};
?>
<div class="daw-recruitment daw-account">
    <?php require recruitment_get_plugin_path( 'public/components/header.php' ); ?>
    <main class="daw-account__main">
        <header class="daw-account__heading"><div><span class="daw-recruitment__eyebrow">AKUN PELAMAR</span><h1>Edit Profil Pelamar</h1><p>Kelola informasi yang digunakan untuk lamaran berikutnya.</p></div><a class="daw-recruitment__button" href="#profile-form">Edit Profil</a></header>
        <?php require recruitment_get_plugin_path( 'public/components/applicant-account-nav.php' ); ?>
        <?php if ( $profile_saved ) : ?><div class="daw-account__success" role="status">Profil berhasil disimpan.</div><?php endif; ?>
        <?php if ( $error ) : ?><div class="daw-account__alert" role="alert"><?= esc_html( $error ) ?></div><?php endif; ?>
        <?php if ( $preview ) : ?>
            <div class="daw-account__preview-note">Mode preview: data contoh ditampilkan dan perubahan profil tidak disimpan.</div>
        <?php else : ?>
            <div class="daw-account__notice">Perubahan profil hanya berlaku untuk lamaran yang dibuat setelah perubahan. Data dan riwayat aplikasi akan tersambung setelah integrasi Oracle berikutnya.</div>
        <?php endif; ?>
        <form id="profile-form" class="daw-account__form" method="post" action="<?= esc_url( recruitment_get_public_url( 'account-profile' ) ) ?>">
            <?php wp_nonce_field( 'applicant_account_account-profile', 'applicant_account_nonce' ); ?>
            <?php if ( $preview ) : ?><input type="hidden" name="daw_ui_preview" value="1"><?php endif; ?>
            <section class="daw-account__card daw-account__identity"><div class="daw-account__avatar" aria-hidden="true"><?= esc_html( strtoupper( substr( $profile_name ?: 'P', 0, 1 ) ) ) ?></div><div><strong><?= esc_html( $profile_name ) ?></strong><span><?= esc_html( $profile_email ) ?></span><small>Akun pelamar DAW</small></div></section>
            <section class="daw-account__card"><h2>Profil Peserta</h2><div class="daw-account__fields">
                <label>Nama lengkap<input name="full_name" value="<?= esc_attr( $profile_name ) ?>" required></label>
                <label>Email<input value="<?= esc_attr( $profile_email ) ?>" type="email" disabled><small>Email akun tidak dapat diubah di halaman ini.</small></label>
                <label>Jenis kelamin<select name="gender"><option value="">Pilih</option><option value="Perempuan" <?= selected( $value( 'gender' ), 'Perempuan', false ) ?>>Perempuan</option><option value="Laki-laki" <?= selected( $value( 'gender' ), 'Laki-laki', false ) ?>>Laki-laki</option></select></label>
                <label>Tempat lahir<input name="birth_place" value="<?= esc_attr( $value( 'birth_place' ) ) ?>"></label>
                <label>Tanggal lahir<input type="date" name="birth_date" value="<?= esc_attr( $value( 'birth_date' ) ) ?>"></label>
                <label>Nomor telepon<input type="tel" name="phone" value="<?= esc_attr( $value( 'phone' ) ) ?>"></label>
                <label class="daw-account__field--full">Alamat<textarea name="address" rows="2"><?= esc_textarea( $value( 'address' ) ) ?></textarea></label>
                <label>Kota<input name="city" value="<?= esc_attr( $value( 'city' ) ) ?>"></label>
                <label>Provinsi<input name="province" value="<?= esc_attr( $value( 'province' ) ) ?>"></label>
            </div></section>
            <section class="daw-account__card"><h2>Pendidikan</h2><div class="daw-account__fields">
                <label>Jenjang pendidikan<input name="education_level" value="<?= esc_attr( $value( 'education_level' ) ) ?>"></label><label>Institusi<input name="institution" value="<?= esc_attr( $value( 'institution' ) ) ?>"></label>
                <label>Jurusan<input name="major" value="<?= esc_attr( $value( 'major' ) ) ?>"></label><label>Tahun lulus<input name="graduation_year" value="<?= esc_attr( $value( 'graduation_year' ) ) ?>"></label>
                <label>IPK<input name="gpa" value="<?= esc_attr( $value( 'gpa' ) ) ?>"></label><label>Sertifikasi / pelatihan<input name="certificate" value="<?= esc_attr( $value( 'certificate' ) ) ?>"></label>
            </div></section>
            <section class="daw-account__card"><h2>Pengalaman Kerja</h2><div class="daw-account__fields">
                <label>Nama perusahaan<input name="company" value="<?= esc_attr( $value( 'company' ) ) ?>"></label><label>Posisi<input name="position" value="<?= esc_attr( $value( 'position' ) ) ?>"></label><label>Periode kerja<input name="work_period" value="<?= esc_attr( $value( 'work_period' ) ) ?>"></label>
            </div></section>
            <section class="daw-account__card"><h2>Dokumen</h2><p class="daw-account__muted">Pengelolaan dan unggah dokumen akan tersedia setelah integrasi data aplikasi selesai.</p></section>
            <div class="daw-account__actions"><button class="daw-recruitment__button" type="submit" <?= $preview ? 'disabled' : '' ?>><?= $preview ? 'Preview - Penyimpanan Nonaktif' : 'Simpan Profil' ?></button></div>
        </form>
    </main>
    <?php require recruitment_get_plugin_path( 'public/components/footer.php' ); ?>
</div>
