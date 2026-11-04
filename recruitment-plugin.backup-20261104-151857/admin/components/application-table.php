<?php
/**
 * admin/components/application-table.php
 *
 * Reusable table untuk daftar applications.
 *
 * Variabel yang diharapkan tersedia di scope:
 *   $applications (array) — daftar application dari Recruitment_Database.
 *
 * TODO: Implementasi dengan data dari Recruitment_Database::get_applications().
 */
$applications = $applications ?? [];
?>
<section class="status-table">
    <p class="eyebrow">Daftar Application</p>
    <?php if ( empty( $applications ) ) : ?>
        <p style="color:var(--muted); padding: 20px 0;">Belum ada data application. Akan ditampilkan setelah database diimplementasikan.</p>
    <?php else : ?>
        <table>
            <thead>
                <tr>
                    <th>Nama</th>
                    <th>Email</th>
                    <th>Posisi</th>
                    <th>Tanggal</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ( $applications as $app ) : ?>
                    <tr>
                        <td><?= htmlspecialchars( $app['name'] ?? '', ENT_QUOTES, 'UTF-8' ) ?></td>
                        <td><?= htmlspecialchars( $app['email'] ?? '', ENT_QUOTES, 'UTF-8' ) ?></td>
                        <td><?= htmlspecialchars( $app['position'] ?? '', ENT_QUOTES, 'UTF-8' ) ?></td>
                        <td><?= htmlspecialchars( $app['date'] ?? '', ENT_QUOTES, 'UTF-8' ) ?></td>
                        <td><?= htmlspecialchars( $app['status'] ?? '', ENT_QUOTES, 'UTF-8' ) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>
