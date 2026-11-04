<?php
/**
 * admin/components/header.php
 *
 * Header dashboard HRD.
 */
$user = wp_get_current_user();
?>
<header class="recruitment-admin__header">
    <a class="recruitment-admin__brand" href="<?= esc_url( recruitment_get_admin_url( 'recruitment-dashboard' ) ) ?>"><span> D </span> DAW Recruitment</a>
    <nav aria-label="Admin navigasi">
        <a href="<?= esc_url( recruitment_get_public_url( 'careers' ) ) ?>">Public Site</a>
        <span><?= esc_html( $user->display_name ) ?></span>
        <a class="nav-action" href="<?= esc_url( wp_logout_url( home_url( '/' ) ) ) ?>">Logout</a>
    </nav>
</header>
