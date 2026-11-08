<?php
?>
<nav class="daw-account__nav" aria-label="Navigasi akun pelamar">
    <a class="<?= in_array( $page ?? '', [ 'applicant-dashboard' ], true ) ? 'is-active' : '' ?>" href="<?= esc_url( recruitment_get_public_url( 'applicant-dashboard' ) ) ?>">Dashboard</a>
    <a class="<?= in_array( $page ?? '', [ 'account-profile' ], true ) ? 'is-active' : '' ?>" href="<?= esc_url( recruitment_get_public_url( 'account-profile' ) ) ?>">Profil</a>
</nav>
