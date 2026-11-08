<?php
$errors = [
    'invalid_request' => 'Sesi tidak valid. Muat ulang halaman lalu coba kembali.',
    'invalid_registration' => 'Pastikan nama lengkap, email aktif, dan semua data wajib sudah benar.',
    'invalid_profile' => 'Lengkapi semua data profil yang wajib diisi dan periksa kembali nilai yang dimasukkan.',
    'invalid_personal_data' => 'Periksa kembali data pribadi: nama, email, gender, tempat dan tanggal lahir, nomor telepon, serta alamat.',
    'invalid_education_data' => 'Periksa kembali data pendidikan, IPK (0-4), tahun lulus, status pernikahan, dan minat/bakat.',
    'invalid_work_experiences' => 'Lengkapi semua kolom pengalaman kerja yang sudah mulai diisi, atau hapus baris pengalaman yang kosong.',
    'invalid_family_members' => 'Isi minimal satu anggota keluarga dan lengkapi semua kolom pada setiap anggota keluarga.',
    'invalid_upload' => 'Foto profil dan CV wajib diunggah. Periksa jenis file, batas 5 MB per file, dan batas total unggahan 6 MB.',
    'upload_storage_failed' => 'Dokumen belum dapat disimpan dengan aman. Silakan coba kembali.',
    'email_exists' => 'Email ini sudah terdaftar. Silakan masuk ke akun Anda.',
    'password_mismatch' => 'Kata sandi harus minimal 10 karakter dan kedua isian harus sama.',
    'registration_setup_required' => 'Akun belum tersimpan karena schema Oracle belum lengkap. Minta administrator database meninjau dan menerapkan migrasi 009_candidate_account_auth.sql serta 010_applicant_profile_details.sql, lalu coba kembali.',
    'registration_failed' => 'Akun belum dapat dibuat. Pastikan migrasi Oracle profil pelamar sudah diterapkan atau coba kembali nanti.',
];
$registration_state = is_array( $GLOBALS['daw_applicant_registration_form_state'] ?? null )
    ? $GLOBALS['daw_applicant_registration_form_state']
    : [];
$error_code = (string) ( $registration_state['error'] ?? sanitize_key( wp_unslash( $_GET['account_error'] ?? '' ) ) );
$error = $errors[ $error_code ] ?? '';
$preview = recruitment_is_ui_preview();
$redirect_to = isset( $_GET['redirect_to'] ) && is_scalar( $_GET['redirect_to'] )
    ? sanitize_key( wp_unslash( (string) $_GET['redirect_to'] ) )
    : '';
$redirect_job_id = isset( $_GET['job_id'] ) && is_scalar( $_GET['job_id'] )
    ? absint( wp_unslash( (string) $_GET['job_id'] ) )
    : 0;
$redirect_args = 'application-form' === $redirect_to && $redirect_job_id
    ? [ 'redirect_to' => $redirect_to, 'job_id' => $redirect_job_id ]
    : ( 'account-profile' === $redirect_to ? [ 'redirect_to' => $redirect_to ] : [] );
$applicant_profile = is_array( $registration_state['profile'] ?? null )
    ? $registration_state['profile']
    : [];
$applicant_profile_registration = true;
?>
<div class="daw-recruitment daw-account-auth">
    <?php require recruitment_get_plugin_path( 'public/components/header.php' ); ?>
    <main class="daw-account-auth__main daw-account-auth__main--center daw-account-auth__main--registration">
        <section class="daw-account-auth__card daw-account-auth__card--registration">
            <span class="daw-recruitment__eyebrow">MEMULAI</span><h2>Buat akun pelamar</h2><p>Lengkapi profil agar data siap digunakan saat melamar pekerjaan.</p>
            <?php if ( $preview ) : ?><div class="daw-account__preview-note">Mode preview: pengiriman formulir dinonaktifkan.</div><?php endif; ?>
            <?php if ( $error ) : ?><div class="daw-account__alert" role="alert"><?= esc_html( $error ) ?></div><?php endif; ?>
            <?php if ( $registration_state ) : ?><div class="daw-account__alert" role="note">Data yang sudah diisi tetap ditampilkan. Kata sandi tidak disimpan; silakan masukkan kembali. File yang dipilih juga perlu dipilih ulang.</div><?php endif; ?>
            <form method="post" enctype="multipart/form-data" action="<?= esc_url( recruitment_get_public_url( 'applicant-register', $redirect_args ) ) ?>">
                <?php wp_nonce_field( 'applicant_account_applicant-register', 'applicant_account_nonce' ); ?>
                <?php if ( $redirect_args ) : ?><input type="hidden" name="redirect_to" value="<?= esc_attr( $redirect_to ) ?>"><?php if ( $redirect_job_id ) : ?><input type="hidden" name="job_id" value="<?= esc_attr( $redirect_job_id ) ?>"><?php endif; ?><?php endif; ?>
                <?php if ( $preview ) : ?><input type="hidden" name="daw_ui_preview" value="1"><?php endif; ?>
                <p class="daw-registration__field-legend"><strong aria-hidden="true">*</strong> Wajib diisi <span aria-hidden="true">·</span> Tanpa tanda bintang berarti opsional.</p>
                <?php require recruitment_get_plugin_path( 'public/components/applicant-profile-fields.php' ); ?>
                <section class="daw-account__card daw-profile__section daw-registration__password">
                    <h2>Keamanan Akun</h2>
                    <div class="daw-profile__grid">
                        <label class="daw-profile__value"><span>EMAIL AKTIF</span><input type="email" name="email" value="<?= esc_attr( $applicant_profile['email'] ?? '' ) ?>" maxlength="50" autocomplete="email" required <?= $preview ? 'disabled' : '' ?>><small>Email ini juga digunakan untuk masuk ke akun.</small></label>
                        <label class="daw-profile__value"><span>KATA SANDI</span><input type="password" name="password" autocomplete="new-password" minlength="10" required <?= $preview ? 'disabled' : '' ?>><small>Gunakan minimal 10 karakter.</small></label>
                        <label class="daw-profile__value"><span>KONFIRMASI KATA SANDI</span><input type="password" name="password_confirmation" autocomplete="new-password" minlength="10" required <?= $preview ? 'disabled' : '' ?>></label>
                    </div>
                </section>
                <button class="daw-recruitment__button" type="submit" <?= $preview ? 'disabled' : '' ?>>Buat Akun</button>
            </form>
            <p class="daw-account-auth__switch">Sudah punya akun? <a href="<?= esc_url( recruitment_get_public_url( 'applicant-login', $redirect_args ) ) ?>">Masuk</a></p>
        </section>
    </main>
    <?php require recruitment_get_plugin_path( 'public/components/footer.php' ); ?>
</div>
