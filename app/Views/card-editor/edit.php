<?php
/** @var \App\Models\BusinessCard $card */
/** @var \App\Models\CardVisibility|null $visibility */
/** @var array<\App\Models\CardLink> $links */
/** @var array<string, string> $errors */
/** @var string|null $success */
/** @var array<string, mixed> $old */

$val = fn(string $field, mixed $default = '') => $old[$field] ?? ($card->$field ?? $default);

$vis = function(string $prop) use ($old, $visibility) {
    if (isset($old[$prop])) {
        return !empty($old[$prop]);
    }
    return $visibility ? (bool) $visibility->$prop : false;
};
?>

<div class="editor-wrapper">
    <div class="editor-header-row">
        <h1 style="font-size:30px;margin:0">Modifier ma carte</h1>
        <a class="b" href="<?= e(url('/dashboard')) ?>" style="min-height:44px">← Retour</a>
    </div>

    <?php if (!empty($success)): ?>
        <div class="alert alert-success" role="status" style="margin-bottom:20px">
            <?= e($success) ?>
        </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger" role="alert" style="margin-bottom:20px">
            <strong>Veuillez corriger les erreurs suivantes :</strong>
            <ul style="margin:8px 0 0 20px;padding:0">
                <?php foreach ($errors as $errorMsg): ?>
                    <li><?= e($errorMsg) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form method="POST" action="<?= e(url('/card/edit')) ?>" enctype="multipart/form-data" id="card-editor-form">
        <?= csrf_field() ?>

        <div class="editor-layout-grid">
            <!-- Left Column: Form Cards -->
            <div class="editor-form-col">
                
                <!-- Theme Selector Card (10 Distinct Styles) -->
                <div class="c" id="theme-selector-card" style="display:flex;flex-direction:column;gap:16px">
                    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px">
                        <div>
                            <h2 style="font-size:18px;margin:0">Style & Design de la carte (10 modèles)</h2>
                            <p style="font-size:13px;color:#475569;margin-top:4px">Choisissez une palette et un style graphique pour vos aperçus, exports PNG et PDF.</p>
                        </div>
                        <span style="font-size:12px;font-weight:600;color:var(--color-primary, #4338CA);background:rgba(67,56,202,0.08);padding:4px 10px;border-radius:999px">10 modèles disponibles</span>
                    </div>

                    <?php
                    $availableThemes = $themes ?? \App\Services\CardThemeService::getAllThemes();
                    $activeThemeKey = (string) $val('theme_key', $card->themeKey ?? 'default');
                    ?>
                    <div class="theme-grid-selector" style="display:grid;grid-template-columns:repeat(auto-fill, minmax(220px, 1fr));gap:12px">
                        <?php foreach ($availableThemes as $tKey => $t): ?>
                            <?php $isSelected = ($activeThemeKey === $tKey); ?>
                            <label class="theme-option-card <?= $isSelected ? 'is-selected' : '' ?>" id="theme-card-<?= e($tKey) ?>" style="border:2px solid <?= $isSelected ? '#4338CA' : '#E2E8F0' ?>;border-radius:12px;padding:12px;cursor:pointer;display:flex;flex-direction:column;gap:10px;background:<?= $isSelected ? '#EEF2FF' : '#FFFFFF' ?>;transition:all 0.15s ease;position:relative" onclick="selectTheme('<?= e($tKey) ?>')">
                                <input type="radio" name="theme_key" value="<?= e($tKey) ?>" <?= $isSelected ? 'checked' : '' ?> style="position:absolute;opacity:0;pointer-events:none">
                                
                                <!-- Color Swatch Recto/Verso representation -->
                                <div style="display:flex;height:44px;border-radius:8px;overflow:hidden;border:1px solid rgba(0,0,0,0.1);box-shadow:0 2px 6px rgba(0,0,0,0.04)">
                                    <!-- Recto Swatch -->
                                    <div style="flex:1.2;background:<?= e($t['rectoBg']) ?>;padding:6px 8px;display:flex;align-items:center;gap:6px">
                                        <div style="width:16px;height:16px;border-radius:50%;background:<?= e($t['primary']) ?>;flex-shrink:0"></div>
                                        <div style="display:flex;flex-direction:column;gap:3px;flex:1">
                                            <div style="height:5px;width:80%;background:<?= e($t['rectoText']) ?>;border-radius:2px"></div>
                                            <div style="height:4px;width:55%;background:<?= e($t['rectoMuted']) ?>;border-radius:2px"></div>
                                        </div>
                                    </div>
                                    <!-- Verso Swatch -->
                                    <div style="flex:1;background:<?= e($t['versoBg']) ?>;display:flex;align-items:center;justify-content:center;gap:4px;padding:4px">
                                        <div style="background:#FFFFFF;color:#0F172A;font-size:8px;font-weight:700;padding:2px 4px;border-radius:3px;box-shadow:0 1px 3px rgba(0,0,0,0.2)">QR</div>
                                        <span style="color:<?= e($t['versoText']) ?>;font-size:9px;font-weight:600">ScanV</span>
                                    </div>
                                </div>

                                <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:6px">
                                    <div>
                                        <div style="font-weight:700;font-size:13.5px;color:#0F172A"><?= e($t['name']) ?></div>
                                        <div style="font-size:11px;color:#64748B;line-height:1.3;margin-top:2px"><?= e($t['subtitle']) ?></div>
                                    </div>
                                    <div class="theme-check-icon" style="width:20px;height:20px;border-radius:50%;background:<?= $isSelected ? '#4338CA' : '#E2E8F0' ?>;color:#fff;display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:700;flex-shrink:0">
                                        <?= $isSelected ? '✓' : '' ?>
                                    </div>
                                </div>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Card 1: Identité -->
                <div class="c form-grid-2col">
                    <h2 style="font-size:18px;margin:0" class="col-span-full">Identité</h2>
                    
                    <label class="f">
                        Prénom
                        <input type="text" id="display_first_name" name="display_first_name" placeholder="Prénom" value="<?= e($val('display_first_name')) ?>" required maxlength="100" oninput="updateLivePreview()">
                    </label>

                    <label class="f">
                        Nom
                        <input type="text" id="display_last_name" name="display_last_name" placeholder="Nom" value="<?= e($val('display_last_name')) ?>" required maxlength="100" oninput="updateLivePreview()">
                    </label>

                </div>

                <!-- Card 2: Fonction et entreprise -->
                <div class="c form-grid-2col">
                    <h2 style="font-size:18px;margin:0" class="col-span-full">Fonction et entreprise</h2>

                    <div style="display:flex;flex-direction:column;gap:6px">
                        <label class="f">
                            Fonction
                            <input type="text" id="job_title" name="job_title" placeholder="Fonction" value="<?= e($val('job_title')) ?>" maxlength="150" oninput="updateLivePreview()">
                        </label>
                        <label class="t">
                            <input type="checkbox" name="show_job_title" value="1" <?= $vis('show_job_title') ? 'checked' : '' ?> onchange="updateLivePreview()">
                            <span>Visible publiquement</span>
                        </label>
                    </div>

                    <div style="display:flex;flex-direction:column;gap:6px">
                        <label class="f">
                            Service
                            <input type="text" id="department" name="department" placeholder="Service" value="<?= e($val('department')) ?>" maxlength="150" oninput="updateLivePreview()">
                        </label>
                        <label class="t">
                            <input type="checkbox" name="show_department" value="1" <?= $vis('show_department') ? 'checked' : '' ?> onchange="updateLivePreview()">
                            <span>Visible publiquement</span>
                        </label>
                    </div>

                    <div style="display:flex;flex-direction:column;gap:6px">
                        <label class="f">
                            Entreprise
                            <input type="text" id="company_name" name="company_name" placeholder="Entreprise" value="<?= e($val('company_name')) ?>" maxlength="150" oninput="updateLivePreview()">
                        </label>
                        <label class="t">
                            <input type="checkbox" name="show_company" value="1" <?= $vis('show_company') ? 'checked' : '' ?> onchange="updateLivePreview()">
                            <span>Visible publiquement</span>
                        </label>
                    </div>

                    <div style="display:flex;flex-direction:column;gap:6px">
                        <label class="f">
                            Site web
                            <input type="url" id="company_website" name="company_website" placeholder="https://" value="<?= e($val('company_website')) ?>" maxlength="255" oninput="updateLivePreview()">
                        </label>
                        <label class="t">
                            <input type="checkbox" name="show_website" value="1" <?= $vis('show_website') ? 'checked' : '' ?> onchange="updateLivePreview()">
                            <span>Visible publiquement</span>
                        </label>
                    </div>

                    <div class="col-span-full" style="display:flex;flex-direction:column;gap:8px">
                        <label class="f">
                            Logo d'entreprise
                            <input type="file" id="logo_input" name="logo" accept="image/jpeg,image/png,image/webp">
                        </label>
                        <div id="current-logo-thumbnail-box" style="<?= !empty($card->logoPath) ? 'display:flex;' : 'display:none;' ?>align-items:center;gap:12px;background:#F8FAFC;padding:8px 12px;border:1px solid #E2E8F0;border-radius:8px">
                            <img id="current-logo-img" src="<?= !empty($card->logoPath) ? e(url('/uploads/' . $card->logoPath)) : '' ?>" alt="Logo d'entreprise" style="max-height:44px;max-width:120px;object-fit:contain">
                            <span id="current-logo-label" style="font-size:12px;color:#475569;font-weight:600"><?= !empty($card->logoPath) ? 'Logo enregistré' : 'Nouveau logo sélectionné' ?></span>
                        </div>
                        <?php if (!empty($card->logoPath)): ?>
                            <label class="t" style="color:#B91C1C">
                                <input type="checkbox" name="delete_logo" value="1">
                                <span>Supprimer le logo existant</span>
                            </label>
                        <?php endif; ?>
                    </div>

                    <div class="col-span-full" style="display:flex;flex-direction:column;gap:6px">
                        <label class="f">
                            Adresse
                            <input type="text" id="company_address" name="company_address" placeholder="10 Rue de la Paix, 75002 Paris" value="<?= e($val('company_address')) ?>" maxlength="255" oninput="updateLivePreview()">
                        </label>
                        <label class="t">
                            <input type="checkbox" name="show_company_address" value="1" <?= $vis('show_company_address') ? 'checked' : '' ?> onchange="updateLivePreview()">
                            <span>Visible publiquement</span>
                        </label>
                    </div>
                </div>

                <!-- Card 3: Contact -->
                <div class="c form-grid-2col">
                    <h2 style="font-size:18px;margin:0" class="col-span-full">Contact</h2>

                    <div style="display:flex;flex-direction:column;gap:6px">
                        <label class="f">
                            Téléphone professionnel
                            <input type="tel" id="professional_phone" name="professional_phone" placeholder="+000 00 00 00 00" value="<?= e($val('professional_phone')) ?>" maxlength="50" oninput="updateLivePreview()">
                        </label>
                        <label class="t">
                            <input type="checkbox" name="show_phone" value="1" <?= $vis('show_phone') ? 'checked' : '' ?> onchange="updateLivePreview()">
                            <span>Visible publiquement</span>
                        </label>
                    </div>

                    <div style="display:flex;flex-direction:column;gap:6px">
                        <label class="f">
                            E-mail professionnel
                            <input type="email" id="professional_email" name="professional_email" placeholder="contact@entreprise.com" value="<?= e($val('professional_email')) ?>" maxlength="191" oninput="updateLivePreview()">
                        </label>
                        <label class="t">
                            <input type="checkbox" name="show_email" value="1" <?= $vis('show_email') ? 'checked' : '' ?> onchange="updateLivePreview()">
                            <span>Visible publiquement</span>
                        </label>
                    </div>
                </div>

                <!-- Card 4: Liens -->
                <div class="c" style="display:flex;flex-direction:column;gap:12px">
                    <div style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px">
                        <h2 style="font-size:18px;margin:0">Liens</h2>
                        <label class="t">
                            <input type="checkbox" name="show_social_links" value="1" <?= $vis('show_social_links') ? 'checked' : '' ?> onchange="updateLivePreview()">
                            <span>Afficher publiquement</span>
                        </label>
                    </div>

                    <div id="links-container" style="display:flex;flex-direction:column;gap:12px">
                        <?php
                        $linksToDisplay = !empty($old['links']) && is_array($old['links']) ? $old['links'] : $links;
                        $idx = 0;
                        ?>
                        <?php foreach ($linksToDisplay as $linkItem): ?>
                            <?php
                            $linkLabel = is_array($linkItem) ? ($linkItem['label'] ?? '') : $linkItem->label;
                            $linkUrl = is_array($linkItem) ? ($linkItem['url'] ?? '') : $linkItem->url;
                            $linkOrder = is_array($linkItem) ? ($linkItem['sort_order'] ?? $idx) : $linkItem->sortOrder;
                            $linkPub = is_array($linkItem) ? (!empty($linkItem['is_public'])) : $linkItem->isPublic;
                            ?>
                            <div class="link-item-row editor-link-row" id="link-row-<?= $idx ?>">
                                <label class="f" style="margin:0">
                                    <span class="sr-only" style="display:none">Libellé</span>
                                    <input type="text" name="links[<?= $idx ?>][label]" placeholder="Libellé (ex: LinkedIn)" value="<?= e($linkLabel) ?>" maxlength="100">
                                </label>
                                <label class="f" style="margin:0">
                                    <span class="sr-only" style="display:none">URL</span>
                                    <input type="text" name="links[<?= $idx ?>][url]" placeholder="https://" value="<?= e($linkUrl) ?>" maxlength="255">
                                </label>
                                <div style="display:flex;align-items:center;gap:8px">
                                    <label class="t" title="Public" style="margin:0">
                                        <input type="checkbox" name="links[<?= $idx ?>][is_public]" value="1" <?= $linkPub ? 'checked' : '' ?>>
                                    </label>
                                    <input type="hidden" name="links[<?= $idx ?>][sort_order]" value="<?= (int) $linkOrder ?>">
                                    <button type="button" class="b" onclick="removeLinkRow(<?= $idx ?>)" style="min-height:44px;padding:0 12px;border-color:#DC2626;color:#DC2626" title="Supprimer">✕</button>
                                </div>
                            </div>
                            <?php $idx++; ?>
                        <?php endforeach; ?>
                    </div>

                    <button type="button" class="b" onclick="addLinkRow()" style="align-self:flex-start;margin-top:6px">
                        + Ajouter un lien
                    </button>
                </div>
            </div>

            <!-- Right Column: Sticky Preview & CTA -->
            <div class="editor-preview-col">
                <div class="c" style="display:flex;flex-direction:column;gap:12px;align-items:center">
                    <div style="display:flex;justify-content:space-between;align-items:center;width:100%">
                        <div style="font-weight:600;font-size:14px">Aperçu en direct</div>
                        <button type="button" class="b b-sm" onclick="toggleCard3DFlip()" style="font-size:12px;padding:4px 10px;min-height:30px">
                            🔄 Retourner
                        </button>
                    </div>

                    <?php $currTheme = \App\Services\CardThemeService::getTheme($activeThemeKey); ?>

                    <!-- 3D Card Scene -->
                    <div class="editor-card-3d-scene" style="perspective:1000px;width:100%;max-width:330px;min-height:215px">
                        <div id="editor-card-flipper" style="width:100%;height:100%;position:relative;transform-style:preserve-3d;transition:transform 0.65s cubic-bezier(0.4, 0.2, 0.2, 1);cursor:pointer" onclick="toggleCard3DFlip()" title="Cliquez pour retourner la carte (Recto / Verso)">
                            
                            <!-- Front mini card -->
                            <div id="preview-mini-front" class="editor-mini-front diagonal-split" style="width:100%;min-height:215px;box-sizing:border-box;background:#FFFFFF;border:1px solid <?= e($currTheme['primary']) ?>4D;border-radius:14px;box-shadow:0 6px 20px rgba(15,23,42,0.08);position:relative;backface-visibility:hidden;-webkit-backface-visibility:hidden;transition:all 0.25s ease;display:flex">
                                <!-- Hidden legacy avatar box to avoid JS errors on file input change -->
                                <div id="preview-avatar-box" style="display:none;"><img id="preview-avatar-img" src="" alt=""></div>

                                <!-- Triple Stepped Accent Border & Dark Section Background (Detail 1) -->
                                <svg style="position:absolute;inset:0;width:100%;height:100%;z-index:1;pointer-events:none;display:block" viewBox="0 0 1004 650" preserveAspectRatio="none" aria-hidden="true">
                                    <polygon id="preview-poly-bg" points="590,0 577.5,60 590.5,60 580.2,110 593.2,110 582.8,160 595.8,160 494,650 1004,650 1004,0" fill="<?= e($currTheme['versoBg']) ?>"/>
                                    <polygon points="577.5,60 590.5,60 468,650 455,650" fill="#F59E0B"/>
                                    <polygon points="580.2,110 593.2,110 481,650 468,650" fill="#EA580C"/>
                                    <polygon points="582.8,160 595.8,160 494,650 481,650" fill="#0284C7"/>
                                </svg>

                                <!-- Right Dark Section -->
                                <div id="preview-angled-bg" class="dashboard-front-angled-bg" style="width:48%;padding:16px 14px 16px 16px">
                                    <!-- Authentic Two-Tone SIP Flame Watermark (Detail 2) -->
                                    <div style="position:absolute;top:6px;right:18px;pointer-events:none;z-index:1" aria-hidden="true">
                                        <svg width="35" height="48" viewBox="0 0 100 135">
                                            <path d="M52 0 C54 18 68 34 76 54 C88 80 84 108 62 126 C44 140 18 136 6 114 C-2 98 0 80 12 58 C15 54 22 56 22 62 C16 78 18 96 28 106 C40 118 60 114 70 98 C78 84 76 66 66 48 C60 36 54 22 52 0 Z" fill="#DC2626" opacity="0.22"/>
                                            <path d="M35 28 C42 42 56 60 56 80 C56 98 44 110 32 108 C20 106 14 92 18 78 C22 64 28 48 35 28 Z" fill="#F59E0B" opacity="0.28"/>
                                        </svg>
                                    </div>

                                    <!-- Right Contact Details Stack (Right-aligned with icons on right edge) -->
                                    <div id="preview-contacts" style="position:relative;z-index:1;display:flex;flex-direction:column;width:100%;font-size:10px">
                                        <div class="dashboard-contact-row" style="font-size:10.5px;margin-bottom:5px">
                                            <span id="preview-phone-val" style="word-break:break-all;color:#FFFFFF;font-weight:600"><?= e($val('professional_phone')) ?></span>
                                            <span class="dashboard-contact-icon" style="width:13px;height:13px">
                                                <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor"><path d="M6.62 10.79a15.053 15.053 0 006.59 6.59l2.2-2.2a1 1 0 011.02-.24c1.12.37 2.33.57 3.57.57a1 1 0 011 1V20a1 1 0 01-1 1A17 17 0 013 4a1 1 0 011-1h3.5a1 1 0 011 1c0 1.25.2 2.45.57 3.57a1 1 0 01-.25 1.02l-2.2 2.2z"/></svg>
                                            </span>
                                        </div>
                                        <div class="dashboard-contact-row" style="font-size:10.5px;margin-bottom:5px">
                                            <span id="preview-email-val" style="word-break:break-all;color:#F8FAFC"><?= e($val('professional_email')) ?></span>
                                            <span class="dashboard-contact-icon" style="width:13px;height:13px">
                                                <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor"><path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/></svg>
                                            </span>
                                        </div>
                                    </div>
                                </div>

                                <!-- Left White/Light Panel (Identity & Brand) -->
                                <div class="dashboard-front-left-panel" style="width:48%;padding:16px 10px 16px 14px;min-height:200px">
                                    <!-- Top-Left: Logo & Company -->
                                    <div>
                                        <div id="preview-logo-box" style="<?= !empty($card->logoPath) ? 'display:flex;' : 'display:none;' ?>flex-shrink:0;align-items:flex-start;justify-content:flex-start">
                                            <img id="preview-logo-img" src="<?= !empty($card->logoPath) ? e(url('/uploads/' . $card->logoPath)) : '' ?>" alt="Logo" style="max-height:30px;max-width:54px;object-fit:contain;display:block">
                                        </div>
                                        <div id="preview-company-name" style="font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:0.8px;color:#475569;margin-top:4px;line-height:1.2;word-break:break-word">
                                            <?= e($val('company_name')) ?>
                                        </div>
                                    </div>

                                    <!-- Bottom-Left: Name & Role/Dept -->
                                    <div style="margin-top:auto">
                                        <h3 id="preview-name" style="font-size:14px;margin:0;font-weight:800;color:#0F172A;line-height:1.2;word-break:break-word;transition:color 0.25s ease">
                                            <?= e(($val('display_first_name') || $val('display_last_name')) ? ($val('display_first_name') . ' ' . $val('display_last_name')) : 'Prénom Nom') ?>
                                        </h3>
                                        <div id="preview-role-company" style="color:#64748B;font-size:11px;font-weight:600;font-style:italic;margin-top:2px;line-height:1.3;word-break:break-word;transition:color 0.25s ease">
                                            <?= e($val('job_title') ?: 'Fonction') ?>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Back mini card (Absolute behind Front for 3D flip) -->
                            <div id="preview-mini-back" style="position:absolute;inset:0;width:100%;min-height:215px;background:<?= e($currTheme['versoBg']) ?>;color:<?= e($currTheme['versoText']) ?>;border-radius:14px;display:flex;align-items:center;justify-content:center;gap:12px;box-shadow:0 6px 20px rgba(0,0,0,0.15);padding:14px;box-sizing:border-box;transform:rotateY(180deg);backface-visibility:hidden;-webkit-backface-visibility:hidden;transition:all 0.25s ease">
                                <div style="background:#fff;padding:6px;border-radius:10px;display:flex;align-items:center;justify-content:center;flex-shrink:0">
                                    <img src="<?= e(url('/card/qr.png')) ?>" alt="QR Code" style="width:84px;height:84px;display:block">
                                </div>
                                <div style="color:inherit;display:flex;flex-direction:column;gap:5px;max-width:165px">
                                    <?php if (!empty($card->logoPath)): ?>
                                        <img src="<?= e(url('/uploads/' . $card->logoPath)) ?>" alt="Logo" style="max-height:26px;max-width:70px;object-fit:contain;display:block">
                                    <?php endif; ?>
                                    <div id="preview-back-company" style="font-size:10px;font-weight:800;text-transform:uppercase;letter-spacing:0.6px;color:#FFFFFF;line-height:1.2">
                                        <?= e($val('company_name') ?: 'SOCIETE IVORIENNE DE PETROLE') ?>
                                    </div>
                                    <div id="preview-back-address" style="font-size:8.5px;color:rgba(255,255,255,0.85);line-height:1.25">
                                        <?= e($val('company_address') ?: 'abidjan, VGE face solibra immeuble la balance') ?>
                                    </div>
                                    <div style="font-size:9.5px;font-weight:700;line-height:1.2;margin-top:2px;border-top:1px solid rgba(255,255,255,0.2);padding-top:4px">
                                        Scannez pour me contacter
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <span style="font-size:11px;color:#64748B;font-style:italic">Cliquez sur la carte pour basculer Recto / Verso</span>
                </div>

                <button type="submit" class="b p" style="min-height:52px;width:100%">Enregistrer</button>
                <a class="b" href="<?= e(url('/dashboard')) ?>" style="width:100%;box-sizing:border-box;text-align:center">Annuler</a>
            </div>
        </div>
    </form>
</div>

<script>
let linkIndexCounter = <?= count($linksToDisplay) ?>;

function addLinkRow() {
    const container = document.getElementById('links-container');
    const idx = linkIndexCounter++;
    const row = document.createElement('div');
    row.className = 'link-item-row editor-link-row';
    row.id = 'link-row-' + idx;
    row.innerHTML = `
        <label class="f" style="margin:0">
            <input type="text" name="links[${idx}][label]" placeholder="Libellé (ex: LinkedIn)" maxlength="100">
        </label>
        <label class="f" style="margin:0">
            <input type="text" name="links[${idx}][url]" placeholder="https://" maxlength="255">
        </label>
        <div style="display:flex;align-items:center;gap:8px">
            <label class="t" title="Public" style="margin:0">
                <input type="checkbox" name="links[${idx}][is_public]" value="1" checked>
            </label>
            <input type="hidden" name="links[${idx}][sort_order]" value="${idx}">
            <button type="button" class="b" onclick="removeLinkRow(${idx})" style="min-height:44px;padding:0 12px;border-color:#DC2626;color:#DC2626" title="Supprimer">✕</button>
        </div>
    `;
    container.appendChild(row);
}

function removeLinkRow(idx) {
    const row = document.getElementById('link-row-' + idx);
    if (row) {
        row.remove();
    }
}

function previewPhoto(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const img = document.getElementById('preview-avatar-img');
            const box = document.getElementById('preview-avatar-box');
            if (img) img.src = e.target.result;
            if (box) box.style.display = 'flex';
        };
        reader.readAsDataURL(input.files[0]);
    }
}

function toggleDeletePhoto(checkbox) {
    const box = document.getElementById('preview-avatar-box');
    if (box) {
        box.style.display = checkbox.checked ? 'none' : 'flex';
    }
}

function updateLivePreview() {
    const fn = document.getElementById('display_first_name')?.value || '';
    const ln = document.getElementById('display_last_name')?.value || '';
    const nameEl = document.getElementById('preview-name');
    if (nameEl) {
        const full = (fn + ' ' + ln).trim();
        nameEl.textContent = full || 'Prénom Nom';
    }

    const job = document.getElementById('job_title')?.value || 'Fonction';
    const comp = document.getElementById('company_name')?.value || '';
    const addr = document.getElementById('company_address')?.value || '';
    const rcEl = document.getElementById('preview-role-company');
    if (rcEl) {
        rcEl.textContent = job + (comp ? ' · ' + comp : '');
    }
    const compEl = document.getElementById('preview-company-name');
    if (compEl) compEl.textContent = comp || 'SOCIETE IVORIENNE DE PETROLE';
    const backCompEl = document.getElementById('preview-back-company');
    if (backCompEl) backCompEl.textContent = comp || 'SOCIETE IVORIENNE DE PETROLE';
    const backAddrEl = document.getElementById('preview-back-address');
    if (backAddrEl) backAddrEl.textContent = addr || 'abidjan, VGE face solibra immeuble la balance';

    const phone = document.getElementById('professional_phone')?.value || '';
    const email = document.getElementById('professional_email')?.value || '';
    const phoneEl = document.getElementById('preview-phone-val');
    const emailEl = document.getElementById('preview-email-val');
    if (phoneEl) phoneEl.textContent = phone;
    if (emailEl) emailEl.textContent = email;

    const showPhotoBox = document.getElementById('show_photo');
    const avatarBox = document.getElementById('preview-avatar-box');
    const avatarImg = document.getElementById('preview-avatar-img');
    if (avatarBox && showPhotoBox && avatarImg) {
        avatarBox.style.display = (showPhotoBox.checked && avatarImg.src && avatarImg.src !== window.location.href) ? 'flex' : 'none';
    }
}

// 10 Themes Catalogue & Real-Time Live Switcher
const THEMES_DATA = <?= json_encode(\App\Services\CardThemeService::getAllThemes(), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP) ?>;

function selectTheme(key) {
    const theme = THEMES_DATA[key];
    if (!theme) return;

    // 1. Radio check
    const radios = document.querySelectorAll('input[name="theme_key"]');
    radios.forEach(r => {
        r.checked = (r.value === key);
    });

    // 2. Options card borders & checks
    document.querySelectorAll('.theme-option-card').forEach(cardEl => {
        cardEl.style.borderColor = '#E2E8F0';
        cardEl.style.background = '#FFFFFF';
        const checkIcon = cardEl.querySelector('.theme-check-icon');
        if (checkIcon) {
            checkIcon.style.background = '#E2E8F0';
            checkIcon.textContent = '';
        }
    });

    const activeCard = document.getElementById('theme-card-' + key);
    if (activeCard) {
        activeCard.style.borderColor = '#4338CA';
        activeCard.style.background = '#EEF2FF';
        const checkIcon = activeCard.querySelector('.theme-check-icon');
        if (checkIcon) {
            checkIcon.style.background = '#4338CA';
            checkIcon.textContent = '✓';
        }
    }

    // 3. Update live mini preview cards immediately
    const frontEl = document.getElementById('preview-mini-front');
    if (frontEl) {
        frontEl.style.borderColor = theme.primary + '4D';
    }
    const angledBg = document.getElementById('preview-angled-bg');
    if (angledBg) {
        angledBg.style.backgroundColor = theme.versoBg;
    }

    const nameEl = document.getElementById('preview-name');
    if (nameEl) nameEl.style.color = theme.rectoText;

    const rcEl = document.getElementById('preview-role-company');
    if (rcEl) rcEl.style.color = theme.rectoMuted;

    const backEl = document.getElementById('preview-mini-back');
    if (backEl) {
        backEl.style.backgroundColor = theme.versoBg;
        backEl.style.color = theme.versoText;
    }
}

// 3D Flip Card Toggle
let isEditorCardFlipped = false;
function toggleCard3DFlip() {
    isEditorCardFlipped = !isEditorCardFlipped;
    const flipper = document.getElementById('editor-card-flipper');
    if (flipper) {
        flipper.style.transform = isEditorCardFlipped ? 'rotateY(180deg)' : 'rotateY(0deg)';
    }
}

// Logo file input instant live preview
const logoInput = document.getElementById('logo_input');
if (logoInput) {
    logoInput.addEventListener('change', function(e) {
        const file = e.target.files && e.target.files[0];
        if (file) {
            const reader = new FileReader();
            reader.onload = function(evt) {
                const res = evt.target.result;
                const prevBox = document.getElementById('preview-logo-box');
                const prevImg = document.getElementById('preview-logo-img');
                const thumbBox = document.getElementById('current-logo-thumbnail-box');
                const thumbImg = document.getElementById('current-logo-img');
                const thumbLbl = document.getElementById('current-logo-label');
                
                if (prevBox) prevBox.style.display = 'flex';
                if (prevImg) prevImg.src = res;
                if (thumbBox) thumbBox.style.display = 'flex';
                if (thumbImg) thumbImg.src = res;
                if (thumbLbl) thumbLbl.textContent = 'Nouveau logo sélectionné : ' + file.name;
            };
            reader.readAsDataURL(file);
        }
    });
}
</script>
