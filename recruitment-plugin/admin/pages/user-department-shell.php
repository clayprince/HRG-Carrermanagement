<?php
$current_user = wp_get_current_user();
$display_name = '' !== (string) $current_user->display_name ? (string) $current_user->display_name : 'User Department';
$department = (string) get_user_meta( $current_user->ID, 'daw_department', true );
$department = '' !== $department ? $department : 'Department';
$view = sanitize_key( wp_unslash( $_GET['view'] ?? 'dashboard' ) );
$views = [ 'dashboard', 'requests', 'interviews', 'applicants', 'results' ];
if ( ! in_array( $view, $views, true ) ) {
    $view = 'dashboard';
}
$page_url = admin_url( 'admin.php?page=daw-user-department' );
$view_url = static function ( string $requested_view ) use ( $page_url ): string {
    return add_query_arg( 'view', $requested_view, $page_url );
};
?>
<div class="daw-user-department">
    <aside class="daw-ud-sidebar" aria-label="Navigasi User Department">
        <a class="daw-ud-brand" href="<?= esc_url( $view_url( 'dashboard' ) ) ?>">
            <span class="daw-ud-brand__mark" aria-hidden="true">D</span>
            <span><strong>Daya Adicipta Wisesa</strong><small>User Department</small></span>
        </a>
        <div class="daw-ud-identity">
            <span>User Department</span>
            <strong><?= esc_html( $department ) ?> — <?= esc_html( $display_name ) ?></strong>
        </div>
        <nav class="daw-ud-nav" aria-label="Menu User Department">
            <a class="<?= 'dashboard' === $view ? 'is-active' : '' ?>" href="<?= esc_url( $view_url( 'dashboard' ) ) ?>" <?= 'dashboard' === $view ? 'aria-current="page"' : '' ?>><span class="dashicons dashicons-dashboard" aria-hidden="true"></span>Dashboard</a>
            <a class="<?= 'requests' === $view ? 'is-active' : '' ?>" href="<?= esc_url( $view_url( 'requests' ) ) ?>" <?= 'requests' === $view ? 'aria-current="page"' : '' ?>><span class="dashicons dashicons-clipboard" aria-hidden="true"></span>Request Lowongan</a>
            <a class="<?= 'interviews' === $view ? 'is-active' : '' ?>" href="<?= esc_url( $view_url( 'interviews' ) ) ?>" <?= 'interviews' === $view ? 'aria-current="page"' : '' ?>><span class="dashicons dashicons-groups" aria-hidden="true"></span><span>Kandidat Wawancara User</span></a>
            <a class="<?= 'applicants' === $view ? 'is-active' : '' ?>" href="<?= esc_url( $view_url( 'applicants' ) ) ?>" <?= 'applicants' === $view ? 'aria-current="page"' : '' ?>><span class="dashicons dashicons-database" aria-hidden="true"></span>Data Pelamar</a>
            <a class="<?= 'results' === $view ? 'is-active' : '' ?>" href="<?= esc_url( $view_url( 'results' ) ) ?>" <?= 'results' === $view ? 'aria-current="page"' : '' ?>><span class="dashicons dashicons-list-view" aria-hidden="true"></span>Hasil Wawancara User</a>
        </nav>
        <div class="daw-ud-sidebar__footer"><span class="dashicons dashicons-building" aria-hidden="true"></span><?= esc_html( $department ) ?> Department</div>
    </aside>
    <div class="daw-ud-workspace">
        <main class="daw-ud-main" aria-label="Konten User Department"></main>
    </div>
</div>
