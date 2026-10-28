<?php
/**
 * Gabarit de Carte Physique ScanV - Recto (85 × 55 mm - 1004 × 650 px à 300 DPI)
 * Thème : default
 */
?>
<?php if (!empty($standalone)): ?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($displayName) ?> - Carte Recto</title>
    <?php if (!empty($inlineCss)): ?>
        <style><?= $inlineCss ?></style>
    <?php else: ?>
        <link rel="stylesheet" href="<?= e(url('/assets/css/card-default.css')) ?>">
        <?php if (!empty($theme) && $theme !== 'default'): ?>
            <link rel="stylesheet" href="<?= e(url('/assets/css/card-' . $theme . '.css')) ?>">
        <?php endif; ?>
    <?php endif; ?>
    <!-- DESIGN SLOT: HEAD_ADDITIONS -->
</head>
<body class="<?= !empty($inlineCss) ? 'scanv-export-body' : 'scanv-preview-body' ?>">
<?php endif; ?>

<!-- DESIGN SLOT: RECTO_BEFORE_CARD -->
<div class="scanv-card scanv-card-front scanv-theme-<?= e($theme ?? 'default') ?> no-photo">
    <!-- Stepped Accent Border with Horizontal Tabs & Straight Diagonal Navy Section (Detail 1) -->
    <svg class="scanv-front-bg-svg" viewBox="0 0 1004 650" preserveAspectRatio="none" aria-hidden="true">
        <!-- Layer 1: Yellow / Amber (Topmost tab, starts at y=45) -->
        <polygon class="scanv-stripe-yellow" points="662.5,45 706.5,45 390,650 346,650" fill="var(--card-stripe-yellow, #F59E0B)"/>
        <!-- Layer 2: Orange / Coral (Second tab, starts at y=80) -->
        <polygon class="scanv-stripe-orange" points="655.2,80 688.2,80 390,650 357,650" fill="var(--card-stripe-orange, #EA580C)"/>
        <!-- Layer 3: Deep Blue (Third tab, starts at y=115) -->
        <polygon class="scanv-stripe-deepblue" points="647.8,115 669.8,115 390,650 368,650" fill="var(--card-stripe-deepblue, #1E40AF)"/>
        <!-- Layer 4: Sky Blue (Fourth tab, starts at y=150) -->
        <polygon class="scanv-stripe-skyblue" points="640.5,150 651.5,150 390,650 379,650" fill="var(--card-stripe-skyblue, #0284C7)"/>
        <!-- Dark Navy Section (Straight continuous diagonal edge from top to bottom) -->
        <polygon class="scanv-bg-dark-poly" points="730,0 1004,0 1004,650 390,650" fill="var(--card-verso-bg, #0F172A)"/>
    </svg>

    <!-- Authentic Two-Tone SIP Flame Watermark (Detail 2) -->
    <div class="scanv-front-watermark" aria-hidden="true">
        <svg viewBox="0 0 100 125" class="scanv-watermark-flame-svg" width="165" height="205">
            <!-- Outer flame curl (crimson / reddish-coral) -->
            <path class="flame-outer" d="M50 0 C64 20 84 45 84 74 C84 104 64 122 44 122 C24 122 12 104 15 80 C18 64 28 60 30 72 C25 86 34 104 46 104 C62 104 68 86 66 68 C64 48 54 24 50 0 Z"/>
            <!-- Inner flame drop (warm golden amber) -->
            <path class="flame-inner" d="M44 26 C52 42 62 58 62 78 C62 94 52 104 42 104 C32 104 24 92 26 78 C28 64 36 44 44 26 Z"/>
        </svg>
    </div>

    <!-- Right Contact Details Stack (Right-aligned with crisp icons) -->
    <div class="scanv-front-right-panel">
        <div class="scanv-front-contacts-stack">
            <?php if (!empty($phoneDisplay)): ?>
                <div class="scanv-front-contact-row">
                    <span class="scanv-contact-val truncate"><?= e($phoneDisplay) ?></span>
                    <span class="scanv-contact-icon" title="Téléphone">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M6.62 10.79a15.053 15.053 0 006.59 6.59l2.2-2.2a1 1 0 011.02-.24c1.12.37 2.33.57 3.57.57a1 1 0 011 1V20a1 1 0 01-1 1A17 17 0 013 4a1 1 0 011-1h3.5a1 1 0 011 1c0 1.25.2 2.45.57 3.57a1 1 0 01-.25 1.02l-2.2 2.2z"/></svg>
                    </span>
                </div>
            <?php endif; ?>

            <?php if (!empty($email)): ?>
                <div class="scanv-front-contact-row">
                    <span class="scanv-contact-val truncate"><?= e($email) ?></span>
                    <span class="scanv-contact-icon" title="Email">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/></svg>
                    </span>
                </div>
            <?php endif; ?>

            <?php if (!empty($companyWebsite)): ?>
                <div class="scanv-front-contact-row">
                    <span class="scanv-contact-val truncate"><?= e($companyWebsite) ?></span>
                    <span class="scanv-contact-icon" title="Site Web">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 17.93c-3.95-.49-7-3.85-7-7.93 0-.62.08-1.21.21-1.79L9 15v1c0 1.1.9 2 2 2v1.93zm6.9-2.54c-.26-.81-1-1.39-1.9-1.39h-1v-3c0-.55-.45-1-1-1H8v-2h2c.55 0 1-.45 1-1V7h2c1.1 0 2-.9 2-2v-.41c2.93 1.19 5 4.06 5 7.41 0 2.08-.8 3.97-2.1 5.39z"/></svg>
                    </span>
                </div>
            <?php endif; ?>

            <?php if (!empty($companyAddress)): ?>
                <div class="scanv-front-contact-row">
                    <span class="scanv-contact-val truncate clamp-2" title="<?= e($companyAddress) ?>"><?= e($companyAddress) ?></span>
                    <span class="scanv-contact-icon" title="Adresse">
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5a2.5 2.5 0 010-5 2.5 2.5 0 010 5z"/></svg>
                    </span>
                </div>
            <?php endif; ?>

            <?php if (!empty($links)): ?>
                <?php foreach (array_slice($links, 0, 2) as $link): ?>
                    <div class="scanv-front-contact-row">
                        <span class="scanv-contact-val truncate"><?= e($link['label'] ?? '') ?><?= (!empty($link['label']) && !empty($link['url'])) ? ' : ' : '' ?><?= e($link['url'] ?? '') ?></span>
                        <span class="scanv-contact-icon" title="Lien">
                            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M3.9 12c0-1.71 1.39-3.1 3.1-3.1h4V7H7c-2.76 0-5 2.24-5 5s2.24 5 5 5h4v-1.9H7c-1.71 0-3.1-1.39-3.1-3.1zM8 13h8v-2H8v2zm9-6h-4v1.9h4c1.71 0 3.1 1.39 3.1 3.1s-1.39 3.1-3.1 3.1h-4V17h4c2.76 0 5-2.24 5-5s-2.24-5-5-5z"/></svg>
                        </span>
                    </div>
                <?php endforeach; ?>
            <?php endif; ?>
        </div>
    </div>

    <!-- Left White/Light Panel (Identity & Brand) -->
    <div class="scanv-front-left-panel">
        <!-- Top-Left: Logo & Company -->
        <div class="scanv-front-brand-block">
            <?php if (!empty($logoUrl)): ?>
                <img src="<?= e($logoUrl) ?>" alt="Logo" class="scanv-front-logo-img">
            <?php else: ?>
                <div class="scanv-front-default-brand">
                    <svg class="scanv-front-logo-icon" width="48" height="48" viewBox="0 0 21 21" aria-hidden="true" shape-rendering="crispEdges">
                        <path fill-rule="evenodd" d="M0 0h7v7H0zM1 1v5h5V1zM2 2h3v3H2zM14 0h7v7h-7zM15 1v5h5V1zM16 2h3v3h-3zM0 14h7v7H0zM1 15v5h5v-5zM2 16h3v3H2z"></path>
                        <path d="M8 9h1v3H8zM12 9h1v3h-1zM9 12h1v1H9zM11 12h1v1h-1zM10 13h1v1h-1zM15 15h1v1h-1zM17 17h1v1h-1zM19 15h1v1h-1zM9 16h1v1H9zM12 18h2v1h-2z"></path>
                    </svg>
                </div>
            <?php endif; ?>

            <?php if (!empty($companyName)): ?>
                <div class="scanv-front-company truncate" title="<?= e($companyName) ?>">
                    <?= e($companyName) ?>
                </div>
            <?php endif; ?>
        </div>

        <!-- Bottom-Left: Name & Job Title / Department -->
        <div class="scanv-front-person-block">
            <div class="scanv-front-name truncate clamp-2" title="<?= e($displayName) ?>">
                <?= e($displayName) ?>
            </div>

            <?php
            $subDetails = [];
            if (!empty($jobTitle)) $subDetails[] = $jobTitle;
            if (!empty($department)) $subDetails[] = $department;
            $subline = implode(' · ', $subDetails);
            ?>
            <?php if ($subline !== ''): ?>
                <div class="scanv-front-role truncate clamp-2" title="<?= e($subline) ?>">
                    <?= e($subline) ?>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>
<!-- DESIGN SLOT: RECTO_AFTER_CARD -->

<?php if (!empty($standalone)): ?>
</body>
</html>
<?php endif; ?>
