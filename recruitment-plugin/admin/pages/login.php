<?php
$target = 'user_department' === ( $target ?? '' ) ? 'user_department' : 'hr';
$portal_url = recruitment_get_public_url( 'portal' );
$public_url = recruitment_get_public_url( 'careers' );
$login_page_url = function_exists( 'get_permalink' ) ? get_permalink() : $portal_url;
$login_action = add_query_arg( [ 'recruitment_page' => 'login', 'target' => $target ], $login_page_url );
$lost_password_url = function_exists( 'wp_lostpassword_url' ) ? wp_lostpassword_url( $login_action ) : add_query_arg( 'action', 'lostpassword', admin_url( 'wp-login.php' ) );
?>
<main class="daw-admin-login daw-admin-login--split">
    <section class="daw-admin-login__intro">
        <a class="daw-admin-login__brand" href="<?= esc_url( $portal_url ) ?>" aria-label="DAW Recruitment Admin"><span aria-hidden="true">D</span></a>
        <div class="daw-admin-login__message">
            <h1>DAW Recruitment</h1>
            <p class="daw-admin-login__subtitle">Sistem Manajemen Rekrutmen</p>
            <p>Platform internal HR untuk mengelola proses rekrutmen secara efisien — dari permintaan SDM hingga keputusan akhir.</p>
        </div>
        <div class="daw-admin-login__stats"><div><strong>3</strong><span>Wilayah</span></div><div><strong>12+</strong><span>Dealer</span></div><div><strong>20+</strong><span>Posisi</span></div></div>
    </section>
    <section class="daw-admin-login__panel">
        <div class="daw-admin-login__content">
            <section class="daw-admin-login__card" aria-labelledby="daw-admin-login-title">
                <h2 id="daw-admin-login-title">Masuk ke Portal Admin</h2>
                <p>Gunakan akun HR yang telah didaftarkan.</p>
                <?php if ( ! empty( $error ) ) : ?>
                    <p class="daw-admin-login__error" role="alert"><?= esc_html( $error ) ?></p>
                <?php endif; ?>
                <form method="post" action="<?= esc_url( $login_action ) ?>">
                    <?php wp_nonce_field( 'daw_admin_portal_login' ); ?>
                    <label for="daw-admin-role">Pilih portal</label>
                    <select id="daw-admin-role" name="target" required>
                        <option value="hr" <?= selected( $target, 'hr', false ) ?>>Admin HR</option>
                        <option value="user_department" <?= selected( $target, 'user_department', false ) ?>>User Department</option>
                    </select>
                    <label for="daw-admin-username">Email</label>
                    <input id="daw-admin-username" name="username" type="text" autocomplete="username" placeholder="admin@daw-recruitment.id" required />
                    <label for="daw-admin-password">Password</label>
                    <input id="daw-admin-password" name="password" type="password" autocomplete="current-password" placeholder="Masukkan password" required />
                    <button type="submit">Masuk</button>
                </form>
                <a class="daw-admin-login__lost-password" href="<?= esc_url( $lost_password_url ) ?>">Lupa Password?</a>
                <p class="daw-admin-login__restricted">Akses terbatas. Hanya administrator HR DAW yang berwenang.</p>
            </section>
            <a class="daw-admin-login__back" href="<?= esc_url( $public_url ) ?>">&larr; Kembali ke Halaman Publik</a>
        </div>
        <p class="daw-admin-login__copyright">&copy; <?= esc_html( gmdate( 'Y' ) ) ?> PT. Daya Adicipta Wisesa.</p>
    </section>
</main>
