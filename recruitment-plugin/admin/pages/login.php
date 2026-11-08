<?php
$target = 'user_department' === ( $target ?? '' ) ? 'user_department' : 'hr';
$mode = 'reset-password' === ( $page ?? '' ) ? 'reset-password' : 'login';
$is_department_login = 'user_department' === $target;
$public_url = recruitment_get_public_url( 'careers' );
$forgot_hr_url = recruitment_get_public_url( 'forgot-password', [ 'target' => 'hr' ] );
$forgot_department_url = recruitment_get_department_login_url(
    [ 'recruitment_page' => 'forgot-password', 'target' => 'user_department' ]
);
$token = sanitize_text_field( wp_unslash( $_GET['token'] ?? '' ) );
$error = (string) ( $error ?? '' );
$forgot_notice = (string) ( $forgot_notice ?? '' );
$reset_notice = (string) ( $reset_notice ?? '' );
$open_forgot = '1' === sanitize_key( wp_unslash( $_GET['forgot'] ?? '' ) );
?>
<main class="daw-admin-login daw-admin-login--card">
    <section class="daw-admin-login__card" aria-labelledby="daw-admin-login-title">
        <p class="daw-admin-login__eyebrow"><?= 'reset-password' === $mode ? 'PENGATURAN AKUN' : 'SELAMAT DATANG KEMBALI' ?></p>
        <h1 id="daw-admin-login-title">Selamat Datang di Sistem Rekrutmen, Silahkan Login</h1>

        <?php if ( 'login' === $mode ) : ?>
            <?php if ( '' !== $error ) : ?>
                <p class="daw-admin-login__error" role="alert"><?= esc_html( $error ) ?></p>
            <?php endif; ?>
            <?php if ( 'password_updated' === $reset_notice ) : ?>
                <p class="daw-admin-login__notice" role="status">Password berhasil diubah. Silakan masuk dengan password baru.</p>
            <?php elseif ( 'service_unavailable' === $reset_notice ) : ?>
                <p class="daw-admin-login__error" role="alert">Password belum dapat diperbarui. Silakan coba kembali nanti.</p>
            <?php elseif ( 'invalid_token' === $reset_notice || 'invalid_password' === $reset_notice || 'invalid_request' === $reset_notice ) : ?>
                <p class="daw-admin-login__error" role="alert">Tautan reset tidak valid atau kedaluwarsa. Silakan ajukan permintaan reset kembali.</p>
            <?php endif; ?>
            <form method="post" action="<?= esc_url( add_query_arg( [ 'recruitment_page' => 'login', 'target' => $target ], get_permalink() ) ) ?>">
                <?php wp_nonce_field( 'daw_admin_portal_login' ); ?>
                <label for="daw-admin-role">Peran</label>
                <select id="daw-admin-role" name="target" data-portal-role data-forgot-hr="<?= esc_url( $forgot_hr_url ) ?>" data-forgot-department="<?= esc_url( $forgot_department_url ) ?>">
                    <option value="hr" <?= selected( $target, 'hr', false ) ?>>Admin HR</option>
                    <option value="user_department" <?= selected( $target, 'user_department', false ) ?>>Admin Kepala Departemen</option>
                </select>
                <label for="daw-admin-username">Email atau Username</label>
                <input id="daw-admin-username" name="username" type="text" autocomplete="username" required>
                <label for="daw-admin-password">Kata sandi</label>
                <input id="daw-admin-password" name="password" type="password" autocomplete="current-password" required>
                <button type="submit">Masuk</button>
            </form>
            <a class="daw-admin-login__lost-password" data-portal-forgot href="<?= esc_url( $is_department_login ? $forgot_department_url : $forgot_hr_url ) ?>" aria-haspopup="dialog">Lupa password?</a>
        <?php else : ?>
            <h2>Buat Password Baru</h2>
            <p class="daw-admin-login__description">Masukkan password baru untuk akun Admin HR Anda.</p>
            <form method="post" action="<?= esc_url( add_query_arg( [ 'recruitment_page' => 'reset-password', 'target' => 'hr' ], get_permalink() ) ) ?>">
                <?php wp_nonce_field( 'daw_hr_password_reset' ); ?>
                <input type="hidden" name="target" value="hr">
                <input type="hidden" name="token" value="<?= esc_attr( $token ) ?>">
                <label for="daw-new-password">Password baru</label>
                <input id="daw-new-password" name="new_password" type="password" minlength="8" autocomplete="new-password" required>
                <label for="daw-password-confirmation">Konfirmasi password baru</label>
                <input id="daw-password-confirmation" name="password_confirmation" type="password" minlength="8" autocomplete="new-password" required>
                <button type="submit">Simpan password baru</button>
            </form>
        <?php endif; ?>

        <a class="daw-admin-login__lost-password" href="<?= esc_url( wp_login_url( admin_url() ) ) ?>">Masuk melalui WordPress</a>
        <a class="daw-admin-login__back" href="<?= esc_url( $public_url ) ?>">Pergi ke tampilan pelamar</a>
    </section>
    <?php if ( 'login' === $mode ) : ?>
        <dialog class="daw-admin-login__modal" data-portal-forgot-modal aria-label="Lupa password" data-auto-open="<?= $open_forgot || in_array( $forgot_notice, [ 'submitted', 'invalid_request' ], true ) ? 'true' : 'false' ?>">
            <button class="daw-admin-login__modal-close" type="button" data-portal-forgot-close aria-label="Tutup">&times;</button>
            <div data-portal-forgot-department hidden>
                <p>Untuk mengubah password, silakan hubungi pihak HR. Reset password mandiri tidak tersedia untuk akun User Department</p>
            </div>
            <div data-portal-forgot-hr hidden>
                <h2 id="daw-forgot-modal-title">Lupa password Admin HR</h2>
                <p class="daw-admin-login__description">Masukkan email untuk menerima link reset password.</p>
                <?php if ( 'submitted' === $forgot_notice ) : ?>
                    <p class="daw-admin-login__notice" role="status">Jika email cocok dengan akun HR aktif, tautan reset akan dikirim ke email tersebut.</p>
                <?php elseif ( 'invalid_request' === $forgot_notice ) : ?>
                    <p class="daw-admin-login__error" role="alert">Permintaan tidak valid. Coba kembali.</p>
                <?php endif; ?>
                <form method="post" action="<?= esc_url( add_query_arg( [ 'recruitment_page' => 'forgot-password', 'target' => 'hr' ], get_permalink() ) ) ?>">
                    <?php wp_nonce_field( 'daw_hr_password_reset_request' ); ?>
                    <input type="hidden" name="target" value="hr">
                    <label for="daw-reset-email">Email</label>
                    <input id="daw-reset-email" name="email" type="email" autocomplete="email" required>
                    <button type="submit">Kirim link reset</button>
                </form>
            </div>
        </dialog>
    <?php endif; ?>
</main>
