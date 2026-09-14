<?php

$db = Database::get();

$sql = "SELECT
            l.parent_sku,
            MAX(CASE WHEN l.is_master = 1 THEN l.title END) AS master_title,
            COUNT(DISTINCT l.id)      AS landing_count,
            COUNT(DISTINCT l.country) AS country_count,
            COUNT(t.id)               AS testimonial_count,
            MAX(l.synced_at)          AS last_sync
        FROM landings l
        LEFT JOIN testimonials t ON t.landing_id = l.id
        GROUP BY l.parent_sku
        ORDER BY l.parent_sku";

$products = $db->query($sql)->fetchAll();

renderHeader('Products');
?>

<div class="page-header">
    <h2>Products</h2>
    <button class="btn btn-success" id="syncBtn" onclick="syncApi()">Sync from API</button>
</div>

<div class="search-row" style="margin-bottom:20px">
    <div class="search-input-wrap">
        <input type="text" id="skuSearch" placeholder="Search by SKU or title…"
               class="form-control search-input" autocomplete="off">
        <span class="search-spinner hidden" id="searchSpinner">⟳</span>
    </div>
</div>

<div id="syncStatus"></div>

<?php if (empty($products)): ?>
    <div class="empty-state" id="emptyState">
        <p>No products found. <strong>Sync from API</strong> to import data.</p>
    </div>
<?php else: ?>
    <div class="table-info" id="tableInfo">Showing <span id="visibleCount"><?= count($products) ?></span> of <?= count($products) ?> products</div>
    <div id="noResults" class="empty-state hidden"><p>No products match your search.</p></div>
    <table class="table" id="productsTable">
        <thead>
            <tr>
                <th>Parent SKU</th>
                <th>Title (EN)</th>
                <th>Countries</th>
                <th>Testimonials</th>
                <th>Last Sync</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody id="productsBody">
        <?php foreach ($products as $p): ?>
            <tr data-sku="<?= h($p['parent_sku']) ?>" data-title="<?= h(strtolower($p['master_title'] ?? '')) ?>">
                <td><code class="sku-cell"><?= h($p['parent_sku']) ?></code></td>
                <td><?= h($p['master_title'] ?? '—') ?></td>
                <td><span class="badge"><?= (int) $p['country_count'] ?></span></td>
                <td><span class="badge badge-blue"><?= (int) $p['testimonial_count'] ?></span></td>
                <td class="text-muted text-sm"><?= $p['last_sync'] ? h(substr($p['last_sync'], 0, 16)) : 'Never' ?></td>
                <td>
                    <a href="/?action=landings&sku=<?= urlencode($p['parent_sku']) ?>"
                       class="btn btn-sm btn-primary">View Landings</a>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<script>
const ALL_PRODUCTS = <?= json_encode($products) ?>;
const TOTAL_COUNT  = <?= count($products) ?>;
</script>

<?php renderFooter(); ?>
