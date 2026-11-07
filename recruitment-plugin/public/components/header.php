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
        $header_candidate = $is_applicant_user ? recruitment_get_current_applicant() : null;
        $header_applicant = $is_applicant_user
            ? (object) [
                'display_name' => (string) ( $header_candidate['full_name'] ?? '' ),
                'user_email' => (string) ( $header_candidate['email'] ?? '' ),
            ]
            : (object) [
                'display_name' => 'Aiko Rahayu',
                'user_email'   => 'aiko.rahayu@email.com',
            ];
        $header_applicant_name = trim( (string) $header_applicant->display_name );
        $header_name_parts = preg_split( '/\s+/', $header_applicant_name, -1, PREG_SPLIT_NO_EMPTY );
        $header_avatar_initials = '';
        foreach ( array_slice( $header_name_parts ?: [], 0, 2 ) as $header_name_part ) {
            $header_avatar_initials .= strtoupper( substr( $header_name_part, 0, 1 ) );
        }
        $logout_form = $is_applicant_user
            ? recruitment_logout_form( 'Keluar', recruitment_get_public_url( 'careers' ), 'daw-account-menu__logout-form' )
            : '';
        ?>
        <div class="daw-account-menu" data-account-menu>
            <button class="daw-account-menu__toggle" type="button" data-account-menu-toggle aria-expanded="false" aria-controls="daw-account-menu-panel">
                <span class="daw-account-menu__avatar" aria-hidden="true"><?= esc_html( $header_avatar_initials ?: 'P' ) ?></span>
                <span class="daw-account-menu__label"><strong><?= esc_html( $header_applicant_name ) ?></strong><small>Akun Pelamar</small></span>
                <span class="daw-account-menu__chevron" aria-hidden="true">⌄</span>
            </button>
            <section class="daw-account-menu__panel" id="daw-account-menu-panel" data-account-menu-panel hidden>
                <div class="daw-account-menu__identity">
                    <span class="daw-account-menu__avatar daw-account-menu__avatar--dark" aria-hidden="true"><?= esc_html( $header_avatar_initials ?: 'P' ) ?></span>
                    <span><strong><?= esc_html( $header_applicant_name ) ?></strong><small><?= esc_html( $header_applicant->user_email ) ?></small><b>Akun Pelamar</b></span>
                </div>
                <nav aria-label="Menu akun pelamar">
                    <a href="<?= esc_url( recruitment_get_public_url( 'account-profile' ) ) ?>"><strong>Edit Profil</strong><small>Kelola data diri dan dokumen</small></a>
                    <a href="<?= esc_url( recruitment_get_public_url( 'application-process' ) ) ?>"><strong>Tracking Lamaran</strong><small>Pantau tahap seleksi aktif</small></a>
                </nav>
                <?php if ( $is_applicant_user ) : ?>
                    <?= $logout_form ?>
                <?php else : ?>
                    <a class="daw-account-menu__logout" href="<?= esc_url( recruitment_get_public_url( 'careers' ) ) ?>">Tutup preview</a>
                <?php endif; ?>
            </section>
        </div>
    <?php else : ?>
        <a class="daw-recruitment__cta" href="<?= esc_url( recruitment_get_public_url( 'applicant-login' ) ) ?>">Masuk</a>
    <?php endif; ?>
</header>
