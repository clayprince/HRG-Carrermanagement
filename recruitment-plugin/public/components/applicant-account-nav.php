<?php
$preview_args = recruitment_is_ui_preview() ? [ 'daw_ui_preview' => '1' ] : [];
$logout_form = recruitment_logout_form( 'Keluar', recruitment_get_public_url( 'careers', $preview_args ), 'daw-account__logout' );
?>
<nav class="daw-account__nav" aria-label="Navigasi akun pelamar">
    <a class="<?= in_array( $page ?? '', [ 'account-profile' ], true ) ? 'is-active' : '' ?>" href="<?= esc_url( recruitment_get_public_url( 'account-profile', $preview_args ) ) ?>">Profil</a>
    <a class="<?= in_array( $page ?? '', [ 'application-history', 'application-status', 'application-detail', 'application-process', 'tracking-detail' ], true ) ? 'is-active' : '' ?>" href="<?= esc_url( recruitment_get_public_url( 'application-history', $preview_args ) ) ?>">Riwayat Lamaran</a>
    <?= $logout_form ?>
</nav>
