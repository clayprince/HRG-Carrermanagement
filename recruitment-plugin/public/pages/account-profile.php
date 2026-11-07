<?php
$preview = recruitment_is_ui_preview();
$applicant = $preview ? [
    'full_name' => 'Preview Pelamar',
    'email' => 'preview@example.invalid',
    'gender' => '',
    'birth_place' => '',
    'birth_date' => '',
    'phone' => '',
    'address' => '',
    'profile_details' => [],
    'status' => 'ACTIVE',
] : recruitment_get_current_applicant();
$editing = '1' === sanitize_text_field( wp_unslash( $_GET['edit'] ?? '' ) );
$profile_saved = '1' === sanitize_text_field( wp_unslash( $_GET['profile_saved'] ?? '' ) );
$profile_details_error = ! $preview && ! empty( $applicant['profile_details_error'] );
$notice = sanitize_key( wp_unslash( $_GET['account_error'] ?? '' ) );
$messages = [
    'invalid_request' => 'Sesi tidak valid. Muat ulang halaman lalu coba kembali.',
    'profile_update_failed' => 'Profil belum dapat disimpan. Periksa input dan coba kembali.',
    'invalid_profile' => 'Lengkapi semua data profil yang wajib diisi dan periksa kembali nilai yang dimasukkan.',
    'invalid_personal_data' => 'Periksa kembali data pribadi: nama, email, gender, tempat dan tanggal lahir, nomor telepon, serta alamat.',
    'invalid_education_data' => 'Periksa kembali data pendidikan, IPK (0-4), tahun lulus, status pernikahan, dan minat/bakat.',
    'invalid_work_experiences' => 'Lengkapi semua kolom pengalaman kerja yang sudah mulai diisi, atau hapus baris pengalaman yang kosong.',
    'invalid_family_members' => 'Lengkapi semua kolom anggota keluarga yang sudah mulai diisi, atau hapus baris yang kosong.',
    'invalid_application_answers' => 'Periksa kembali jawaban administrasi lamaran. Pastikan setiap isian berupa teks yang valid dan tidak lebih dari 10.000 karakter.',
    'invalid_upload' => 'Foto dan CV wajib tersedia. Periksa jenis file, batas 5 MB per file, dan batas total unggahan 6 MB.',
    'upload_storage_failed' => 'Dokumen belum dapat disimpan dengan aman. Silakan coba kembali.',
    'email_exists' => 'Email tersebut sudah digunakan akun lain.',
    'password_updated' => 'Password berhasil diperbarui.',
    'password_mismatch' => 'Password baru dan konfirmasi tidak sama atau tidak memenuhi syarat minimal 10 karakter.',
    'current_password' => 'Password saat ini tidak sesuai.',
    'applicant_account_required' => 'Silakan masuk kembali ke akun pelamar.',
];
$profile_details = is_array( $applicant['profile_details'] ?? null ) ? $applicant['profile_details'] : [];
$profile_documents = is_array( $profile_details['documents'] ?? null ) ? $profile_details['documents'] : [];
$profile_photo_url = ! empty( $profile_documents['profile_photo'] )
    ? recruitment_get_public_url( 'applicant-file', [ 'document' => 'profile_photo' ] )
    : ( $preview ? recruitment_get_plugin_url( 'assets/images/profile-preview.svg' ) : '' );
$profile_values = [
    $applicant['full_name'] ?? '',
    $applicant['email'] ?? '',
    $applicant['gender'] ?? '',
    $applicant['birth_place'] ?? '',
    $applicant['birth_date'] ?? '',
    $applicant['phone'] ?? '',
    $applicant['address'] ?? '',
    $profile_details['education_level'] ?? '',
    $profile_details['institution'] ?? '',
    $profile_details['major'] ?? '',
    $profile_details['gpa'] ?? '',
    $profile_details['graduation_year'] ?? '',
    $profile_details['marital_status'] ?? '',
    $profile_details['interests'] ?? '',
    $profile_documents['profile_photo']['key'] ?? '',
    $profile_documents['resume']['key'] ?? '',
];
$profile_complete = count( array_filter( $profile_values, static fn ( $value ): bool => '' !== trim( (string) $value ) ) );
$profile_percentage = (int) round( 100 * $profile_complete / count( $profile_values ) );
$name_parts = preg_split( '/\s+/', trim( (string) ( $applicant['full_name'] ?? '' ) ), -1, PREG_SPLIT_NO_EMPTY );
$avatar_initials = '';
foreach ( array_slice( $name_parts ?: [], 0, 2 ) as $name_part ) {
    $avatar_initials .= strtoupper( substr( $name_part, 0, 1 ) );
}
$profile_applications = [];
$profile_applications_error = false;
if ( ! $preview && ! $editing ) {
    $database = new Recruitment_Database();
    $profile_applications = $database->get_applicant_applications( (string) $applicant['id'] );
    $profile_applications_error = $database->has_error();
}
?>
<div class="daw-recruitment daw-account">
    <?php require recruitment_get_plugin_path( 'public/components/header.php' ); ?>
    <main class="daw-account__main daw-account__main--profile">
        <header class="daw-account__heading daw-profile__heading">
            <div><span class="daw-recruitment__eyebrow">AKUN PELAMAR</span><h1><?= $editing ? 'Edit Profil Pelamar' : 'Profil Pelamar' ?></h1><p><?= $editing ? 'Perbarui data yang digunakan untuk lamaran berikutnya.' : 'Pastikan data diri dan dokumen Anda selalu lengkap.' ?></p></div>
            <?php if ( $editing ) : ?>
                <a class="daw-recruitment__button daw-recruitment__button--muted daw-profile__edit-link" href="<?= esc_url( recruitment_get_public_url( 'account-profile' ) ) ?>">Kembali ke Profil</a>
            <?php else : ?>
                <a class="daw-recruitment__button daw-profile__edit-link" href="<?= esc_url( recruitment_get_public_url( 'account-profile', [ 'edit' => '1' ] ) ) ?>">Edit Profil</a>
            <?php endif; ?>
        </header>

        <?php if ( $preview ) : ?><div class="daw-account__preview-note">Mode preview: data tidak diambil atau disimpan ke Oracle.</div><?php endif; ?>
        <?php if ( $profile_saved ) : ?><div class="daw-account__success" role="status">Profil berhasil disimpan.</div><?php endif; ?>
        <?php if ( isset( $messages[ $notice ] ) ) : ?><div class="daw-account__alert" role="alert"><?= esc_html( $messages[ $notice ] ) ?></div><?php endif; ?>
        <?php if ( $profile_details_error ) : ?><div class="daw-account__alert" role="alert">Data profil lengkap belum tersedia. Hubungi pengelola agar migrasi database profil pelamar diterapkan sebelum mengubah profil.</div><?php endif; ?>

        <?php if ( $editing ) : ?>
            <div class="daw-profile__notice" role="note"><span aria-hidden="true">ⓘ</span><p>Perubahan profil berlaku untuk penggunaan berikutnya dan tidak mengubah snapshot data pada lamaran yang sudah dikirim.</p></div>
            <section class="daw-account__card daw-account__identity daw-profile__identity">
                <?php if ( ! empty( $profile_documents['profile_photo'] ) ) : ?>
                    <img class="daw-profile__identity-photo" src="<?= esc_url( recruitment_get_public_url( 'applicant-file', [ 'document' => 'profile_photo' ] ) ) ?>" alt="Foto profil pelamar">
                <?php elseif ( $preview ) : ?>
                    <img class="daw-profile__identity-photo" src="<?= esc_url( $profile_photo_url ) ?>" alt="Foto profil preview">
                <?php else : ?>
                    <div class="daw-account__avatar" aria-hidden="true"><?= esc_html( $avatar_initials ?: 'P' ) ?></div>
                <?php endif; ?>
                <div class="daw-profile__identity-copy"><strong><?= esc_html( $applicant['full_name'] ?? '' ) ?></strong><span><?= esc_html( $applicant['email'] ?? '' ) ?></span><small class="daw-profile__completion">Profil <?= esc_html( (string) $profile_percentage ) ?>% lengkap</small></div>
            </section>

            <form id="profile-form" class="daw-profile__form" method="post" enctype="multipart/form-data" action="<?= esc_url( recruitment_get_public_url( 'account-profile' ) ) ?>">
                <?php wp_nonce_field( 'applicant_account_account-profile', 'applicant_account_nonce' ); ?>
                <?php if ( $preview ) : ?><input type="hidden" name="daw_ui_preview" value="1"><?php endif; ?>
                <?php
                $applicant_profile = $applicant;
                $applicant_profile_registration = false;
                $applicant_profile_disabled = $profile_details_error;
                $applicant_profile_preview_photo_url = $preview ? $profile_photo_url : '';
                require recruitment_get_plugin_path( 'public/components/applicant-profile-fields.php' );
                ?>
                <?php if ( ! $preview && ! $profile_details_error ) : ?><div class="daw-account__actions"><button class="daw-recruitment__button" type="submit">Simpan Perubahan</button></div><?php endif; ?>
            </form>

            <?php if ( ! $preview ) : ?>
                <details class="daw-account__card daw-profile__password">
                    <summary>Ganti Password</summary>
                    <p class="daw-account__muted">Masukkan password saat ini. Password baru minimal 10 karakter.</p>
                    <form method="post" action="<?= esc_url( recruitment_get_public_url( 'account-profile' ) ) ?>">
                        <?php wp_nonce_field( 'applicant_password_change', 'applicant_password_nonce' ); ?>
                        <div class="daw-account__fields">
                            <label>Password saat ini<input type="password" name="current_password" autocomplete="current-password" required></label>
                            <label>Password baru<input type="password" name="new_password" minlength="10" autocomplete="new-password" required></label>
                            <label>Konfirmasi password baru<input type="password" name="password_confirmation" minlength="10" autocomplete="new-password" required></label>
                        </div>
                        <div class="daw-account__actions"><button class="daw-recruitment__button" type="submit">Ubah Password</button></div>
                    </form>
                </details>
            <?php endif; ?>
        <?php else : ?>
            <section class="daw-account__card daw-account__identity daw-profile__identity">
                <?php if ( ! empty( $profile_documents['profile_photo'] ) ) : ?>
                    <img class="daw-profile__identity-photo" src="<?= esc_url( recruitment_get_public_url( 'applicant-file', [ 'document' => 'profile_photo' ] ) ) ?>" alt="Foto profil pelamar">
                <?php elseif ( $preview ) : ?>
                    <img class="daw-profile__identity-photo" src="<?= esc_url( $profile_photo_url ) ?>" alt="Foto profil preview">
                <?php else : ?>
                    <div class="daw-account__avatar" aria-hidden="true"><?= esc_html( $avatar_initials ?: 'P' ) ?></div>
                <?php endif; ?>
                <div class="daw-profile__identity-copy"><strong><?= esc_html( $applicant['full_name'] ?? '' ) ?></strong><span><?= esc_html( $applicant['email'] ?? '' ) ?></span><small class="daw-profile__completion">Profil <?= esc_html( (string) $profile_percentage ) ?>% lengkap</small></div>
            </section>

            <div class="daw-profile__readonly-container">
                <?php
                $applicant_profile = $applicant;
                $applicant_profile_readonly = true;
                $applicant_profile_registration = false;
                $applicant_profile_disabled = true;
                $applicant_profile_preview_photo_url = $preview ? $profile_photo_url : '';
                require recruitment_get_plugin_path( 'public/components/applicant-profile-fields.php' );
                ?>
            </div>

            <?php if ( ! $preview ) : ?>
                <?php if ( $profile_applications_error ) : ?>
                    <section class="daw-profile__next-step" role="status"><div><span class="daw-profile__next-step-label">LANGKAH SELANJUTNYA</span><h2>Informasi lamaran belum dapat dimuat</h2><p>Koneksi ke layanan data sedang bermasalah. Silakan coba kembali nanti.</p></div></section>
                <?php elseif ( $profile_applications ) : ?>
                    <section class="daw-profile__next-step"><div><span class="daw-profile__next-step-label">LANGKAH SELANJUTNYA</span><h2>Ayo cek lamaran kamu sudah sampai tahap mana, ya?</h2><p>Pantau status dan tahapan seleksi dari lamaran yang sudah kamu kirim.</p></div><a class="daw-recruitment__button" href="<?= esc_url( recruitment_get_public_url( 'application-process' ) ) ?>">Tracking Lamaran</a></section>
                <?php else : ?>
                    <section class="daw-profile__next-step"><div><span class="daw-profile__next-step-label">LANGKAH SELANJUTNYA</span><h2>Ayo daftarkan dirimu!</h2><p>Temukan kesempatan karier yang cocok dan mulai perjalananmu bersama PT. Daya Adicipta Wisesa.</p></div><a class="daw-recruitment__button" href="<?= esc_url( recruitment_get_public_url( 'careers' ) ) ?>">Cari Lowongan yang Cocok</a></section>
                <?php endif; ?>
            <?php endif; ?>
        <?php endif; ?>
    </main>
    <?php require recruitment_get_plugin_path( 'public/components/footer.php' ); ?>
</div>
