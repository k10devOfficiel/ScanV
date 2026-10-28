<?php
/** @var \App\Models\User $user */
/** @var \App\Models\BusinessCard|null $card */
/** @var \App\Models\CardVisibility|null $visibility */
?>

<div class="dashboard-wrapper">
    <!-- Flash Messages -->
    <?php if (!empty($flashSuccess)): ?>
        <div class="alert alert-success" role="alert" style="margin-bottom:20px">
            <?= e($flashSuccess) ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($flashError)): ?>
        <div class="alert alert-danger" role="alert" style="margin-bottom:20px">
            <?= e($flashError) ?>
        </div>
    <?php endif; ?>

    <!-- Title & Status -->
    <div class="dashboard-header-row">
        <div style="display:flex;align-items:center;flex-wrap:wrap;gap:10px">
            <h1 style="font-size:30px;margin:0">Ma carte</h1>
            <?php if ($card !== null && $card->isActive): ?>
                <span style="background:#DCFCE7;color:#166534;font-size:13px;font-weight:600;padding:4px 12px;border-radius:999px">Carte Active</span>
            <?php else: ?>
                <span style="background:#FEE2E2;color:#991B1B;font-size:13px;font-weight:600;padding:4px 12px;border-radius:999px">Carte Désactivée</span>
            <?php endif; ?>

            <?php if (!empty($theme)): ?>
                <span style="background:<?= e($theme['primary']) ?>14;color:<?= e($theme['primary']) ?>;border:1.5px solid <?= e($theme['primary']) ?>38;font-size:12.5px;font-weight:600;padding:4px 12px;border-radius:999px;display:inline-flex;align-items:center;gap:6px">
                    <span style="width:8px;height:8px;border-radius:50%;background:<?= e($theme['primary']) ?>"></span>
                    Modèle : <?= e($theme['name']) ?>
                </span>
            <?php endif; ?>
        </div>
    </div>

    <!-- Main Grid: Card Previews (left) + Action Buttons (right) -->
    <div class="dashboard-grid">
        <!-- Left: Card Previews Container -->
        <div class="dashboard-previews-container">
            <div class="dashboard-previews-inner">
                <?php if ($card !== null): ?>
                    <?php
                    $showPhoto = $visibility ? $visibility->isVisible('show_photo') : true;
                    $showJob = $visibility ? $visibility->isVisible('show_job_title') : true;
                    $showDept = $visibility ? $visibility->isVisible('show_department') : true;
                    $showCompany = $visibility ? $visibility->isVisible('show_company') : true;
                    $showPhone = $visibility ? $visibility->isVisible('show_phone') : true;
                    $showEmail = $visibility ? $visibility->isVisible('show_email') : true;
                    $showWebsite = $visibility ? $visibility->isVisible('show_website') : true;
                    $showAddress = $visibility ? $visibility->isVisible('show_company_address') : true;
                    $theme = $theme ?? \App\Services\CardThemeService::getTheme($card->themeKey);
                    ?>

                    <!-- Flip Control Bar -->
                    <div style="display:flex;justify-content:space-between;align-items:center;width:100%;max-width:490px;margin-bottom:6px">
                        <button type="button" class="b b-sm" id="btn-flip-dashboard" onclick="toggleDashboardCardFlip()" style="font-size:12.5px;display:inline-flex;align-items:center;gap:6px;min-height:34px;background:#EEF2FF;color:#4338CA;border-color:#C7D2FE">
                            🔄 Retourner la carte (3D)
                        </button>
                        <span style="font-size:11.5px;color:#64748B;font-style:italic">Cliquez sur la carte pour basculer</span>
                    </div>

                    <!-- 3D Interactive Flip Scene -->
                    <div class="card-3d-scene">
                        <div id="dashboard-card-flipper" class="card-3d-flipper" onclick="toggleDashboardCardFlip()" title="Cliquez pour retourner la carte en 3D">
                            
                            <!-- Front Card Face (3D Face Front) -->
                            <div class="card-3d-face card-3d-face-front dashboard-card-front-box diagonal-split" style="border-color:<?= e($theme['primary']) ?>38">
                                <!-- Triple Stepped Accent Border & Dark Section Background (Detail 1) -->
                                <svg style="position:absolute;inset:0;width:100%;height:100%;z-index:1;pointer-events:none;display:block" viewBox="0 0 1004 650" preserveAspectRatio="none" aria-hidden="true">
                                    <polygon points="590,0 577.5,60 590.5,60 580.2,110 593.2,110 582.8,160 595.8,160 494,650 1004,650 1004,0" fill="<?= e($theme['versoBg']) ?>"/>
                                    <polygon points="577.5,60 590.5,60 468,650 455,650" fill="#F59E0B"/>
                                    <polygon points="580.2,110 593.2,110 481,650 468,650" fill="#EA580C"/>
                                    <polygon points="582.8,160 595.8,160 494,650 481,650" fill="#0284C7"/>
                                </svg>

                                <!-- Right Dark Section -->
                                <div class="dashboard-front-angled-bg">
                                    <!-- Authentic Two-Tone SIP Flame Watermark (Detail 2) -->
                                    <div style="position:absolute;top:8px;right:22px;pointer-events:none;z-index:1" aria-hidden="true">
                                        <svg width="45" height="60" viewBox="0 0 100 135">
                                            <path d="M52 0 C54 18 68 34 76 54 C88 80 84 108 62 126 C44 140 18 136 6 114 C-2 98 0 80 12 58 C15 54 22 56 22 62 C16 78 18 96 28 106 C40 118 60 114 70 98 C78 84 76 66 66 48 C60 36 54 22 52 0 Z" fill="#DC2626" opacity="0.22"/>
                                            <path d="M35 28 C42 42 56 60 56 80 C56 98 44 110 32 108 C20 106 14 92 18 78 C22 64 28 48 35 28 Z" fill="#F59E0B" opacity="0.28"/>
                                        </svg>
                                    </div>

                                    <!-- Right Contact Details Stack (Right-aligned with icons on right edge) -->
                                    <div style="position:relative;z-index:1;display:flex;flex-direction:column;width:100%">
                                        <?php if (!empty($card->professionalPhone) && $showPhone): ?>
                                            <div class="dashboard-contact-row">
                                                <span style="word-break:break-all;color:#FFFFFF;font-weight:600"><?= e($card->professionalPhone) ?></span>
                                                <span class="dashboard-contact-icon">
                                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor"><path d="M6.62 10.79a15.053 15.053 0 006.59 6.59l2.2-2.2a1 1 0 011.02-.24c1.12.37 2.33.57 3.57.57a1 1 0 011 1V20a1 1 0 01-1 1A17 17 0 013 4a1 1 0 011-1h3.5a1 1 0 011 1c0 1.25.2 2.45.57 3.57a1 1 0 01-.25 1.02l-2.2 2.2z"/></svg>
                                                </span>
                                            </div>
                                        <?php endif; ?>

                                        <?php if (!empty($card->professionalEmail) && $showEmail): ?>
                                            <div class="dashboard-contact-row">
                                                <span style="word-break:break-all;color:#F8FAFC"><?= e($card->professionalEmail) ?></span>
                                                <span class="dashboard-contact-icon">
                                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor"><path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/></svg>
                                                </span>
                                            </div>
                                        <?php endif; ?>

                                        <?php if (!empty($card->companyWebsite) && $showWebsite): ?>
                                            <div class="dashboard-contact-row">
                                                <span style="word-break:break-all;color:#F8FAFC"><?= e($card->companyWebsite) ?></span>
                                                <span class="dashboard-contact-icon">
                                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 17.93c-3.95-.49-7-3.85-7-7.93 0-.62.08-1.21.21-1.79L9 15v1c0 1.1.9 2 2 2v1.93zm6.9-2.54c-.26-.81-1-1.39-1.9-1.39h-1v-3c0-.55-.45-1-1-1H8v-2h2c.55 0 1-.45 1-1V7h2c1.1 0 2-.9 2-2v-.41c2.93 1.19 5 4.06 5 7.41 0 2.08-.8 3.97-2.1 5.39z"/></svg>
                                                </span>
                                            </div>
                                        <?php endif; ?>

                                        <?php if (!empty($card->companyAddress) && $showAddress): ?>
                                            <div class="dashboard-contact-row">
                                                <span style="word-break:break-word;color:#F8FAFC;max-width:180px;text-align:right"><?= e($card->companyAddress) ?></span>
                                                <span class="dashboard-contact-icon">
                                                    <svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5a2.5 2.5 0 010-5 2.5 2.5 0 010 5z"/></svg>
                                                </span>
                                            </div>
                                        <?php endif; ?>

                                        <?php if (!empty($links) && ($visibility ? $visibility->isVisible('show_social_links') : true)): ?>
                                            <?php foreach (array_slice($links, 0, 1) as $link): ?>
                                                <div class="dashboard-contact-row">
                                                    <span style="color:#F8FAFC;font-size:10.5px;word-break:break-all"><?= e($link->label ?? ($link['label'] ?? '')) ?> : <?= e($link->url ?? ($link['url'] ?? '')) ?></span>
                                                    <span class="dashboard-contact-icon">
                                                        <svg width="15" height="15" viewBox="0 0 24 24" fill="currentColor"><path d="M3.9 12c0-1.71 1.39-3.1 3.1-3.1h4V7H7c-2.76 0-5 2.24-5 5s2.24 5 5 5h4v-1.9H7c-1.71 0-3.1-1.39-3.1-3.1zM8 13h8v-2H8v2zm9-6h-4v1.9h4c1.71 0 3.1 1.39 3.1 3.1s-1.39 3.1-3.1 3.1h-4V17h4c2.76 0 5-2.24 5-5s-2.24-5-5-5z"/></svg>
                                                    </span>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php endif; ?>

                                        <?php if (empty($card->professionalPhone) && empty($card->professionalEmail) && empty($card->companyWebsite) && empty($card->companyAddress)): ?>
                                            <div style="color:rgba(255,255,255,0.7);font-style:italic;font-size:11px;text-align:right">Carte sans coordonnées</div>
                                        <?php endif; ?>
                                    </div>
                                </div>

                                <!-- Left White/Light Panel (Identity & Brand) -->
                                <div class="dashboard-front-left-panel">
                                    <!-- Top-Left: Logo & Company -->
                                    <div>
                                        <?php if (!empty($card->logoPath)): ?>
                                            <div class="dashboard-logo-wrap" style="flex-shrink:0;display:flex;align-items:flex-start;justify-content:flex-start">
                                                <img src="<?= e(url('/uploads/' . $card->logoPath)) ?>" alt="Logo d'entreprise" style="max-width:80px;max-height:44px;object-fit:contain;display:block">
                                            </div>
                                        <?php else: ?>
                                            <svg width="34" height="34" viewBox="0 0 21 21" aria-hidden="true" shape-rendering="crispEdges" fill="<?= e($theme['primary']) ?>">
                                                <path fill-rule="evenodd" d="M0 0h7v7H0zM1 1v5h5V1zM2 2h3v3H2zM14 0h7v7h-7zM15 1v5h5V1zM16 2h3v3h-3zM0 14h7v7H0zM1 15v5h5v-5zM2 16h3v3H2z"></path>
                                                <path d="M8 9h1v3H8zM12 9h1v3h-1zM9 12h1v1H9zM11 12h1v1h-1zM10 13h1v1h-1zM15 15h1v1h-1zM17 17h1v1h-1zM19 15h1v1h-1zM9 16h1v1H9zM12 18h2v1h-2z"></path>
                                            </svg>
                                        <?php endif; ?>

                                        <?php if (!empty($card->companyName) && $showCompany): ?>
                                            <div style="font-size:10px;font-weight:700;text-transform:uppercase;letter-spacing:1px;color:#475569;margin-top:6px;line-height:1.2;word-break:break-word">
                                                <?= e($card->companyName) ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>

                                    <!-- Bottom-Left: Name & Role/Dept -->
                                    <div style="margin-top:auto">
                                        <h3 style="font-size:17px;margin:0;font-weight:800;color:#0F172A;line-height:1.2;word-break:break-word">
                                            <?= e($card->getDisplayName() ?: 'Prénom Nom') ?>
                                        </h3>
                                        <?php
                                        $subLineParts = [];
                                        if (!empty($card->jobTitle) && $showJob) $subLineParts[] = $card->jobTitle;
                                        if (!empty($card->department) && $showDept) $subLineParts[] = $card->department;
                                        $subText = implode(' · ', $subLineParts);
                                        ?>
                                        <?php if ($subText !== ''): ?>
                                            <div style="color:#64748B;font-size:12px;font-weight:600;font-style:italic;margin-top:3px;line-height:1.3;word-break:break-word">
                                                <?= e($subText) ?>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>

                            <!-- Back Card Face (3D Face Back) -->
                            <div class="card-3d-face card-3d-face-back dashboard-card-back-box" style="background:<?= e($theme['versoBg']) ?>;color:<?= e($theme['versoText']) ?>;height:100%;padding:20px;gap:18px">
                                <div style="background:#fff;padding:10px;border-radius:12px;display:flex;align-items:center;justify-content:center;flex-shrink:0;box-shadow:0 4px 12px rgba(0,0,0,0.12)">
                                    <img src="<?= e(url('/card/qr.png')) ?>" alt="QR Code" style="width:110px;height:110px;display:block">
                                </div>
                                <div style="color:inherit;display:flex;flex-direction:column;gap:6px;max-width:210px">
                                    <?php if (!empty($card->logoPath)): ?>
                                        <img src="<?= e(url('/uploads/' . $card->logoPath)) ?>" alt="Logo" style="max-height:36px;max-width:90px;object-fit:contain;display:block">
                                    <?php endif; ?>
                                    <div style="font-size:12px;font-weight:800;text-transform:uppercase;letter-spacing:0.8px;color:#FFFFFF;line-height:1.25">
                                        <?= e($card->companyName ?: 'SOCIETE IVORIENNE DE PETROLE') ?>
                                    </div>
                                    <div style="font-size:10px;color:rgba(255,255,255,0.85);line-height:1.3">
                                        <?= e($card->companyAddress ?: 'abidjan, VGE face solibra immeuble la balance') ?>
                                    </div>
                                    <div style="font-size:11px;font-weight:700;line-height:1.3;margin-top:4px;border-top:1px solid rgba(255,255,255,0.2);padding-top:6px">
                                        Scannez pour enregistrer mon contact
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php else: ?>
                    <p class="m">Vous n'avez pas encore créé de carte.</p>
                <?php endif; ?>
            </div>

            <?php if ($card !== null): ?>
                <!-- Preview Links -->
                <div style="display:flex;align-items:center;gap:16px;font-size:13px;margin-top:8px">
                    <a href="<?= e(url('/card/preview/front')) ?>" target="_blank" rel="noopener" style="color:<?= e($theme['primary']) ?>;font-weight:600">Aperçu HD Recto</a>
                    <span style="color:#CBD5E1">•</span>
                    <a href="<?= e(url('/card/preview/back')) ?>" target="_blank" rel="noopener" style="color:<?= e($theme['primary']) ?>;font-weight:600">Aperçu HD Verso</a>
                </div>
            <?php endif; ?>
        </div>

        <!-- Right: Action Buttons Stack -->
        <div class="dashboard-actions-sidebar">
            <a class="b p" href="<?= e(url('/card/edit')) ?>">Modifier ma carte</a>
            <a class="b" href="<?= e(url('/card/edit#theme-selector-card')) ?>" style="border-color:<?= e($theme['primary']) ?>;color:<?= e($theme['primary']) ?>;font-weight:600">
                🎨 Changer de modèle (10 designs)
            </a>
            <?php if ($card !== null): ?>
                <a class="b" href="<?= e(url('/card/download/front.png')) ?>">Télécharger PNG recto</a>
                <a class="b" href="<?= e(url('/card/download/back.png')) ?>">Télécharger PNG verso</a>
                <a class="b" href="<?= e(url('/card/download/card.pdf')) ?>">Télécharger PDF</a>
                <a class="b" href="<?= e(url('/c/' . $card->publicId)) ?>" target="_blank" rel="noopener">Voir ma page publique</a>
                
                <input type="hidden" id="public-url-value" value="<?= e(url('/c/' . $card->publicId)) ?>">
                <button type="button" class="b" id="btn-copy-url" onclick="copyPublicUrl()">Copier le lien</button>

                <form method="POST" action="<?= e(url('/card/toggle-status')) ?>" style="margin:0">
                    <?= csrf_field() ?>
                    <button class="b" type="submit" style="width:100%">
                        <?= $card->isActive ? 'Désactiver ma carte' : 'Réactiver ma carte' ?>
                    </button>
                </form>
            <?php endif; ?>

            <!-- Zone sensible (Account deletion) -->
            <div style="border:1.5px dashed #DC2626;border-radius:12px;padding:14px;margin-top:8px">
                <div style="font-weight:600;font-size:13px;color:#B91C1C;margin-bottom:8px">Zone sensible</div>
                <button type="button" class="b" onclick="openDeleteModal()" style="width:100%;box-sizing:border-box;border-color:#DC2626;color:#B91C1C">
                    Supprimer mon compte
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Account Deletion Modal (matches reference/extracted_views/supprimer_compte.html) -->
<div id="delete-account-modal" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,0.6);backdrop-filter:blur(4px);z-index:9999;align-items:center;justify-content:center;padding:16px">
    <div class="c" style="width:100%;max-width:440px;display:flex;flex-direction:column;gap:16px;border-color:#DC2626;background:#fff;box-shadow:0 20px 25px -5px rgba(0,0,0,0.2)">
        <h2 style="font-size:22px;color:#B91C1C;margin:0">Supprimer mon compte</h2>
        <p class="m">Cette action est définitive : ta carte, tes liens, tes images et ton QR code seront supprimés. Le QR déjà imprimé ne mènera plus nulle part.</p>
        
        <form method="POST" action="<?= e(url('/account/delete')) ?>" style="display:flex;flex-direction:column;gap:16px" novalidate>
            <?= csrf_field() ?>
            <label class="f">
                Mot de passe
                <input type="password" name="password" id="delete_password" placeholder="Confirme avec ton mot de passe" required autofocus>
            </label>

            <button type="submit" class="b" style="min-height:52px;background:#DC2626;border-color:#DC2626;color:#fff;font-weight:600">
                Supprimer définitivement
            </button>
            
            <button type="button" class="b" onclick="closeDeleteModal()">
                Annuler
            </button>
        </form>
    </div>
</div>

<script>
function copyPublicUrl() {
    const urlInput = document.getElementById('public-url-value');
    const copyBtn = document.getElementById('btn-copy-url');
    if (urlInput) {
        navigator.clipboard.writeText(urlInput.value).then(() => {
            if (copyBtn) {
                const originalText = copyBtn.textContent;
                copyBtn.textContent = 'Lien copié !';
                copyBtn.style.color = '#166534';
                copyBtn.style.borderColor = '#166534';
                setTimeout(() => {
                    copyBtn.textContent = originalText;
                    copyBtn.style.color = '';
                    copyBtn.style.borderColor = '';
                }, 2000);
            }
        });
    }
}

function toggleDashboardCardFlip() {
    const flipper = document.getElementById('dashboard-card-flipper');
    const btn = document.getElementById('btn-flip-dashboard');
    if (flipper) {
        flipper.classList.toggle('is-flipped');
        const isFlipped = flipper.classList.contains('is-flipped');
        if (btn) {
            btn.textContent = isFlipped ? '🔄 Voir le Recto' : '🔄 Retourner la carte (3D)';
        }
    }
}

function openDeleteModal() {
    const modal = document.getElementById('delete-account-modal');
    if (modal) {
        modal.style.display = 'flex';
        const pwdInput = document.getElementById('delete_password');
        if (pwdInput) pwdInput.focus();
    }
}

function closeDeleteModal() {
    const modal = document.getElementById('delete-account-modal');
    if (modal) {
        modal.style.display = 'none';
    }
}

// Close modal on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeDeleteModal();
    }
});
</script>
