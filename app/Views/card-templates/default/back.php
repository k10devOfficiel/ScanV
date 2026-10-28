<?php
/**
 * Gabarit de Carte Physique ScanV - Verso (85 × 55 mm - 1004 × 650 px à 300 DPI)
 * Thème : default
 */
?>
<?php if (!empty($standalone)): ?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Carte Verso - ScanV</title>
    <?php if (!empty($inlineCss)): ?>
        <style><?= $inlineCss ?></style>
    <?php else: ?>
        <link rel="stylesheet" href="<?= e(url('/assets/css/card-default.css')) ?>">
        <?php if (!empty($theme) && $theme !== 'default'): ?>
            <link rel="stylesheet" href="<?= e(url('/assets/css/card-' . $theme . '.css')) ?>">
        <?php endif; ?>
    <?php endif; ?>
    <!-- DESIGN SLOT: HEAD_ADDITIONS_VERSO -->
</head>
<body class="<?= !empty($inlineCss) ? 'scanv-export-body' : 'scanv-preview-body' ?>">
<?php endif; ?>

<!-- DESIGN SLOT: VERSO_BEFORE_CARD -->
<div class="scanv-card scanv-card-back scanv-theme-<?= e($theme ?? 'default') ?>">
    <div class="scanv-card-decor" aria-hidden="true"></div>
    <!-- QR Code white box (Quiet Zone) -->
    <div class="scanv-back-qr-box">
        <?php if (!empty($qrSvg)): ?>
            <div style="width: 320px; height: 320px; display: flex; align-items: center; justify-content: center;">
                <?= $qrSvg ?>
            </div>
        <?php elseif (!empty($qrPngDataUri)): ?>
            <img src="<?= e($qrPngDataUri) ?>" alt="QR Code" class="scanv-back-qr-img" width="320" height="320">
        <?php else: ?>
            <img src="<?= e(url('/card/qr.png')) ?>" alt="QR Code" class="scanv-back-qr-img" width="320" height="320">
        <?php endif; ?>
    </div>

    <!-- Right side: Logo, Company Name, Address & CTA -->
    <div class="scanv-back-content">
        <div class="scanv-back-brand-block">
            <?php if (!empty($logoUrl)): ?>
                <img src="<?= e($logoUrl) ?>" alt="Logo" class="scanv-back-logo-img">
            <?php else: ?>
                <svg class="scanv-back-logo-icon" width="48" height="48" viewBox="0 0 21 21" aria-hidden="true" shape-rendering="crispEdges">
                    <path fill-rule="evenodd" d="M0 0h7v7H0zM1 1v5h5V1zM2 2h3v3H2zM14 0h7v7h-7zM15 1v5h5V1zM16 2h3v3h-3zM0 14h7v7H0zM1 15v5h5v-5zM2 16h3v3H2z"></path>
                    <path d="M8 9h1v3H8zM12 9h1v3h-1zM9 12h1v1H9zM11 12h1v1h-1zM10 13h1v1h-1zM15 15h1v1h-1zM17 17h1v1h-1zM19 15h1v1h-1zM9 16h1v1H9zM12 18h2v1h-2z"></path>
                </svg>
            <?php endif; ?>

            <div class="scanv-back-company">
                <?= e($companyName ?: 'SOCIETE IVORIENNE DE PETROLE') ?>
            </div>

            <div class="scanv-back-address">
                <?= e($companyAddress ?: 'abidjan, VGE face solibra immeuble la balance') ?>
            </div>
        </div>

        <div class="scanv-back-cta">
            Scannez pour enregistrer mon contact
        </div>
    </div>
</div>
<!-- DESIGN SLOT: VERSO_AFTER_CARD -->

<?php if (!empty($standalone)): ?>
</body>
</html>
<?php endif; ?>
