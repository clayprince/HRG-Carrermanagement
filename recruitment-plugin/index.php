<?php
declare(strict_types=1);

if ( ! defined( 'ABSPATH' ) ) {
    define( 'ABSPATH', __DIR__ . '/' );
}

if ( ! defined( 'RECRUITMENT_PLUGIN_PATH' ) ) {
    define( 'RECRUITMENT_PLUGIN_PATH', __DIR__ . '/' );
}

if ( ! defined( 'RECRUITMENT_PLUGIN_URL' ) ) {
    $request_uri = $_SERVER['REQUEST_URI'] ?? '/';
    $base_path = preg_replace( '#/[^/]+$#', '', parse_url( $request_uri, PHP_URL_PATH ) ?: '/' );
    $base_path = '/' === $base_path ? '' : $base_path;
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    $base_url = 'http://' . $host . $base_path;
    define( 'RECRUITMENT_PLUGIN_URL', rtrim( $base_url, '/' ) . '/wp-content/plugins/HRG-Carrermanagement/recruitment-plugin/' );
}

if ( ! defined( 'RECRUITMENT_SANDBOX' ) ) {
    define( 'RECRUITMENT_SANDBOX', true );
}

ob_start();
session_start();

// Load core dependencies.
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/class-database.php';
require_once __DIR__ . '/includes/class-application.php';
require_once __DIR__ . '/includes/class-auth.php';
require_once __DIR__ . '/includes/class-router.php';
require_once __DIR__ . '/includes/class-plugin.php';

$auth   = new Recruitment_Auth();
$requested_page = sanitize_key( $_GET['page'] ?? $_GET['recruitment_page'] ?? 'careers' );
$requested_path = parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH );
if ( '/interview-booking' === rtrim( (string) $requested_path, '/' ) ) {
    $requested_page = 'interview-booking';
}
$page = [
    'application' => 'application-form',
    'tracking' => 'application-history',
][ $requested_page ] ?? $requested_page;

if ( $page === 'logout' ) {
    $auth->logout();
    header( 'Location: ?page=login' );
    exit;
}

$error = null;
if ( $page === 'login' && $_SERVER['REQUEST_METHOD'] === 'POST' ) {
    if ( $auth->login( $_POST['username'] ?? '', $_POST['password'] ?? '' ) ) {
        header( 'Location: ?page=dashboard' );
        exit;
    }
    $error = 'Username atau password salah. Silakan coba lagi.';
}

?><!doctype html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DAW Recruitment</title>
    <link rel="stylesheet" href="<?= esc_url( recruitment_get_plugin_url( 'assets/css/public.css' ) ) ?>">
    <script src="<?= esc_url( recruitment_get_plugin_url( 'assets/js/public.js' ) ) ?>" defer></script>
    <?php if ( in_array( $page, [ 'login', 'dashboard', 'vacancies', 'applicants', 'applications', 'user-interview', 'settings' ], true ) ) : ?>
        <link rel="stylesheet" href="<?= esc_url( recruitment_get_plugin_url( 'assets/css/admin.css' ) ) ?>">
    <?php endif; ?>
    <?php if ( in_array( $page, [ 'user-interview', 'vacancies' ], true ) ) : ?>
        <script src="<?= esc_url( recruitment_get_plugin_url( 'assets/js/admin.js' ) ) ?>" defer></script>
    <?php endif; ?>
</head>
<body>
<?php
$router = new Recruitment_Router();
$router->render( $page, [ 'error' => $error ] );
?>
</body>
</html>
