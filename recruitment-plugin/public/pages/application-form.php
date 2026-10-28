<?php
$submitted = false;
$errors    = [];
$payload   = [];
$submission_succeeded = false;
$job_id    = absint( $_GET['job_id'] ?? $_POST['job_id'] ?? 0 );
$current_user = wp_get_current_user();
$name     = (string) $current_user->display_name;
$email    = (string) $current_user->user_email;
$phone    = (string) get_user_meta( get_current_user_id(), 'daw_applicant_phone', true );
$birth_place = (string) get_user_meta( get_current_user_id(), 'daw_applicant_birth_place', true );
$birth_date = (string) get_user_meta( get_current_user_id(), 'daw_applicant_birth_date', true );
$address = (string) get_user_meta( get_current_user_id(), 'daw_applicant_address', true );
$ui_preview = recruitment_is_ui_preview() || ( function_exists( 'current_user_can' ) && current_user_can( 'manage_options' ) );

if ( 'POST' === $_SERVER['REQUEST_METHOD'] ) {
    $preview_submission = $ui_preview
        || '1' === sanitize_text_field( wp_unslash( $_POST['daw_ui_preview'] ?? '' ) );
    if ( $preview_submission ) {
        $errors[] = 'Mode preview hanya untuk melihat tampilan. Lamaran tidak dikirim.';
    } elseif ( ! isset( $_POST['recruitment_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['recruitment_nonce'] ) ), 'recruitment_apply' ) ) {
        $errors[] = 'Sesi form tidak valid. Silakan coba lagi.';
    } else {
        $result  = ( new Recruitment_Application() )->submit( $_POST, $_FILES );
        $errors  = $result['errors'];
        $payload = $result['payload'];
        $name    = $payload['applicant']['full_name'];
        $email   = $payload['applicant']['email'];
        $submission_succeeded = $result['success'];
        if ( ! $errors && $submission_succeeded ) {
            update_user_meta( get_current_user_id(), 'daw_application_confirmation_pending', '1' );
            wp_safe_redirect( recruitment_get_public_url( 'application-confirmation' ) );
            exit;
        }
    }
    $submitted = ! $errors;
}

$job = recruitment_get_public_job( $job_id ) ?: recruitment_get_public_job( 1 );
$job_title = $job['title'];
$job_meta  = trim( $job['dealer'] . ' · ' . $job['location'], ' ·' );
$job_deadline = $job['deadline'];
$vacancy   = $job_id ? get_post( $job_id ) : null;
if ( $vacancy && 'daw_vacancy' === $vacancy->post_type ) {
    $job = recruitment_map_vacancy( $vacancy );
    $job_title = $job['title'];
    $job_meta  = trim( $job['dealer'] . ' · ' . $job['location'], ' ·' );
    $job_deadline = $job['deadline'];
}
?>
<div class="daw-recruitment daw-recruitment--application">
    <?php require recruitment_get_plugin_path( 'public/components/header.php' ); ?>
    <main class="daw-recruitment__section daw-recruitment__application-main">
        <div class="daw-recruitment__container daw-recruitment__form-container">
            <?php if ( $submitted ) : ?>
                <section class="daw-recruitment__panel daw-recruitment__success"><h1>Lamaran Berhasil Dikirim</h1><p>Terima kasih, <?= esc_html( $name ) ?>. Lamaran Anda telah dikirim.</p><p>Status lamaran akan tersedia di akun setelah integrasi data aplikasi selesai.</p><a class="daw-recruitment__button" href="<?= esc_url( recruitment_get_public_url( 'application-history' ) ) ?>">Kembali ke Akun</a></section>
            <?php else : ?>
                <a class="daw-recruitment__back" href="<?= esc_url( recruitment_get_public_url( 'job-detail', [ 'job_id' => $job_id ] ) ) ?>">&larr; Kembali ke Detail Lowongan</a>
                <section class="daw-recruitment__application-job"><span class="daw-recruitment__application-job-icon">&diams;</span><div><strong><?= esc_html( $job_title ) ?></strong><small><?= esc_html( $job_meta ) ?></small></div><span class="daw-recruitment__application-deadline">Deadline: <?= esc_html( $job_deadline ) ?></span></section>
                <div class="daw-recruitment__alert daw-recruitment__alert--notice"><?= $ui_preview ? 'Mode preview: formulir dapat dilihat, tetapi lamaran tidak akan dikirim.' : 'Isi seluruh formulir dengan lengkap dan benar. Pastikan semua data dapat dipertanggung jawabkan.' ?></div>
                <?php foreach ( $errors as $error ) : ?><div class="daw-recruitment__alert"><?= esc_html( $error ) ?></div><?php endforeach; ?>
                <form class="daw-recruitment__application-form" method="post" enctype="multipart/form-data" data-application-wizard <?= $ui_preview ? 'data-preview-only="true"' : '' ?>>
                    <input type="hidden" name="job_id" value="<?= esc_attr( $job_id ) ?>"><input type="hidden" name="daw_ui_preview" value="<?= esc_attr( $ui_preview ? '1' : '0' ) ?>"><?php wp_nonce_field( 'recruitment_apply', 'recruitment_nonce' ); ?>
                    <section class="daw-recruitment__form-card"><div class="daw-recruitment__form-card-title"><span>A</span><div><h2>Identitas</h2><p>Informasi pribadi</p></div></div><div class="daw-recruitment__form-fields">
                        <label class="daw-recruitment__field daw-recruitment__field--full">Foto Terkini <em>*</em><span class="daw-recruitment__upload"><input type="file" name="photo" accept="image/jpeg,image/png"><strong>Upload Foto</strong><small>JPG / PNG · Maks 2MB</small></span></label>
                        <label>Nama Lengkap <em>*</em><input name="full_name" required placeholder="Nama sesuai KTP" value="<?= esc_attr( $name ) ?>"></label>
                        <label>Tempat Lahir <input name="birth_place" placeholder="Kota tempat lahir" value="<?= esc_attr( $birth_place ) ?>"></label><label>Tanggal Lahir <em>*</em><input name="birth_date" type="date" required value="<?= esc_attr( $birth_date ) ?>"></label>
                        <label class="daw-recruitment__field--full">Alamat Saat Ini <em>*</em><textarea name="address" required rows="2" placeholder="Jalan, RT/RW, Kelurahan, Kecamatan, Kota/Kabupaten"><?= esc_textarea( $address ) ?></textarea></label>
                        <label>Nomor Telepon / WhatsApp <em>*</em><input name="phone" required type="tel" placeholder="08xxxxxxxxxx" value="<?= esc_attr( $phone ) ?>"></label><label>Email Aktif <em>*</em><input name="email" type="email" required placeholder="" value="<?= esc_attr( $email ) ?>"></label>
                        <label>Media Sosial yang Dimiliki<select name="social_platform"><option value="">Platform</option><option>Instagram</option><option>LinkedIn</option><option>Facebook</option><option>TikTok</option><option>YouTube</option><option>Lainnya</option></select></label><label><span>&nbsp;</span><input name="social_username" placeholder="@username atau URL profil"></label>
                        <label class="daw-recruitment__field--full">Nomor KTP <em>*</em><input name="identity_number" required placeholder="16 digit nomor KTP"></label>
                    </div></section>
                    <section class="daw-recruitment__form-card"><div class="daw-recruitment__form-card-title"><span>B</span><div><h2>Pendidikan Formal</h2><p>Isi riwayat pendidikan dari SMA/SMK hingga jenjang tertinggi</p></div></div><div class="daw-recruitment__repeatable" data-repeatable="education"><div class="daw-recruitment__repeatable-title">PENDIDIKAN 1</div><div class="daw-recruitment__form-fields"><label>Jenjang Pendidikan <em>*</em><select name="education_level[]" required><option value="">Pilih jenjang</option><option>SMA / SMK</option><option>D3</option><option>S1</option><option>S2</option></select></label><label>Tahun Lulus <em>*</em><input name="graduation_year[]" required placeholder=""></label><label class="daw-recruitment__field--full">Nama Sekolah / Universitas <em>*</em><input name="school[]" required placeholder="Nama institusi pendidikan"></label><label>Jurusan / Program Studi<input name="major[]" placeholder=""></label></div></div><button class="daw-recruitment__add-row" type="button" data-add="education">＋ Tambah Pendidikan</button></section>
                    <section class="daw-recruitment__form-card"><div class="daw-recruitment__form-card-title"><span>C</span><div><h2>Riwayat Pekerjaan</h2><p>Isi pengalaman kerja Anda. Kosongkan jika belum memiliki pengalaman.</p></div></div><div class="daw-recruitment__repeatable" data-repeatable="work"><div class="daw-recruitment__repeatable-title">PEKERJAAN 1</div><div class="daw-recruitment__form-fields"><label>Nama Perusahaan<input name="company[]" placeholder=""></label><label>Jabatan / Posisi<input name="position[]" placeholder=""></label><label>Periode Bekerja<input name="work_period[]" placeholder="Start - End"></label><label>Nama Atasan / Referensi<input name="supervisor[]" placeholder="Nama atasan langsung"></label><label class="daw-recruitment__field--full">Nomor Kontak Atasan / Referensi<input name="supervisor_phone[]" placeholder="Nomor telepon yang dapat dihubungi"></label></div></div><button class="daw-recruitment__add-row" type="button" data-add="work">＋ Tambah Pengalaman Kerja</button></section>
                    <section class="daw-recruitment__form-card"><div class="daw-recruitment__form-card-title"><span>D</span><div><h2>Keluarga</h2><p>Informasi status pernikahan dan data keluarga</p></div></div><div class="daw-recruitment__form-fields"><fieldset class="daw-recruitment__field--full"><legend>Status Pernikahan <em>*</em></legend><div class="daw-recruitment__choice-row"><label><input type="radio" name="marital_status" value="single" required> Belum Menikah</label><label><input type="radio" name="marital_status" value="married"> Menikah</label><label><input type="radio" name="marital_status" value="divorced"> Cerai</label></div></fieldset><fieldset class="daw-recruitment__field--full"><legend>Apakah Anda memiliki hubungan keluarga dengan salah satu karyawan Daya Group? <em>*</em></legend><div class="daw-recruitment__choice-row"><label><input type="radio" name="family_relation" value="yes" required> Ya</label><label><input type="radio" name="family_relation" value="no"> Tidak</label></div></fieldset><fieldset class="daw-recruitment__field--full"><legend>Apakah Anda memiliki hubungan pribadi dengan salah satu karyawan atau berencana menikah dengan karyawan di masa yang akan datang? <em>*</em></legend><div class="daw-recruitment__choice-row"><label><input type="radio" name="personal_relation" value="yes" required> Ya</label><label><input type="radio" name="personal_relation" value="no"> Tidak</label></div></fieldset><label>Susunan Keluarga Inti<textarea name="immediate_family" rows="2" placeholder="Nama, hubungan, usia (jika perlu)"></textarea></label><label>Susunan Keluarga Pribadi<textarea name="personal_family" rows="2" placeholder="Nama, hubungan, usia (jika perlu)"></textarea></label></div></section>
                    <section class="daw-recruitment__form-card"><div class="daw-recruitment__form-card-title"><span>E</span><div><h2>Minat dan Konsep Pribadi</h2><p>Ekspektasi Anda dalam bergabung dengan Daya Adicipta Wisesa</p></div></div><div class="daw-recruitment__form-fields"><label class="daw-recruitment__field--full">Referensi berapa gaji yang Saudara inginkan? <em>*</em><input name="salary_expectation" required placeholder=""></label><label class="daw-recruitment__field--full">Fasilitas yang diinginkan di luar gaji<textarea name="benefits" rows="2" placeholder=""></textarea></label><fieldset class="daw-recruitment__field--full"><legend>Apakah ada orang yang Saudara kenal dalam perusahaan kami? <em>*</em></legend><div class="daw-recruitment__choice-row"><label><input type="radio" name="known_employee" value="yes" required> Ya</label><label><input type="radio" name="known_employee" value="no"> Tidak</label></div></fieldset></div></section>
                    <section class="daw-recruitment__form-card daw-recruitment__form-card--pdp"><div class="daw-recruitment__form-card-title"><span>♢</span><div><h2>Pernyataan dan Persetujuan Data Pribadi (PDP)</h2><p>Centang seluruh pernyataan di bawah sebelum mengirimkan lamaran.</p></div></div><div class="daw-recruitment__pdp-list"><label><input type="checkbox" name="pdp_accuracy" value="1" required data-pdp-consent> Saya menyatakan bahwa data-data yang saya isi dalam formulir ini adalah benar dan menggambarkan kondisi saya saat ini dan saya bersedia untuk mempertanggungjawabkannya.</label><label><input type="checkbox" name="pdp_data" value="1" required data-pdp-consent> Saya bersedia memberikan Data Pribadi saya seperti CV, Ijazah, Transkrip Nilai, Foto, KTP, SIM, NPWP, Nomor Rekening Bank, SPT Kartu Keluarga, Media Sosial Saya dan data lainnya untuk diakses, disimpan dan dipergunakan untuk proses recruitment di Daya Group.</label><label><input type="checkbox" name="pdp_placement" value="1" required data-pdp-consent> Saya bersedia ditempatkan di seluruh area penempatan Daya Adicipta Wisesa dan seluruh anak perusahaan di bawah naungan Daya Group.</label></div><p class="daw-recruitment__pdp-note">ⓘ Centang ketiga pernyataan di atas untuk mengaktifkan tombol Submit.</p></section>
                    <div class="daw-recruitment__form-actions"><a class="daw-recruitment__button daw-recruitment__button--muted" href="<?= esc_url( recruitment_get_public_url( 'job-detail', [ 'job_id' => $job_id ] ) ) ?>">← Batal</a><button class="daw-recruitment__button daw-recruitment__submit" type="submit" disabled data-submit-application <?= $ui_preview ? 'aria-disabled="true" title="Tidak tersedia pada mode preview"' : '' ?>><?= $ui_preview ? 'Preview - Lamaran Nonaktif' : 'Submit Lamaran' ?></button></div>
                </form>
            <?php endif; ?>
        </div>
    </main>
    <?php require recruitment_get_plugin_path( 'public/components/footer.php' ); ?>
</div>
