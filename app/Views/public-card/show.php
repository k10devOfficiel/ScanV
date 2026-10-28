<?php
/** @var \App\Models\PublicCardView $cardView */

$initials = '';
$parts = preg_split('/\s+/', trim($cardView->displayName));
foreach ($parts as $p) {
    if (!empty($p)) {
        $initials .= mb_strtoupper(mb_substr($p, 0, 1));
        if (mb_strlen($initials) >= 2) break;
    }
}
if ($initials === '') $initials = 'SV';
?>

<div class="public-card-container" style="width:100%;max-width:440px;margin:0 auto;min-height:100vh;box-sizing:border-box;background:#F8FAFC;padding:40px 24px 24px;display:flex;flex-direction:column;align-items:center;gap:12px">
    <!-- Avatar / Photo or Initials Badge -->
    <?php if ($cardView->photoUrl !== null): ?>
        <img src="<?= e($cardView->photoUrl) ?>" alt="Photo de <?= e($cardView->displayName) ?>" style="width:104px;height:104px;border-radius:52px;object-fit:cover;border:2px solid #E2E8F0;box-shadow:0 4px 14px rgba(15,23,42,0.08)">
    <?php else: ?>
        <div style="width:104px;height:104px;border-radius:52px;background:#E0E7FF;color:#4338CA;font:700 36px Poppins;display:flex;align-items:center;justify-content:center;box-shadow:0 4px 14px rgba(15,23,42,0.08)">
            <?= e($initials) ?>
        </div>
    <?php endif; ?>

    <!-- Name & Titles -->
    <h1 style="font-size:26px;margin:8px 0 0 0;text-align:center"><?= e($cardView->displayName) ?></h1>
    
    <?php if ($cardView->jobTitle !== null): ?>
        <div style="color:#475569;font-size:16px;text-align:center">
            <?= e($cardView->jobTitle) ?><?= $cardView->department !== null ? ' · ' . e($cardView->department) : '' ?>
        </div>
    <?php endif; ?>

    <?php if ($cardView->companyName !== null): ?>
        <div style="font-weight:600;font-size:16px;text-align:center"><?= e($cardView->companyName) ?></div>
    <?php endif; ?>

    <?php if ($cardView->logoUrl !== null): ?>
        <div style="display:flex;justify-content:center;margin:6px 0">
            <img src="<?= e($cardView->logoUrl) ?>" alt="Logo <?= e($cardView->companyName ?? '') ?>" style="max-height:48px;max-width:140px;object-fit:contain">
        </div>
    <?php endif; ?>

    <!-- Actions Stack -->
    <div style="width:100%;display:flex;flex-direction:column;gap:12px;margin-top:20px">
        <!-- 1. Enregistrer le contact (Primary CTA) -->
        <a class="b p" href="<?= e($cardView->vcardUrl) ?>" id="save-contact-btn" style="min-height:56px;font-size:17px">
            Enregistrer le contact
        </a>

        <!-- 2. Appeler (if public) -->
        <?php if ($cardView->phoneTelUri !== null && $cardView->phoneDisplay !== null): ?>
            <a class="b" href="<?= e($cardView->phoneTelUri) ?>" style="min-height:56px;font-size:17px;display:flex;flex-direction:column;gap:2px;justify-content:center;text-align:center">
                <span>Appeler</span>
                <span style="font-size:13px;font-weight:400;color:#475569"><?= e($cardView->phoneDisplay) ?></span>
            </a>
        <?php endif; ?>

        <!-- 3. Envoyer un e-mail (if public) -->
        <?php if ($cardView->emailMailtoUri !== null && $cardView->email !== null): ?>
            <a class="b" href="<?= e($cardView->emailMailtoUri) ?>" style="min-height:56px;font-size:17px;display:flex;flex-direction:column;gap:2px;justify-content:center;text-align:center">
                <span>Envoyer un e-mail</span>
                <span style="font-size:13px;font-weight:400;color:#475569"><?= e($cardView->email) ?></span>
            </a>
        <?php endif; ?>

        <!-- 4. Site web (if public) -->
        <?php if ($cardView->companyWebsite !== null): ?>
            <a class="b" href="<?= e($cardView->companyWebsite) ?>" target="_blank" rel="noopener noreferrer" style="min-height:56px;font-size:17px">
                Site web
            </a>
        <?php endif; ?>

        <!-- 5. Dynamic Links (LinkedIn, GitHub, etc.) -->
        <?php if (!empty($cardView->links)): ?>
            <?php foreach ($cardView->links as $link): ?>
                <?php
                $lbl = is_array($link) ? ($link['label'] ?? '') : ($link->label ?? '');
                $href = is_array($link) ? ($link['url'] ?? '') : ($link->url ?? '');
                ?>
                <a class="b" href="<?= e($href) ?>" target="_blank" rel="noopener noreferrer" style="min-height:56px;font-size:17px">
                    <?= e($lbl) ?>
                </a>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- ScanV Branding Footer -->
    <div style="margin-top:auto;padding-top:32px;display:flex;align-items:center;gap:8px;color:#475569;font-size:13px">
        <svg width="22" height="22" viewBox="0 0 21 21" aria-hidden="true" shape-rendering="crispEdges" fill="var(--color-primary, #4338CA)">
            <path fill-rule="evenodd" d="M0 0h7v7H0zM1 1v5h5V1zM2 2h3v3H2zM14 0h7v7h-7zM15 1v5h5V1zM16 2h3v3h-3zM0 14h7v7H0zM1 15v5h5v-5zM2 16h3v3H2z"></path>
            <path d="M8 9h1v3H8zM12 9h1v3h-1zM9 12h1v1H9zM11 12h1v1h-1zM10 13h1v1h-1zM15 15h1v1h-1zM17 17h1v1h-1zM19 15h1v1h-1zM9 16h1v1H9zM12 18h2v1h-2z"></path>
        </svg>
        <span>Créé avec ScanV</span>
    </div>
</div>
