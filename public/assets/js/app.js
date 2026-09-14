/* ── Toast ─────────────────────────────────────────────────── */
function showToast(msg, type = 'success') {
    const t = document.getElementById('toast');
    if (!t) return;
    t.textContent = msg;
    t.className = 'toast' + (type === 'error' ? ' error' : '');
    clearTimeout(t._timer);
    t._timer = setTimeout(() => t.className = 'toast hidden', 3500);
}

/* ── API helper ────────────────────────────────────────────── */
async function api(action, body = {}, method = 'POST') {
    const fd = new FormData();
    fd.append('action', action);
    for (const [k, v] of Object.entries(body)) {
        if (Array.isArray(v)) v.forEach(x => fd.append(k + '[]', x));
        else fd.append(k, v);
    }
    const res = await fetch('/api.php?action=' + action, { method, body: fd });
    return res.json();
}

/* ── Live fuzzy search ─────────────────────────────────────── */
function fuzzyScore(str, query) {
    str   = str.toLowerCase();
    query = query.toLowerCase();
    if (!query)               return 1;
    if (str === query)        return 100;
    if (str.includes(query))  return 80;

    // Levenshtein distance (max 1 typo per 4 chars)
    const maxDist = Math.max(1, Math.floor(query.length / 4));
    if (levenshtein(str, query) <= maxDist) return 65;

    // Subsequence: all chars of query appear in order in str
    let qi = 0;
    for (let si = 0; si < str.length && qi < query.length; si++) {
        if (str[si] === query[qi]) qi++;
    }
    if (qi === query.length) return 40;

    return 0;
}

function levenshtein(a, b) {
    const m = a.length, n = b.length;
    const dp = Array.from({length: m + 1}, (_, i) => Array.from({length: n + 1}, (_, j) => i || j));
    for (let i = 1; i <= m; i++)
        for (let j = 1; j <= n; j++)
            dp[i][j] = a[i-1] === b[j-1]
                ? dp[i-1][j-1]
                : 1 + Math.min(dp[i-1][j], dp[i][j-1], dp[i-1][j-1]);
    return dp[m][n];
}

function highlight(text, query) {
    if (!query) return escHtml(text);
    // Mark matching characters (subsequence)
    const lText  = text.toLowerCase();
    const lQuery = query.toLowerCase();
    let result = '', qi = 0;
    for (let si = 0; si < text.length; si++) {
        if (qi < lQuery.length && lText[si] === lQuery[qi]) {
            result += `<mark>${escHtml(text[si])}</mark>`;
            qi++;
        } else {
            result += escHtml(text[si]);
        }
    }
    return result;
}

function escHtml(str) {
    return String(str).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;');
}

function renderProductRows(products, query) {
    const tbody = document.getElementById('productsBody');
    if (!tbody) return;

    tbody.innerHTML = products.map(p => {
        const sku   = p.parent_sku;
        const title = p.master_title || '—';
        const sync  = p.last_sync ? p.last_sync.substring(0,16) : 'Never';
        return `<tr>
            <td><code>${highlight(sku, query)}</code></td>
            <td>${highlight(title === '—' ? '' : title, query) || '—'}</td>
            <td><span class="badge">${p.country_count|0}</span></td>
            <td><span class="badge badge-blue">${p.testimonial_count|0}</span></td>
            <td class="text-muted text-sm">${escHtml(sync)}</td>
            <td><a href="${APP_BASE}/?action=landings&sku=${encodeURIComponent(sku)}" class="btn btn-sm btn-primary">View Landings</a></td>
        </tr>`;
    }).join('');
}

(function initLiveSearch() {
    const input    = document.getElementById('skuSearch');
    const noRes    = document.getElementById('noResults');
    const info     = document.getElementById('visibleCount');
    if (!input) return;

    let timer = null;

    input.addEventListener('input', () => {
        clearTimeout(timer);
        timer = setTimeout(() => runSearch(input.value.trim()), 220);
    });

    function runSearch(q) {
        if (typeof ALL_PRODUCTS === 'undefined') return;

        let results;
        if (!q) {
            results = ALL_PRODUCTS;
        } else {
            const scored = ALL_PRODUCTS
                .map(p => ({
                    score: Math.max(
                        fuzzyScore(p.parent_sku, q),
                        fuzzyScore(p.master_title || '', q)
                    ),
                    p
                }))
                .filter(x => x.score > 0)
                .sort((a, b) => b.score - a.score);
            results = scored.map(x => x.p);
        }

        renderProductRows(results, q);
        if (info) info.textContent = results.length;
        if (noRes) noRes.classList.toggle('hidden', results.length > 0);
        const table = document.getElementById('productsTable');
        if (table) table.classList.toggle('hidden', results.length === 0);
    }
})();

/* ── Sync ──────────────────────────────────────────────────── */
async function syncApi() {
    const btn = document.getElementById('syncBtn');
    const status = document.getElementById('syncStatus');
    btn.disabled = true;
    btn.textContent = 'Syncing…';
    status.innerHTML = '<div class="alert alert-info">Syncing from API, please wait…</div>';

    try {
        const data = await api('sync');
        if (data.success) {
            status.innerHTML = `<div class="alert alert-success">
                Sync complete — ${data.inserted} inserted, ${data.updated} updated (total: ${data.total})
            </div>`;
            showToast('Sync successful!');
            setTimeout(() => location.reload(), 1200);
        } else {
            status.innerHTML = `<div class="alert alert-error">${data.error || 'Sync failed.'}</div>`;
            showToast(data.error || 'Sync failed.', 'error');
        }
    } catch (e) {
        status.innerHTML = '<div class="alert alert-error">Network error during sync.</div>';
        showToast('Network error.', 'error');
    }
    btn.disabled = false;
    btn.textContent = 'Sync from API';
}

/* ── Toggle active ─────────────────────────────────────────── */
async function toggleActive(id, isActive) {
    const data = await api('toggle_active', { id, is_active: isActive ? 1 : 0 });
    if (!data.success) showToast('Failed to update.', 'error');
}

/* ── Delete testimonial ────────────────────────────────────── */
async function deleteTestimonial(id) {
    if (!confirm('Delete this testimonial? This cannot be undone.')) return;
    const data = await api('delete_testimonial', { id });
    if (data.success) {
        showToast('Deleted.');
        document.querySelector(`tr[data-id="${id}"]`)?.remove();
    } else {
        showToast(data.error || 'Delete failed.', 'error');
    }
}

/* ── Modal ─────────────────────────────────────────────────── */
function openModal() {
    document.getElementById('modalTitle').textContent = 'Add Testimonial';
    document.getElementById('testimonialForm').reset();
    document.getElementById('tId').value = '';
    document.getElementById('existingImages').innerHTML = '';
    document.getElementById('imagePreview').innerHTML = '';
    setRating(0);
    updateCharCount(document.getElementById('fText'));
    document.getElementById('modal').classList.remove('hidden');
}

function closeModal() {
    document.getElementById('modal').classList.add('hidden');
}

function editTestimonial(t, imgIds, imgFiles) {
    document.getElementById('modalTitle').textContent = 'Edit Testimonial';
    document.getElementById('tId').value       = t.id;
    document.getElementById('fAuthor').value   = t.author_name;
    document.getElementById('fText').value     = t.text;
    document.getElementById('fGender').value   = t.gender || '';
    document.getElementById('fUrl').value      = t.url || '';
    document.getElementById('fActive').checked = t.is_active == 1;

    updateCharCount(document.getElementById('fText'));
    setRating(t.rating ? parseInt(t.rating) : 0);

    // Show existing images with delete buttons
    const existing = document.getElementById('existingImages');
    existing.innerHTML = '';
    if (imgIds && imgIds.length) {
        imgIds.forEach((imgId, i) => {
            if (!imgFiles[i]) return;
            const div = document.createElement('div');
            div.className = 'preview-item';
            div.innerHTML = `
                <img src="/uploads/${imgFiles[i]}" alt="">
                <button class="remove-img" type="button" onclick="deleteImage(${imgId}, this)">&#x2715;</button>
            `;
            existing.appendChild(div);
        });
    }

    document.getElementById('imagePreview').innerHTML = '';
    document.getElementById('modal').classList.remove('hidden');
}

// Close on overlay click
document.addEventListener('click', e => {
    if (e.target.id === 'modal') closeModal();
});

/* ── Save testimonial ──────────────────────────────────────── */
async function saveTestimonial(e) {
    e.preventDefault();
    const form = e.target;
    const btn  = document.getElementById('saveBtn');
    btn.disabled = true;
    btn.textContent = 'Saving…';

    const fd = new FormData(form);

    // Add random rating if checked
    if (document.getElementById('randomRating').checked) {
        fd.set('rating', Math.floor(Math.random() * 5) + 1);
    }

    try {
        const res  = await fetch('/api.php?action=save_testimonial', { method: 'POST', body: fd });
        const data = await res.json();

        if (data.success) {
            showToast('Saved successfully!');
            closeModal();
            setTimeout(() => location.reload(), 600);
        } else {
            const msgs = data.errors ? data.errors.join('\n') : (data.error || 'Save failed.');
            alert(msgs);
        }
    } catch (err) {
        alert('Network error.');
    }

    btn.disabled = false;
    btn.textContent = 'Save';
}

/* ── Star rating ───────────────────────────────────────────── */
function setRating(val) {
    document.getElementById('fRating').value = val || '';
    document.querySelectorAll('#starPicker .star').forEach((s, i) => {
        s.classList.toggle('active', i < val);
    });
}

function toggleRandomRating(cb) {
    const picker = document.getElementById('starPicker');
    picker.style.opacity = cb.checked ? '.3' : '1';
    picker.style.pointerEvents = cb.checked ? 'none' : '';
    if (cb.checked) setRating(0);
}

/* ── Char counter ──────────────────────────────────────────── */
function updateCharCount(el) {
    const counter = document.getElementById('charCount');
    if (counter) counter.textContent = el.value.length;
}

/* ── Image preview ─────────────────────────────────────────── */
function previewImages(input) {
    const grid = document.getElementById('imagePreview');
    grid.innerHTML = '';
    [...input.files].forEach(file => {
        const reader = new FileReader();
        reader.onload = ev => {
            const div = document.createElement('div');
            div.className = 'preview-item';
            div.innerHTML = `<img src="${ev.target.result}" alt="">`;
            grid.appendChild(div);
        };
        reader.readAsDataURL(file);
    });
}

/* ── Delete existing image ─────────────────────────────────── */
async function deleteImage(imageId, btn) {
    if (!confirm('Delete this image?')) return;
    const data = await api('delete_image', { image_id: imageId });
    if (data.success) {
        btn.closest('.preview-item').remove();
        showToast('Image deleted.');
    } else {
        showToast('Failed to delete image.', 'error');
    }
}

/* ── Bulk selection ────────────────────────────────────────── */
function toggleSelectAll(master) {
    document.querySelectorAll('.row-check').forEach(cb => cb.checked = master.checked);
    updateBulkBar();
}

function updateBulkBar() {
    const checked = document.querySelectorAll('.row-check:checked');
    const bar = document.getElementById('bulkBar');
    const master = document.getElementById('selectAll');
    const all = document.querySelectorAll('.row-check');

    document.getElementById('bulkCount').textContent = `${checked.length} selected`;
    bar.classList.toggle('hidden', checked.length === 0);
    if (master) master.indeterminate = checked.length > 0 && checked.length < all.length;
}

function clearSelection() {
    document.querySelectorAll('.row-check, #selectAll').forEach(cb => cb.checked = false);
    if (document.getElementById('selectAll')) document.getElementById('selectAll').indeterminate = false;
    updateBulkBar();
}

async function bulkAction(action) {
    const ids = [...document.querySelectorAll('.row-check:checked')].map(cb => cb.value);
    if (!ids.length) return;

    if (action === 'delete' && !confirm(`Delete ${ids.length} testimonial(s)? This cannot be undone.`)) return;

    const fd = new FormData();
    fd.append('action', 'bulk_action');
    fd.append('bulk_action', action);
    ids.forEach(id => fd.append('ids[]', id));

    const res  = await fetch('/api.php?action=bulk_action', { method: 'POST', body: fd });
    const data = await res.json();

    if (data.success) {
        showToast(`${data.count} testimonial(s) ${action}d.`);
        clearSelection();
        setTimeout(() => location.reload(), 600);
    } else {
        showToast(data.error || 'Bulk action failed.', 'error');
    }
}

/* ── AI mock ───────────────────────────────────────────────── */
async function aiGenerateName() {
    const gender = document.getElementById('fGender')?.value || '';
    const btn = event.target;
    btn.disabled = true;
    btn.textContent = '…';

    const fd = new FormData();
    fd.append('action', 'ai_generate_name');
    fd.append('gender', gender);

    const res  = await fetch('/api.php?action=ai_generate_name', { method: 'POST', body: fd });
    const data = await res.json();

    if (data.success) {
        document.getElementById('fAuthor').value = data.name;
        showToast(`Generated: ${data.name}`);
    } else {
        showToast('Name generation failed.', 'error');
    }
    btn.disabled = false;
    btn.textContent = 'AI';
}

async function aiTranslate() {
    const text = document.getElementById('fText')?.value?.trim();
    if (!text) { showToast('Enter text first.', 'error'); return; }

    const lang = document.getElementById('translateLang')?.value || 'IT';
    const btn = event.target;
    btn.disabled = true;
    btn.textContent = '…';

    const fd = new FormData();
    fd.append('action', 'ai_translate');
    fd.append('text', text);
    fd.append('lang', lang);

    const res  = await fetch('/api.php?action=ai_translate', { method: 'POST', body: fd });
    const data = await res.json();

    if (data.success) {
        document.getElementById('fText').value = data.text;
        updateCharCount(document.getElementById('fText'));
        showToast(`Translated to ${lang}.`);
    } else {
        showToast('Translation failed.', 'error');
    }
    btn.disabled = false;
    btn.textContent = 'AI Translate';
}

/* ── Change log ────────────────────────────────────────────── */
let changeLogLoaded = false;

async function toggleChangeLog() {
    const panel = document.getElementById('changeLogPanel');
    const btn   = document.getElementById('changeLogBtn');
    const hidden = panel.classList.toggle('hidden');
    btn.textContent = hidden ? 'Show Change Log' : 'Hide Change Log';

    if (!hidden && !changeLogLoaded) {
        changeLogLoaded = true;
        const res  = await fetch(`/api.php?action=change_log&landing_id=${LANDING_ID}`);
        const data = await res.json();
        const tbody = document.getElementById('changeLogBody');

        if (!data.logs || data.logs.length === 0) {
            tbody.innerHTML = '<tr><td colspan="5" class="text-muted" style="text-align:center">No changes logged yet.</td></tr>';
            return;
        }

        tbody.innerHTML = data.logs.map(l => {
            const details = l.details ? JSON.parse(l.details) : {};
            const detailStr = Object.entries(details).map(([k,v]) => `${k}: ${v}`).join(', ');
            const actionClass = 'log-' + l.action;
            return `<tr>
                <td class="text-sm text-muted">${l.created_at}</td>
                <td>${l.username || '—'}</td>
                <td><span class="log-action ${actionClass}">${l.action}</span></td>
                <td>#${l.entity_id}</td>
                <td class="text-sm text-muted">${detailStr || '—'}</td>
            </tr>`;
        }).join('');
    }
}

/* ── Drag & drop reorder ───────────────────────────────────── */
(function initSortable() {
    const tbody = document.getElementById('sortableBody');
    if (!tbody) return;

    let dragged = null;

    tbody.addEventListener('dragstart', e => {
        const row = e.target.closest('tr');
        if (!row) return;
        dragged = row;
        row.classList.add('dragging');
        e.dataTransfer.effectAllowed = 'move';
    });

    tbody.addEventListener('dragend', e => {
        const row = e.target.closest('tr');
        if (row) row.classList.remove('dragging');
        tbody.querySelectorAll('tr').forEach(r => r.classList.remove('drag-over'));
        dragged = null;
        saveOrder();
    });

    tbody.addEventListener('dragover', e => {
        e.preventDefault();
        e.dataTransfer.dropEffect = 'move';
        const row = e.target.closest('tr');
        if (!row || row === dragged) return;
        tbody.querySelectorAll('tr').forEach(r => r.classList.remove('drag-over'));
        row.classList.add('drag-over');
    });

    tbody.addEventListener('drop', e => {
        e.preventDefault();
        const row = e.target.closest('tr');
        if (!row || row === dragged) return;
        tbody.insertBefore(dragged, row);
    });

    // Make rows draggable via handle
    tbody.querySelectorAll('.sortable-row').forEach(row => {
        row.draggable = true;
    });

    async function saveOrder() {
        const ids = [...tbody.querySelectorAll('tr[data-id]')].map(r => r.dataset.id);
        const fd  = new FormData();
        fd.append('action', 'reorder');
        ids.forEach(id => fd.append('ids[]', id));
        await fetch('/api.php?action=reorder', { method: 'POST', body: fd });
        showToast('Order saved.');
    }
})();
