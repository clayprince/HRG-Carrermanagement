<?php

// Backward-compatible entry point. New code must use database/class-database.php.
require_once dirname( __DIR__ ) . '/database/class-database.php';
if ( false ) {
// Legacy class body retained for source compatibility.

/**
 * Recruitment_Database — database abstraction layer.
 *
 * Semua query ke database plugin harus melalui class ini.
 * Gunakan $wpdb dan prepared statements — jangan langsung interpolasi variabel.
 *
 * Untuk tahap awal class ini hanya berupa stub.
 * Implementasikan method sesuai kebutuhan.
 *
 * Contoh:
 *   $db = new Recruitment_Database();
 *   $vacancies = $db->get_vacancies();
 */
class Recruitment_Database {

    /**
     * Get all active vacancies.
     *
     * @return array<int, array<string, mixed>>
     */
    public function get_vacancies(): array {
        // TODO: Implementasi menggunakan $wpdb->get_results() dengan prepared statement.
        return [];
    }

    /**
     * Get all applicants.
     *
     * @return array<int, array<string, mixed>>
     */
    public function get_applicants(): array {
        // TODO: Implementasi menggunakan $wpdb->get_results() dengan prepared statement.
        return [];
    }

    /**
     * Get all applications.
     *
     * @return array<int, array<string, mixed>>
     */
    public function get_applications(): array {
        // TODO: Implementasi menggunakan $wpdb->get_results() dengan prepared statement.
        return [];
    }
}
}
