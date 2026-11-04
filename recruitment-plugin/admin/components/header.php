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
        <a href="<?= esc_url( recruitment_get_admin_url( 'hr-profile' ) ) ?>"><?= esc_html( $user->display_name ) ?></a>
        <?= recruitment_logout_form( 'Logout', recruitment_get_public_url( 'login', [ 'target' => 'hr' ] ), 'nav-action' ) ?>
    </nav>
</header>
