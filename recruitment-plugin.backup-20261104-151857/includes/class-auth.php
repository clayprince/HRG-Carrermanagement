<?php

/**
 * Recruitment_Auth — authentication and authorization.
 *
 * Menggunakan WordPress authentication API.
 * Jangan membuat sistem password sendiri.
 *
 * Tanggung jawab:
 * - login
 * - logout
 * - pengecekan user login
 * - pengecekan capability/role
 * - proteksi halaman admin
 */
class Recruitment_Auth {

    /**
     * Log in a user via WordPress authentication.
     *
     * @param string $username
     * @param string $password
     * @return bool True on success.
     */
    public function login( string $username, string $password ): bool {
        return wp_signon( trim( $username ), $password );
    }

    /**
     * Log out the current user.
     */
    public function logout(): void {
        wp_logout();
    }

    /**
     * Check whether the current user has a given capability.
     *
     * @param string $capability WordPress capability slug (e.g. 'manage_options').
     * @return bool
     */
    public function current_user_can( string $capability ): bool {
        return current_user_can( $capability );
    }
}
