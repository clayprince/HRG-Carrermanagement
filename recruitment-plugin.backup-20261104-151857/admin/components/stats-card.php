<?php
/**
 * admin/components/stats-card.php
 *
 * Card statistik untuk dashboard HRD.
 *
 * Variabel yang diharapkan tersedia di scope:
 *   $stat (array) — data statistik: label, value.
 *
 * Contoh:
 *   Total Vacancies: 12
 *   Total Applicants: 34
 */
$stat = $stat ?? [ 'label' => 'Stat', 'value' => '—' ];
?>
<div class="stats-card">
    <dt><?= htmlspecialchars( $stat['label'], ENT_QUOTES, 'UTF-8' ) ?></dt>
    <dd><?= htmlspecialchars( (string) $stat['value'], ENT_QUOTES, 'UTF-8' ) ?></dd>
</div>
