<header class="daw-recruitment__header">
    <a class="daw-recruitment__brand" href="<?= esc_url( recruitment_get_public_url( 'careers' ) ) ?>">
        <span class="daw-recruitment__logo">D</span>
        <span>DAW<small>PT. Daya Adicipta Wisesa</small></span>
    </a>
    <nav class="daw-recruitment__nav" aria-label="Navigasi utama">
        <a href="#">Beranda</a><a href="#">Tentang Kami</a><a href="#">Produk</a><a href="#">Layanan</a><a href="#">News</a>
        <a class="is-active" href="<?= esc_url( recruitment_get_public_url( 'careers' ) ) ?>">Career</a><a href="#">Event</a><a href="#">Kontak</a>
    </nav>
    <?php
    $is_applicant_user = recruitment_is_applicant_user();
    $is_preview_account = ! $is_applicant_user && recruitment_is_ui_preview();
    ?>
    <?php if ( $is_applicant_user || $is_preview_account ) : ?>
        <?php
        $applicant = $is_applicant_user
            ? wp_get_current_user()
            : (object) [
                'display_name' => 'Aiko Rahayu',
                'user_email'   => 'aiko.rahayu@email.com',
            ];
        $applicant_name = trim( (string) $applicant->display_name );
        $name_parts = preg_split( '/\s+/', $applicant_name, -1, PREG_SPLIT_NO_EMPTY );
        $avatar_initials = '';
        foreach ( array_slice( $name_parts ?: [], 0, 2 ) as $name_part ) {
            $avatar_initials .= strtoupper( substr( $name_part, 0, 1 ) );
        }
        $logout_url = $is_applicant_user
            ? wp_logout_url( recruitment_get_public_url( 'careers' ) )
            : recruitment_get_public_url( 'careers' );
        ?>
        <div class="daw-account-menu" data-account-menu>
            <button class="daw-account-menu__toggle" type="button" data-account-menu-toggle aria-expanded="false" aria-controls="daw-account-menu-panel">
                <span class="daw-account-menu__avatar" aria-hidden="true"><?= esc_html( $avatar_initials ?: 'P' ) ?></span>
                <span class="daw-account-menu__label"><strong><?= esc_html( $applicant_name ) ?></strong><small>Akun Pelamar</small></span>
                <span class="daw-account-menu__chevron" aria-hidden="true">⌄</span>
            </button>
            <section class="daw-account-menu__panel" id="daw-account-menu-panel" data-account-menu-panel hidden>
                <div class="daw-account-menu__identity">
                    <span class="daw-account-menu__avatar daw-account-menu__avatar--dark" aria-hidden="true"><?= esc_html( $avatar_initials ?: 'P' ) ?></span>
                    <span><strong><?= esc_html( $applicant_name ) ?></strong><small><?= esc_html( $applicant->user_email ) ?></small><b>Akun Pelamar</b></span>
                </div>
                <nav aria-label="Menu akun pelamar">
                    <a href="<?= esc_url( recruitment_get_public_url( 'account-profile' ) ) ?>"><strong>Edit Profil</strong><small>Kelola data diri dan dokumen</small></a>
                    <a href="<?= esc_url( recruitment_get_public_url( 'application-process' ) ) ?>"><strong>Tracking Lamaran</strong><small>Pantau tahap seleksi aktif</small></a>
                    <a href="<?= esc_url( recruitment_get_public_url( 'application-history' ) ) ?>"><strong>Detail &amp; Riwayat Lamaran</strong><small>Lihat seluruh lamaran</small></a>
                </nav>
                <a class="daw-account-menu__logout" href="<?= esc_url( $logout_url ) ?>"><?= $is_applicant_user ? 'Keluar' : 'Tutup preview' ?></a>
            </section>
        </div>
    <?php else : ?>
        <a class="daw-recruitment__cta" href="<?= esc_url( recruitment_get_public_url( 'applicant-login' ) ) ?>">Masuk</a>
    <?php endif; ?>
</header>
