<?php

define('ROOT_DIR', is_dir(dirname(__DIR__) . '/src') ? dirname(__DIR__) : __DIR__);
require_once ROOT_DIR . '/src/config.php';
require_once ROOT_DIR . '/src/Database.php';
require_once ROOT_DIR . '/src/Auth.php';
require_once ROOT_DIR . '/src/helpers.php';

Auth::start();
if (!Auth::check()) {
    jsonResponse(['error' => 'Unauthorized'], 401);
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    if      ($action === 'sync')               handleSync();
    elseif  ($action === 'save_testimonial')   handleSaveTestimonial();
    elseif  ($action === 'delete_testimonial') handleDeleteTestimonial();
    elseif  ($action === 'reorder')            handleReorder();
    elseif  ($action === 'toggle_active')      handleToggleActive();
    elseif  ($action === 'delete_image')       handleDeleteImage();
    elseif  ($action === 'search_products')    handleSearchProducts();
    elseif  ($action === 'bulk_action')        handleBulkAction();
    elseif  ($action === 'ai_translate')       handleAiTranslate();
    elseif  ($action === 'ai_generate_name')   handleAiGenerateName();
    elseif  ($action === 'change_log')         handleChangeLog();
    else    jsonResponse(['error' => 'Unknown action'], 400);
} catch (Throwable $e) {
    jsonResponse(['error' => $e->getMessage()], 500);
}

// ─── Change log helper ────────────────────────────────────────────────────────

function logChange(string $action, int $entityId, array $details = [], ?int $landingId = null): void
{
    try {
        Database::get()->prepare(
            'INSERT INTO change_log (user_id, action, entity, entity_id, landing_id, details) VALUES (?,?,?,?,?,?)'
        )->execute([$_SESSION['user_id'] ?? null, $action, 'testimonial', $entityId, $landingId, json_encode($details) ?: null]);
    } catch (Throwable) {
        // log silently — never break the main request
    }
}

// ─── Live fuzzy product search ────────────────────────────────────────────────

function fuzzyScore(string $str, string $query): int
{
    $str   = strtolower($str);
    $query = strtolower($query);

    if ($str === $query)                    return 100;
    if (strpos($str, $query) !== false)     return 80;

    // Levenshtein: allow 1 error per 4 chars (min 1)
    $maxDist = max(1, (int) (strlen($query) / 4));
    if (levenshtein($str, $query) <= $maxDist) return 65;

    // Subsequence: all chars of query appear in order inside str
    $qi = 0;
    for ($si = 0; $si < strlen($str) && $qi < strlen($query); $si++) {
        if ($str[$si] === $query[$qi]) $qi++;
    }
    if ($qi === strlen($query)) return 40;

    return 0;
}

function handleSearchProducts(): void
{
    $q  = strtolower(trim($_GET['q'] ?? ''));
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

    $all = $db->query($sql)->fetchAll();

    if ($q === '') {
        jsonResponse(['products' => $all]);
        return;
    }

    // Score each product
    $scored = [];
    foreach ($all as $p) {
        $score = max(
            fuzzyScore($p['parent_sku'], $q),
            fuzzyScore(strtolower($p['master_title'] ?? ''), $q)
        );
        if ($score > 0) $scored[] = ['score' => $score, 'product' => $p];
    }

    usort($scored, fn($a, $b) => $b['score'] <=> $a['score']);

    jsonResponse(['products' => array_column($scored, 'product')]);
}

// ─── Sync from external API ───────────────────────────────────────────────────

function handleSync(): void
{
    $params = [];
    if (!empty($_GET['sku']))     $params['sku']     = $_GET['sku'];
    if (!empty($_GET['country'])) $params['country'] = $_GET['country'];
    $params['limit'] = 1000;

    $url = API_URL . ($params ? '?' . http_build_query($params) : '');

    $ctx = stream_context_create([
        'http' => [
            'header'  => "X-Api-Key: " . API_KEY . "\r\n",
            'timeout' => 30,
        ],
        'ssl' => ['verify_peer' => false],
    ]);

    $raw = @file_get_contents($url, false, $ctx);
    if ($raw === false) {
        jsonResponse(['error' => 'Failed to reach API'], 502);
    }

    $data = json_decode($raw, true);
    if (!is_array($data)) {
        jsonResponse(['error' => 'Invalid API response', 'raw' => substr($raw, 0, 300)], 502);
    }

    // The API might return {data: [...]} or a plain array
    $items = isset($data['data']) ? $data['data'] : $data;

    $db      = Database::get();
    $now     = date('Y-m-d H:i:s');
    $inserted = 0;
    $updated  = 0;

    $upsert = $db->prepare("
        INSERT INTO landings (id, parent_sku, country, is_master, title, description, landing_url, image, synced_at)
        VALUES (:id, :parent_sku, :country, :is_master, :title, :description, :landing_url, :image, :synced_at)
        ON DUPLICATE KEY UPDATE
            parent_sku  = VALUES(parent_sku),
            country     = VALUES(country),
            is_master   = VALUES(is_master),
            title       = VALUES(title),
            description = VALUES(description),
            landing_url = VALUES(landing_url),
            image       = VALUES(image),
            synced_at   = VALUES(synced_at)
    ");

    foreach ($items as $item) {
        if (empty($item['id'])) continue;

        $upsert->execute([
            ':id'          => (int) $item['id'],
            ':parent_sku'  => $item['parent_sku']   ?? $item['sku']  ?? '',
            ':country'     => $item['country']       ?? '',
            ':is_master'   => (int) ($item['is_master'] ?? ($item['country'] === 'EN' ? 1 : 0)),
            ':title'       => $item['title']         ?? null,
            ':description' => $item['description']   ?? null,
            ':landing_url' => $item['landing_url']   ?? $item['url'] ?? null,
            ':image'       => $item['image']         ?? null,
            ':synced_at'   => $now,
        ]);

        // rowCount: 1 = inserted, 2 = updated, 0 = no change
        $rc = $upsert->rowCount();
        if ($rc === 1) $inserted++;
        elseif ($rc === 2) $updated++;
    }

    jsonResponse([
        'success'  => true,
        'inserted' => $inserted,
        'updated'  => $updated,
        'total'    => count($items),
    ]);
}

// ─── Save testimonial (create or update) ──────────────────────────────────────

function handleSaveTestimonial(): void
{
    $id         = (int) ($_POST['id'] ?? 0);
    $landingId  = (int) ($_POST['landing_id'] ?? 0);
    $authorName = trim($_POST['author_name'] ?? '');
    $text       = trim($_POST['text'] ?? '');
    $rating     = (($_POST['rating'] ?? '') !== '') ? (int) $_POST['rating'] : null;
    $gender     = in_array($_POST['gender'] ?? '', ['male','female','other']) ? $_POST['gender'] : null;
    $url        = trim($_POST['url'] ?? '') ?: null;
    $isActive   = isset($_POST['is_active']) ? 1 : 0;

    // Validate
    $errors = [];
    if (!$landingId)            $errors[] = 'Missing landing_id.';
    if ($authorName === '')     $errors[] = 'Author name is required.';
    if ($text === '')           $errors[] = 'Text is required.';
    if (mb_strlen($text) > 2000) $errors[] = 'Text must be max 2000 characters.';
    if ($rating !== null && ($rating < 1 || $rating > 5)) $errors[] = 'Rating must be 1–5.';

    if ($errors) {
        jsonResponse(['errors' => $errors], 422);
    }

    $db = Database::get();

    if ($id) {
        // Update
        $stmt = $db->prepare("
            UPDATE testimonials
            SET author_name = ?, text = ?, rating = ?, gender = ?, url = ?, is_active = ?
            WHERE id = ? AND landing_id = ?
        ");
        $stmt->execute([$authorName, $text, $rating, $gender, $url, $isActive, $id, $landingId]);
        logChange('update', $id, ['author_name' => $authorName, 'rating' => $rating], $landingId);
    } else {
        // Get next sort_order
        $maxOrder = $db->prepare('SELECT COALESCE(MAX(sort_order),0)+1 FROM testimonials WHERE landing_id = ?');
        $maxOrder->execute([$landingId]);
        $sortOrder = (int) $maxOrder->fetchColumn();

        $stmt = $db->prepare("
            INSERT INTO testimonials (landing_id, author_name, text, rating, gender, url, is_active, sort_order)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([$landingId, $authorName, $text, $rating, $gender, $url, $isActive, $sortOrder]);
        $id = (int) $db->lastInsertId();
        logChange('create', $id, ['author_name' => $authorName], $landingId);
    }

    // Handle image uploads
    $uploadedImages = [];
    if (!empty($_FILES['images']['name'][0])) {
        if (!is_dir(UPLOAD_DIR)) mkdir(UPLOAD_DIR, 0777, true);

        $allowed = ['image/jpeg','image/png','image/webp'];
        $files   = $_FILES['images'];
        $count   = count($files['name']);

        for ($i = 0; $i < $count; $i++) {
            if ($files['error'][$i] !== UPLOAD_ERR_OK) continue;
            if (!in_array($files['type'][$i], $allowed)) continue;
            if ($files['size'][$i] > UPLOAD_MAX_SIZE) continue;

            $ext      = strtolower(pathinfo($files['name'][$i], PATHINFO_EXTENSION));
            $filename = generateFilename($ext);
            $thumbname = 'thumb_' . $filename;

            $destFull  = UPLOAD_DIR . '/' . $filename;
            $destThumb = UPLOAD_DIR . '/' . $thumbname;

            if (!move_uploaded_file($files['tmp_name'][$i], $destFull)) continue;

            createThumbnail($destFull, $destThumb);

            // Get next image sort_order
            $maxImg = $db->prepare('SELECT COALESCE(MAX(sort_order),0)+1 FROM testimonial_images WHERE testimonial_id = ?');
            $maxImg->execute([$id]);
            $imgOrder = (int) $maxImg->fetchColumn();

            $ins = $db->prepare('INSERT INTO testimonial_images (testimonial_id, filename, thumbnail, sort_order) VALUES (?,?,?,?)');
            $ins->execute([$id, $filename, $thumbname, $imgOrder]);

            $uploadedImages[] = [
                'id'        => (int) $db->lastInsertId(),
                'filename'  => $filename,
                'thumbnail' => $thumbname,
                'url'       => UPLOAD_URL . $filename,
                'thumb_url' => UPLOAD_URL . $thumbname,
            ];
        }
    }

    jsonResponse(['success' => true, 'id' => $id, 'images' => $uploadedImages]);
}

// ─── Delete testimonial ───────────────────────────────────────────────────────

function handleDeleteTestimonial(): void
{
    $id = (int) ($_POST['id'] ?? 0);
    if (!$id) jsonResponse(['error' => 'Missing id'], 400);

    $db = Database::get();

    // Delete associated images from disk
    $imgs = $db->prepare('SELECT filename, thumbnail FROM testimonial_images WHERE testimonial_id = ?');
    $imgs->execute([$id]);
    foreach ($imgs->fetchAll() as $img) {
        @unlink(UPLOAD_DIR . '/' . $img['filename']);
        if ($img['thumbnail']) @unlink(UPLOAD_DIR . '/' . $img['thumbnail']);
    }

    // Fetch landing_id before deleting
    $lStmt = $db->prepare('SELECT landing_id FROM testimonials WHERE id = ?');
    $lStmt->execute([$id]);
    $lId = (int) ($lStmt->fetchColumn() ?: 0);

    $db->prepare('DELETE FROM testimonials WHERE id = ?')->execute([$id]);
    logChange('delete', $id, [], $lId ?: null);
    jsonResponse(['success' => true]);
}

// ─── Reorder testimonials ─────────────────────────────────────────────────────

function handleReorder(): void
{
    $ids = $_POST['ids'] ?? [];
    if (!is_array($ids)) jsonResponse(['error' => 'ids must be array'], 400);

    $db   = Database::get();
    $stmt = $db->prepare('UPDATE testimonials SET sort_order = ? WHERE id = ?');

    foreach (array_values($ids) as $order => $id) {
        $stmt->execute([$order, (int) $id]);
    }

    jsonResponse(['success' => true]);
}

// ─── Toggle active ────────────────────────────────────────────────────────────

function handleToggleActive(): void
{
    $id     = (int) ($_POST['id'] ?? 0);
    $active = (int) ($_POST['is_active'] ?? 0);
    if (!$id) jsonResponse(['error' => 'Missing id'], 400);

    Database::get()->prepare('UPDATE testimonials SET is_active = ? WHERE id = ?')->execute([$active, $id]);
    jsonResponse(['success' => true]);
}

// ─── Bulk actions ─────────────────────────────────────────────────────────────

function handleBulkAction(): void
{
    $bulkAction = $_POST['bulk_action'] ?? '';
    $ids = array_filter(array_map('intval', $_POST['ids'] ?? []));

    if (empty($ids))       jsonResponse(['error' => 'No IDs provided'], 400);
    if (!$bulkAction)      jsonResponse(['error' => 'No action specified'], 400);

    $db           = Database::get();
    $placeholders = implode(',', array_fill(0, count($ids), '?'));

    switch ($bulkAction) {
        case 'delete':
            // Fetch landing IDs before deleting
            $lMap = [];
            $lRows = $db->prepare("SELECT id, landing_id FROM testimonials WHERE id IN ($placeholders)");
            $lRows->execute($ids);
            foreach ($lRows->fetchAll() as $r) $lMap[$r['id']] = $r['landing_id'];

            $imgs = $db->prepare("SELECT filename, thumbnail FROM testimonial_images WHERE testimonial_id IN ($placeholders)");
            $imgs->execute($ids);
            foreach ($imgs->fetchAll() as $img) {
                @unlink(UPLOAD_DIR . '/' . $img['filename']);
                if ($img['thumbnail']) @unlink(UPLOAD_DIR . '/' . $img['thumbnail']);
            }
            $db->prepare("DELETE FROM testimonials WHERE id IN ($placeholders)")->execute($ids);
            foreach ($ids as $id) logChange('bulk_delete', $id, [], $lMap[$id] ?? null);
            break;

        case 'activate':
            $lRows2 = $db->prepare("SELECT id, landing_id FROM testimonials WHERE id IN ($placeholders)");
            $lRows2->execute($ids);
            $lMap2 = array_column($lRows2->fetchAll(), 'landing_id', 'id');
            $db->prepare("UPDATE testimonials SET is_active = 1 WHERE id IN ($placeholders)")->execute($ids);
            foreach ($ids as $id) logChange('bulk_activate', $id, [], $lMap2[$id] ?? null);
            break;

        case 'deactivate':
            $lRows3 = $db->prepare("SELECT id, landing_id FROM testimonials WHERE id IN ($placeholders)");
            $lRows3->execute($ids);
            $lMap3 = array_column($lRows3->fetchAll(), 'landing_id', 'id');
            $db->prepare("UPDATE testimonials SET is_active = 0 WHERE id IN ($placeholders)")->execute($ids);
            foreach ($ids as $id) logChange('bulk_deactivate', $id, [], $lMap3[$id] ?? null);
            break;

        default:
            jsonResponse(['error' => 'Unknown bulk action'], 400);
    }

    jsonResponse(['success' => true, 'count' => count($ids)]);
}

// ─── AI mock — translate ──────────────────────────────────────────────────────

function handleAiTranslate(): void
{
    $text = trim($_POST['text'] ?? '');
    $lang = strtoupper($_POST['lang'] ?? 'IT');
    if (!$text) jsonResponse(['error' => 'No text provided'], 400);

    $dicts = [
        'IT' => ['good'=>'buono','great'=>'ottimo','product'=>'prodotto','quality'=>'qualità',
                 'recommend'=>'raccomando','excellent'=>'eccellente','fast'=>'veloce',
                 'delivery'=>'consegna','satisfied'=>'soddisfatto','happy'=>'felice',
                 'amazing'=>'straordinario','perfect'=>'perfetto','love'=>'adoro',
                 'best'=>'migliore','very'=>'molto','really'=>'davvero','nice'=>'bello',
                 'works'=>'funziona','works well'=>'funziona bene','easy'=>'facile',
                 'price'=>'prezzo','value'=>'valore','would'=>'vorrei','use'=>'usare'],
        'DE' => ['good'=>'gut','great'=>'toll','product'=>'Produkt','quality'=>'Qualität',
                 'recommend'=>'empfehle','excellent'=>'ausgezeichnet','fast'=>'schnell',
                 'delivery'=>'Lieferung','satisfied'=>'zufrieden','happy'=>'glücklich',
                 'amazing'=>'erstaunlich','perfect'=>'perfekt','love'=>'liebe',
                 'best'=>'besten','very'=>'sehr','really'=>'wirklich','nice'=>'schön',
                 'easy'=>'einfach','price'=>'Preis','value'=>'Wert'],
        'FR' => ['good'=>'bon','great'=>'excellent','product'=>'produit','quality'=>'qualité',
                 'recommend'=>'recommande','excellent'=>'excellent','fast'=>'rapide',
                 'delivery'=>'livraison','satisfied'=>'satisfait','happy'=>'heureux',
                 'amazing'=>'incroyable','perfect'=>'parfait','love'=>'adore',
                 'best'=>'meilleur','very'=>'très','really'=>'vraiment','nice'=>'beau',
                 'easy'=>'facile','price'=>'prix','value'=>'valeur'],
    ];

    $dict       = $dicts[$lang] ?? $dicts['IT'];
    $translated = preg_replace_callback('/\b([a-zA-Z]+)\b/', function ($m) use ($dict) {
        return $dict[strtolower($m[1])] ?? $m[1];
    }, $text);

    jsonResponse(['success' => true, 'text' => "[AI:{$lang}] {$translated}"]);
}

// ─── AI mock — generate author name ──────────────────────────────────────────

function handleAiGenerateName(): void
{
    $gender = $_POST['gender'] ?? '';

    $male   = ['Marco','Luca','Giovanni','Andrea','Antonio','Giuseppe','Francesco',
               'Matteo','Roberto','Stefano','Alessandro','Davide','Riccardo','Mario','Paolo'];
    $female = ['Maria','Giulia','Chiara','Sofia','Laura','Francesca','Sara',
               'Valentina','Alessia','Martina','Federica','Silvia','Paola','Elena','Anna'];
    $last   = ['Rossi','Ferrari','Russo','Bianchi','Romano','Gallo','Costa',
               'Fontana','Conti','Esposito','Ricci','Bruno','De Luca','Moretti','Lombardi'];

    $first = match($gender) {
        'male'   => $male[array_rand($male)],
        'female' => $female[array_rand($female)],
        default  => (rand(0,1) ? $male : $female)[array_rand($male)],
    };

    jsonResponse(['success' => true, 'name' => $first . ' ' . $last[array_rand($last)]]);
}

// ─── Change log ───────────────────────────────────────────────────────────────

function handleChangeLog(): void
{
    $landingId = (int) ($_GET['landing_id'] ?? 0);
    if (!$landingId) jsonResponse(['error' => 'Missing landing_id'], 400);

    $db   = Database::get();
    $stmt = $db->prepare("
        SELECT cl.id, cl.action, cl.entity_id, cl.details, cl.created_at, u.username
        FROM change_log cl
        LEFT JOIN tm_users u ON u.id = cl.user_id
        WHERE cl.entity = 'testimonial'
          AND cl.landing_id = ?
        ORDER BY cl.created_at DESC
        LIMIT 100
    ");
    $stmt->execute([$landingId]);
    jsonResponse(['success' => true, 'logs' => $stmt->fetchAll()]);
}

// ─── Delete single image ──────────────────────────────────────────────────────

function handleDeleteImage(): void
{
    $id = (int) ($_POST['image_id'] ?? 0);
    if (!$id) jsonResponse(['error' => 'Missing image_id'], 400);

    $db   = Database::get();
    $stmt = $db->prepare('SELECT filename, thumbnail FROM testimonial_images WHERE id = ?');
    $stmt->execute([$id]);
    $img = $stmt->fetch();

    if ($img) {
        @unlink(UPLOAD_DIR . '/' . $img['filename']);
        if ($img['thumbnail']) @unlink(UPLOAD_DIR . '/' . $img['thumbnail']);
        $db->prepare('DELETE FROM testimonial_images WHERE id = ?')->execute([$id]);
    }

    jsonResponse(['success' => true]);
}
