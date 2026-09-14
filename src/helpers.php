<?php

function h(string $str): string
{
    return htmlspecialchars($str, ENT_QUOTES, 'UTF-8');
}

function jsonResponse(array $data, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

function generateFilename(string $extension): string
{
    return uniqid('img_', true) . '.' . strtolower($extension);
}

function createThumbnail(string $src, string $dest, int $maxW = 300, int $maxH = 300): bool
{
    $info = @getimagesize($src);
    if (!$info) return false;

    [$origW, $origH, $type] = $info;

    $image = match ($type) {
        IMAGETYPE_JPEG => imagecreatefromjpeg($src),
        IMAGETYPE_PNG  => imagecreatefrompng($src),
        IMAGETYPE_WEBP => imagecreatefromwebp($src),
        default        => false,
    };
    if (!$image) return false;

    $ratio = min($maxW / $origW, $maxH / $origH, 1);
    $newW  = (int)($origW * $ratio);
    $newH  = (int)($origH * $ratio);

    $thumb = imagecreatetruecolor($newW, $newH);

    if ($type === IMAGETYPE_PNG) {
        imagealphablending($thumb, false);
        imagesavealpha($thumb, true);
        $transparent = imagecolorallocatealpha($thumb, 0, 0, 0, 127);
        imagefill($thumb, 0, 0, $transparent);
    }

    imagecopyresampled($thumb, $image, 0, 0, 0, 0, $newW, $newH, $origW, $origH);

    $ok = match ($type) {
        IMAGETYPE_JPEG => imagejpeg($thumb, $dest, 85),
        IMAGETYPE_PNG  => imagepng($thumb, $dest),
        IMAGETYPE_WEBP => imagewebp($thumb, $dest, 85),
        default        => false,
    };

    imagedestroy($image);
    imagedestroy($thumb);

    return (bool) $ok;
}

function renderHeader(string $title = 'Testimonials Manager'): void
{
    $user = h(Auth::username());
    $safeTitle = h($title);
    echo <<<HTML
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{$safeTitle} — Testimonials Manager</title>
        <link rel="stylesheet" href="/assets/css/style.css">
    </head>
    <body>
    <nav class="navbar">
        <a href="/" class="navbar-brand">Testimonials Manager</a>
        <div class="navbar-user">
            <span class="navbar-username">{$user}</span>
            <a href="/?action=logout" class="btn btn-sm btn-outline-light">Logout</a>
        </div>
    </nav>
    <div class="container">
    HTML;
}

function renderFooter(): void
{
    echo <<<HTML
    </div><!-- /container -->
    <div id="toast" class="toast hidden"></div>
    <script src="/assets/js/app.js"></script>
    </body>
    </html>
    HTML;
}

function paginationLinks(int $total, int $perPage, int $current, array $extraParams = []): string
{
    $pages = (int) ceil($total / $perPage);
    if ($pages <= 1) return '';

    $html = '<div class="pagination">';
    for ($i = 1; $i <= $pages; $i++) {
        $params = array_merge($extraParams, ['page' => $i]);
        $qs     = http_build_query($params);
        $active = $i === $current ? ' active' : '';
        $html  .= "<a href=\"/?{$qs}\" class=\"page-link{$active}\">{$i}</a>";
    }
    $html .= '</div>';
    return $html;
}
