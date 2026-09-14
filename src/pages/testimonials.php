<?php

$db         = Database::get();
$landingId  = (int) ($_GET['landing_id'] ?? 0);

if (!$landingId) {
    header('Location: ' . APP_BASE . '/');
    exit;
}

// Get landing info
$stmtL = $db->prepare('SELECT * FROM landings WHERE id = ?');
$stmtL->execute([$landingId]);
$landing = $stmtL->fetch();

if (!$landing) {
    header('Location: ' . APP_BASE . '/');
    exit;
}

// Get testimonials with images
$sql = "SELECT t.*,
               GROUP_CONCAT(ti.id       ORDER BY ti.sort_order SEPARATOR ',') AS img_ids,
               GROUP_CONCAT(ti.filename ORDER BY ti.sort_order SEPARATOR ',') AS img_files,
               GROUP_CONCAT(ti.thumbnail ORDER BY ti.sort_order SEPARATOR ',') AS img_thumbs
        FROM testimonials t
        LEFT JOIN testimonial_images ti ON ti.testimonial_id = t.id
        WHERE t.landing_id = ?
        GROUP BY t.id
        ORDER BY t.sort_order ASC, t.id ASC";

$stmtT = $db->prepare($sql);
$stmtT->execute([$landingId]);
$testimonials = $stmtT->fetchAll();

renderHeader('Testimonials — ' . $landing['country']);
?>

<div class="breadcrumb">
    <a href="<?= APP_BASE ?>/">Products</a> &rsaquo;
    <a href="<?= APP_BASE ?>/?action=landings&sku=<?= urlencode($landing['parent_sku']) ?>"><?= h($landing['parent_sku']) ?></a>
    &rsaquo; <?= h(strtoupper($landing['country'])) ?>
</div>

<div class="page-header">
    <div>
        <h2><?= h($landing['parent_sku']) ?> — <?= h(strtoupper($landing['country'])) ?></h2>
        <?php if ($landing['landing_url']): ?>
            <a href="<?= h($landing['landing_url']) ?>" target="_blank" class="text-muted text-sm link-external">
                <?= h($landing['landing_url']) ?>
            </a>
        <?php endif; ?>
    </div>
    <button class="btn btn-primary" onclick="openModal()">+ Add Testimonial</button>
</div>

<?php if (empty($testimonials)): ?>
    <div class="empty-state">
        <p>No testimonials yet. Add the first one!</p>
    </div>
<?php else: ?>
    <div class="bulk-bar hidden" id="bulkBar">
        <span id="bulkCount">0 selected</span>
        <button class="btn btn-sm btn-success" onclick="bulkAction('activate')">Activate</button>
        <button class="btn btn-sm btn-outline" onclick="bulkAction('deactivate')">Deactivate</button>
        <button class="btn btn-sm btn-danger" onclick="bulkAction('delete')">Delete</button>
        <button class="btn btn-sm btn-outline" onclick="clearSelection()">Clear</button>
    </div>
    <div class="table-info"><?= count($testimonials) ?> testimonials — drag rows to reorder</div>
    <table class="table" id="testimonialsTable">
        <thead>
            <tr>
                <th class="drag-col"></th>
                <th class="check-col"><input type="checkbox" id="selectAll" onchange="toggleSelectAll(this)"></th>
                <th>Author</th>
                <th>Rating</th>
                <th>Gender</th>
                <th>Text</th>
                <th>Images</th>
                <th>Active</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody id="sortableBody">
        <?php foreach ($testimonials as $t): ?>
            <?php
            $imgFiles  = $t['img_files']  ? explode(',', $t['img_files'])  : [];
            $imgThumbs = $t['img_thumbs'] ? explode(',', $t['img_thumbs']) : [];
            $imgIds    = $t['img_ids']    ? explode(',', $t['img_ids'])    : [];
            ?>
            <tr data-id="<?= (int) $t['id'] ?>" class="sortable-row">
                <td class="drag-handle" title="Drag to reorder">&#9776;</td>
                <td><input type="checkbox" class="row-check" value="<?= (int) $t['id'] ?>" onchange="updateBulkBar()"></td>
                <td>
                    <strong><?= h($t['author_name']) ?></strong>
                    <?php if ($t['url']): ?>
                        <a href="<?= h($t['url']) ?>" target="_blank" class="link-external text-sm block">link</a>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($t['rating']): ?>
                        <span class="stars"><?= str_repeat('★', (int)$t['rating']) ?><?= str_repeat('☆', 5 - (int)$t['rating']) ?></span>
                    <?php else: ?>
                        <span class="text-muted">—</span>
                    <?php endif; ?>
                </td>
                <td><?= $t['gender'] ? h(ucfirst($t['gender'])) : '<span class="text-muted">—</span>' ?></td>
                <td class="text-truncate" title="<?= h($t['text']) ?>"><?= h(mb_substr($t['text'], 0, 80)) ?><?= mb_strlen($t['text']) > 80 ? '…' : '' ?></td>
                <td>
                    <?php foreach (array_slice($imgThumbs, 0, 3) as $thumb): ?>
                        <?php if ($thumb): ?>
                            <img src="<?= h(UPLOAD_URL . $thumb) ?>" class="thumb-mini" alt="">
                        <?php endif; ?>
                    <?php endforeach; ?>
                    <?php if (count($imgFiles) === 0): ?>
                        <span class="text-muted text-sm">—</span>
                    <?php endif; ?>
                </td>
                <td>
                    <label class="toggle">
                        <input type="checkbox" <?= $t['is_active'] ? 'checked' : '' ?>
                               onchange="toggleActive(<?= (int)$t['id'] ?>, this.checked)">
                        <span class="toggle-slider"></span>
                    </label>
                </td>
                <td class="actions-cell">
                    <button class="btn btn-sm btn-outline"
                            onclick='editTestimonial(<?= json_encode($t) ?>, <?= json_encode($imgIds) ?>, <?= json_encode($imgFiles) ?>)'>
                        Edit
                    </button>
                    <button class="btn btn-sm btn-danger"
                            onclick="deleteTestimonial(<?= (int)$t['id'] ?>)">
                        Delete
                    </button>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<!-- Modal -->
<div id="modal" class="modal-overlay hidden">
    <div class="modal">
        <div class="modal-header">
            <h3 id="modalTitle">Add Testimonial</h3>
            <button class="modal-close" onclick="closeModal()">&times;</button>
        </div>
        <div class="modal-body">
            <form id="testimonialForm" onsubmit="saveTestimonial(event)">
                <input type="hidden" id="tId" name="id" value="">
                <input type="hidden" name="landing_id" value="<?= $landingId ?>">

                <div class="form-row">
                    <div class="form-group flex-1">
                        <label>Author Name *</label>
                        <div class="input-with-btn">
                            <input type="text" name="author_name" id="fAuthor" class="form-control" required maxlength="200">
                            <button type="button" class="btn btn-sm btn-ai" onclick="aiGenerateName()" title="AI: generate random name">AI</button>
                        </div>
                    </div>
                    <div class="form-group w-120">
                        <label>Gender</label>
                        <select name="gender" id="fGender" class="form-control">
                            <option value="">—</option>
                            <option value="male">Male</option>
                            <option value="female">Female</option>
                            <option value="other">Other</option>
                        </select>
                    </div>
                </div>

                <div class="form-group">
                    <label>
                        Testimonial Text * <span class="text-muted text-sm">(max 2000 chars)</span>
                        <button type="button" class="btn btn-sm btn-ai" style="margin-left:8px"
                                onclick="aiTranslate()" title="AI: mock translate to target language">AI Translate</button>
                        <select id="translateLang" class="form-control-inline">
                            <option value="IT">IT</option>
                            <option value="DE">DE</option>
                            <option value="FR">FR</option>
                        </select>
                    </label>
                    <textarea name="text" id="fText" class="form-control" rows="5"
                              maxlength="2000" required oninput="updateCharCount(this)"></textarea>
                    <div class="char-count"><span id="charCount">0</span>/2000</div>
                </div>

                <div class="form-row">
                    <div class="form-group flex-1">
                        <label>Rating</label>
                        <div class="star-picker" id="starPicker">
                            <?php for ($i = 1; $i <= 5; $i++): ?>
                                <span class="star" data-val="<?= $i ?>" onclick="setRating(<?= $i ?>)">&#9733;</span>
                            <?php endfor; ?>
                        </div>
                        <input type="hidden" name="rating" id="fRating" value="">
                        <label class="checkbox-label">
                            <input type="checkbox" id="randomRating" onchange="toggleRandomRating(this)">
                            Random rating
                        </label>
                    </div>
                    <div class="form-group flex-1">
                        <label>URL / Link</label>
                        <input type="url" name="url" id="fUrl" class="form-control" placeholder="https://...">
                    </div>
                </div>

                <div class="form-group">
                    <label>
                        <input type="checkbox" name="is_active" id="fActive" value="1" checked>
                        Active
                    </label>
                </div>

                <div class="form-group">
                    <label>Images <span class="text-muted text-sm">(JPG/PNG/WebP, max 5MB each)</span></label>
                    <input type="file" name="images[]" id="fImages" class="form-control"
                           accept=".jpg,.jpeg,.png,.webp" multiple onchange="previewImages(this)">
                    <div id="imagePreview" class="image-preview-grid"></div>
                    <div id="existingImages" class="image-preview-grid"></div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-outline" onclick="closeModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="saveBtn">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="changelog-section">
    <button class="btn btn-outline btn-sm" onclick="toggleChangeLog()" id="changeLogBtn">
        Show Change Log
    </button>
    <div id="changeLogPanel" class="hidden">
        <h4 style="margin:16px 0 10px">Change Log</h4>
        <table class="table" id="changeLogTable">
            <thead>
                <tr>
                    <th>Time</th>
                    <th>User</th>
                    <th>Action</th>
                    <th>Testimonial ID</th>
                    <th>Details</th>
                </tr>
            </thead>
            <tbody id="changeLogBody">
                <tr><td colspan="5" class="text-muted" style="text-align:center">Loading…</td></tr>
            </tbody>
        </table>
    </div>
</div>

<script>const LANDING_ID = <?= $landingId ?>;</script>
<?php renderFooter(); ?>
