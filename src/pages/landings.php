<?php

$db  = Database::get();
$sku = trim($_GET['sku'] ?? '');

if (!$sku) {
    header('Location: /');
    exit;
}

// Get landings for this SKU with testimonial counts
$sql = "SELECT
            l.*,
            COUNT(t.id) AS testimonial_count
        FROM landings l
        LEFT JOIN testimonials t ON t.landing_id = l.id
        WHERE l.parent_sku = ?
        GROUP BY l.id
        ORDER BY l.is_master DESC, l.country ASC";

$stmt = $db->prepare($sql);
$stmt->execute([$sku]);
$landings = $stmt->fetchAll();

if (empty($landings)) {
    header('Location: /');
    exit;
}

$masterTitle = '';
foreach ($landings as $l) {
    if ($l['is_master']) {
        $masterTitle = $l['title'];
        break;
    }
}

renderHeader('Landings — ' . $sku);
?>

<div class="breadcrumb">
    <a href="<?= APP_BASE ?>/">Products</a> &rsaquo; <?= h($sku) ?>
</div>

<div class="page-header">
    <div>
        <h2><?= h($sku) ?></h2>
        <?php if ($masterTitle): ?>
            <p class="text-muted"><?= h($masterTitle) ?></p>
        <?php endif; ?>
    </div>
</div>

<table class="table">
    <thead>
        <tr>
            <th>Country</th>
            <th>Type</th>
            <th>Title</th>
            <th>Landing URL</th>
            <th>Testimonials</th>
            <th>Actions</th>
        </tr>
    </thead>
    <tbody>
    <?php foreach ($landings as $l): ?>
        <tr>
            <td>
                <span class="country-flag"><?= h(strtoupper($l['country'])) ?></span>
            </td>
            <td>
                <?php if ($l['is_master']): ?>
                    <span class="badge badge-green">Master (EN)</span>
                <?php else: ?>
                    <span class="badge">Local</span>
                <?php endif; ?>
            </td>
            <td><?= h($l['title'] ?? '—') ?></td>
            <td>
                <?php if ($l['landing_url']): ?>
                    <a href="<?= h($l['landing_url']) ?>" target="_blank" class="link-external text-sm">
                        <?= h(parse_url($l['landing_url'], PHP_URL_PATH) ?: $l['landing_url']) ?>
                    </a>
                <?php else: ?>
                    <span class="text-muted">—</span>
                <?php endif; ?>
            </td>
            <td>
                <span class="badge badge-blue"><?= (int) $l['testimonial_count'] ?></span>
            </td>
            <td>
                <a href="<?= APP_BASE ?>/?action=testimonials&landing_id=<?= (int) $l['id'] ?>"
                   class="btn btn-sm btn-primary">Manage</a>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<?php renderFooter(); ?>
