<?php
/**
 * Plugin Name: Recruitment Plugin
 * Plugin URI:  https://example.com/recruitment-plugin
 * Description: Plugin rekrutmen untuk mengelola lowongan kerja, data pelamar, proses lamaran, dan halaman karier publik melalui dashboard HRD.
 * Version:     1.0.0
 * Author:      PT. Daya Adicipta Wisesa
 * License:     GPL-2.0-or-later
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'RECRUITMENT_PLUGIN_VERSION', '1.0.7' );
define( 'RECRUITMENT_PLUGIN_FILE', __FILE__ );
define( 'RECRUITMENT_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'RECRUITMENT_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

// Load shared helpers and modular services.
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/database/class-database.php';
require_once __DIR__ . '/includes/class-application.php';
require_once __DIR__ . '/includes/class-auth.php';
require_once __DIR__ . '/routes/class-router.php';
require_once __DIR__ . '/includes/class-plugin.php';

require_once __DIR__ . '/public/public-loader.php';
require_once __DIR__ . '/admin/admin-loader.php';

// Initialize the plugin.
( new Recruitment_Plugin() )->init();

register_activation_hook(
    __FILE__,
    static function (): void {
        $role = get_role( 'administrator' );
        if ( $role ) {
            $role->add_cap( 'manage_recruitment' );
            $role->add_cap( 'manage_user_department' );
        }
        $department_role = get_role( 'daw_user_department' );
        if ( ! $department_role ) {
            $department_role = add_role( 'daw_user_department', 'User Department', [ 'read' => true ] );
        }
        if ( $department_role ) {
            $department_role->add_cap( 'manage_user_department' );
        }
        flush_rewrite_rules();
    }
);

register_deactivation_hook( __FILE__, 'flush_rewrite_rules' );
