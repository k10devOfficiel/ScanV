<!-- ScanV Landing Page - Harmonized with reference/landing.html -->
<div class="landing-hero-container">
    <div class="landing-hero-row">
        <!-- Hero Text & Value Proposition -->
        <div class="landing-hero-col-text">
            <h1 class="landing-hero-title">
                Ta carte de visite,<br>
                <span style="color:var(--color-primary, #4338CA)">toujours à jour.</span>
            </h1>
            <p class="landing-hero-desc">
                Crée ta carte, télécharge-la en PNG ou PDF, et partage-la avec un QR code qui ne change jamais.
            </p>
            <div class="landing-cta-group">
                <a class="b p b-lg" href="<?= e(url('/register')) ?>">Créer ma carte</a>
                <?php if (is_logged_in()): ?>
                    <a class="b b-lg" href="<?= e(url('/dashboard')) ?>">Mon tableau de bord</a>
                <?php else: ?>
                    <a class="b b-lg" href="<?= e(url('/login')) ?>">Se connecter</a>
                <?php endif; ?>
            </div>
        </div>

        <!-- Hero 3D Interactive Flip Cards (Recto/Verso) -->
        <div class="landing-hero-col-preview">
            <div class="card-flip-stage" id="heroCardStage" aria-label="Aperçu interactif recto et verso de la carte">
                <!-- Front Card 1 (Starts on Recto) -->
                <div class="hero-flip-card hero-flip-card-1" id="heroCard1" tabindex="0" role="button" aria-label="Carte 1: Cliquer pour retourner entre Recto et Verso" title="Cliquez pour retourner cette carte">
                    <!-- Face Recto (Blanche, Design Diagonal Bicolore sans photo) -->
                    <div class="hero-card-face hero-card-front" style="padding:0;overflow:hidden;position:relative;display:flex">
                        <!-- Triple Stepped Accent Border & Dark Section Background (Detail 1) -->
                        <svg style="position:absolute;inset:0;width:100%;height:100%;z-index:1;pointer-events:none;display:block" viewBox="0 0 1004 650" preserveAspectRatio="none" aria-hidden="true">
                            <polygon points="590,0 577.5,60 590.5,60 580.2,110 593.2,110 582.8,160 595.8,160 494,650 1004,650 1004,0" fill="#0F172A"/>
                            <polygon points="577.5,60 590.5,60 468,650 455,650" fill="#F59E0B"/>
                            <polygon points="580.2,110 593.2,110 481,650 468,650" fill="#EA580C"/>
                            <polygon points="582.8,160 595.8,160 494,650 481,650" fill="#0284C7"/>
                        </svg>

                        <!-- Right Dark Section -->
                        <div class="dashboard-front-angled-bg" style="width:48%;padding:16px 14px 16px 18px">
                            <!-- Authentic Two-Tone SIP Flame Watermark (Detail 2) -->
                            <div style="position:absolute;top:8px;right:20px;pointer-events:none;z-index:1" aria-hidden="true">
                                <svg width="40" height="54" viewBox="0 0 100 135">
                                    <path d="M52 0 C54 18 68 34 76 54 C88 80 84 108 62 126 C44 140 18 136 6 114 C-2 98 0 80 12 58 C15 54 22 56 22 62 C16 78 18 96 28 106 C40 118 60 114 70 98 C78 84 76 66 66 48 C60 36 54 22 52 0 Z" fill="#DC2626" opacity="0.22"/>
                                    <path d="M35 28 C42 42 56 60 56 80 C56 98 44 110 32 108 C20 106 14 92 18 78 C22 64 28 48 35 28 Z" fill="#F59E0B" opacity="0.28"/>
                                </svg>
                            </div>
                            <div style="display:flex;flex-direction:column;width:100%;font-size:10.5px">
                                <div class="dashboard-contact-row" style="font-size:10.5px;margin-bottom:5px">
                                    <span style="color:#FFFFFF;font-weight:600">+225 07 12 34 56 78</span>
                                    <span class="dashboard-contact-icon" style="width:13px;height:13px">
                                        <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor"><path d="M6.62 10.79a15.053 15.053 0 006.59 6.59l2.2-2.2a1 1 0 011.02-.24c1.12.37 2.33.57 3.57.57a1 1 0 011 1V20a1 1 0 01-1 1A17 17 0 013 4a1 1 0 011-1h3.5a1 1 0 011 1c0 1.25.2 2.45.57 3.57a1 1 0 01-.25 1.02l-2.2 2.2z"/></svg>
                                    </span>
                                </div>
                                <div class="dashboard-contact-row" style="font-size:10.5px;margin-bottom:5px">
                                    <span style="color:#F8FAFC">julien.kouame@innovtech.ci</span>
                                    <span class="dashboard-contact-icon" style="width:13px;height:13px">
                                        <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor"><path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/></svg>
                                    </span>
                                </div>
                                <div class="dashboard-contact-row" style="font-size:10.5px;margin-bottom:5px">
                                    <span style="color:#F8FAFC">www.innovtech.ci</span>
                                    <span class="dashboard-contact-icon" style="width:13px;height:13px">
                                        <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 17.93c-3.95-.49-7-3.85-7-7.93 0-.62.08-1.21.21-1.79L9 15v1c0 1.1.9 2 2 2v1.93zm6.9-2.54c-.26-.81-1-1.39-1.9-1.39h-1v-3c0-.55-.45-1-1-1H8v-2h2c.55 0 1-.45 1-1V7h2c1.1 0 2-.9 2-2v-.41c2.93 1.19 5 4.06 5 7.41 0 2.08-.8 3.97-2.1 5.39z"/></svg>
                                    </span>
                                </div>
                                <div class="dashboard-contact-row" style="font-size:10.5px;margin-bottom:0">
                                    <span style="color:#F8FAFC">Plateau, Abidjan · CI</span>
                                    <span class="dashboard-contact-icon" style="width:13px;height:13px">
                                        <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5a2.5 2.5 0 010-5 2.5 2.5 0 010 5z"/></svg>
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Left White Panel -->
                        <div class="dashboard-front-left-panel" style="width:48%;padding:16px 10px 16px 14px;min-height:200px">
                            <div>
                                <svg width="32" height="32" viewBox="0 0 21 21" aria-hidden="true" shape-rendering="crispEdges" fill="#4338CA">
                                    <path fill-rule="evenodd" d="M0 0h7v7H0zM1 1v5h5V1zM2 2h3v3H2zM14 0h7v7h-7zM15 1v5h5V1zM16 2h3v3h-3zM0 14h7v7H0zM1 15v5h5v-5zM2 16h3v3H2z"></path>
                                    <path d="M8 9h1v3H8zM12 9h1v3h-1zM9 12h1v1H9zM11 12h1v1h-1zM10 13h1v1h-1zM15 15h1v1h-1zM17 17h1v1h-1zM19 15h1v1h-1zM9 16h1v1H9zM12 18h2v1h-2z"></path>
                                </svg>
                                <div style="font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:0.8px;color:#475569;margin-top:4px">
                                    InnovTech Abidjan
                                </div>
                            </div>

                            <div style="margin-top:auto">
                                <h3 style="font-size:14px;margin:0;font-weight:800;color:#0F172A;line-height:1.2">Julien Kouamé</h3>
                                <div style="color:#64748B;font-size:11px;font-weight:600;font-style:italic;margin-top:2px">Dir. Commercial</div>
                            </div>
                        </div>
                    </div>

                    <!-- Face Verso (Indigo, QR Code) -->
                    <div class="hero-card-face hero-card-back">
                        <div style="background:#FFFFFF;padding:8px;border-radius:10px;display:flex;box-shadow:0 4px 12px rgba(0,0,0,0.15)">
                            <svg width="78" height="78" viewBox="0 0 21 21" aria-hidden="true" shape-rendering="crispEdges" fill="#0F172A">
                                <path fill-rule="evenodd" d="M0 0h7v7H0zM1 1v5h5V1zM2 2h3v3H2zM14 0h7v7h-7zM15 1v5h5V1zM16 2h3v3h-3zM0 14h7v7H0zM1 15v5h5v-5zM2 16h3v3H2z"></path>
                                <path d="M8 0h2v2H8zM11 1h2v1h-2zM8 3h1v3H8zM10 4h2v2h-2zM13 8h2v2h-2zM8 8h3v1H8zM12 10h1v3h-1zM9 10h2v2H9zM15 9h3v1h-3zM18 8h3v2h-3zM8 13h2v2H8zM11 14h2v2h-2zM14 12h2v2h-2zM17 12h4v1h-4zM15 15h3v1h-3zM19 15h2v3h-2zM9 17h2v2H9zM12 18h3v1h-3zM16 18h2v3h-2zM8 20h4v1H8zM0 8h2v2H0zM3 9h2v1H3zM5 8h1v3H5zM1 11h3v1H1z"></path>
                            </svg>
                        </div>
                        <span style="color:#FFFFFF;font-size:11.5px;font-weight:600;letter-spacing:0.2px">Scannez pour enregistrer mon contact</span>
                        <span style="color:rgba(255,255,255,0.7);font-size:10px">ScanV • Verso</span>
                    </div>
                </div>

                <!-- Back Card 2 (Starts on Verso) -->
                <div class="hero-flip-card hero-flip-card-2" id="heroCard2" tabindex="0" role="button" aria-label="Carte 2: Cliquer pour retourner entre Verso et Recto" title="Cliquez pour retourner cette carte">
                    <!-- Face Verso (Visible au départ) -->
                    <div class="hero-card-face hero-card-back">
                        <div style="background:#FFFFFF;padding:10px;border-radius:10px;display:flex;box-shadow:0 4px 12px rgba(0,0,0,0.15)">
                            <svg width="86" height="86" viewBox="0 0 21 21" aria-hidden="true" shape-rendering="crispEdges" fill="#0F172A">
                                <path fill-rule="evenodd" d="M0 0h7v7H0zM1 1v5h5V1zM2 2h3v3H2zM14 0h7v7h-7zM15 1v5h5V1zM16 2h3v3h-3zM0 14h7v7H0zM1 15v5h5v-5zM2 16h3v3H2z"></path>
                                <path d="M8 0h2v2H8zM11 1h2v1h-2zM8 3h1v3H8zM10 4h2v2h-2zM13 8h2v2h-2zM8 8h3v1H8zM12 10h1v3h-1zM9 10h2v2H9zM15 9h3v1h-3zM18 8h3v2h-3zM8 13h2v2H8zM11 14h2v2h-2zM14 12h2v2h-2zM17 12h4v1h-4zM15 15h3v1h-3zM19 15h2v3h-2zM9 17h2v2H9zM12 18h3v1h-3zM16 18h2v3h-2zM8 20h4v1H8zM0 8h2v2H0zM3 9h2v1H3zM5 8h1v3H5zM1 11h3v1H1z"></path>
                            </svg>
                        </div>
                        <span style="color:#FFFFFF;font-size:12px;font-weight:600">Scannez pour enregistrer mon contact</span>
                        <span style="color:rgba(255,255,255,0.7);font-size:10px">ScanV • Verso</span>
                    </div>

                    <!-- Face Recto (Révélée au clic, Design Diagonal Bicolore sans photo) -->
                    <div class="hero-card-face hero-card-front" style="padding:0;overflow:hidden;position:relative;display:flex">
                        <!-- Triple Stepped Accent Border & Dark Section Background (Detail 1) -->
                        <svg style="position:absolute;inset:0;width:100%;height:100%;z-index:1;pointer-events:none;display:block" viewBox="0 0 1004 650" preserveAspectRatio="none" aria-hidden="true">
                            <polygon points="590,0 577.5,60 590.5,60 580.2,110 593.2,110 582.8,160 595.8,160 494,650 1004,650 1004,0" fill="#0F172A"/>
                            <polygon points="577.5,60 590.5,60 468,650 455,650" fill="#F59E0B"/>
                            <polygon points="580.2,110 593.2,110 481,650 468,650" fill="#EA580C"/>
                            <polygon points="582.8,160 595.8,160 494,650 481,650" fill="#0284C7"/>
                        </svg>

                        <!-- Right Dark Section -->
                        <div class="dashboard-front-angled-bg" style="width:48%;padding:16px 14px 16px 18px">
                            <!-- Authentic Two-Tone SIP Flame Watermark (Detail 2) -->
                            <div style="position:absolute;top:8px;right:20px;pointer-events:none;z-index:1" aria-hidden="true">
                                <svg width="40" height="54" viewBox="0 0 100 135">
                                    <path d="M52 0 C54 18 68 34 76 54 C88 80 84 108 62 126 C44 140 18 136 6 114 C-2 98 0 80 12 58 C15 54 22 56 22 62 C16 78 18 96 28 106 C40 118 60 114 70 98 C78 84 76 66 66 48 C60 36 54 22 52 0 Z" fill="#DC2626" opacity="0.22"/>
                                    <path d="M35 28 C42 42 56 60 56 80 C56 98 44 110 32 108 C20 106 14 92 18 78 C22 64 28 48 35 28 Z" fill="#F59E0B" opacity="0.28"/>
                                </svg>
                            </div>
                            <div style="display:flex;flex-direction:column;width:100%;font-size:10.5px">
                                <div class="dashboard-contact-row" style="font-size:10.5px;margin-bottom:5px">
                                    <span style="color:#FFFFFF;font-weight:600">+225 07 12 34 56 78</span>
                                    <span class="dashboard-contact-icon" style="width:13px;height:13px">
                                        <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor"><path d="M6.62 10.79a15.053 15.053 0 006.59 6.59l2.2-2.2a1 1 0 011.02-.24c1.12.37 2.33.57 3.57.57a1 1 0 011 1V20a1 1 0 01-1 1A17 17 0 013 4a1 1 0 011-1h3.5a1 1 0 011 1c0 1.25.2 2.45.57 3.57a1 1 0 01-.25 1.02l-2.2 2.2z"/></svg>
                                    </span>
                                </div>
                                <div class="dashboard-contact-row" style="font-size:10.5px;margin-bottom:5px">
                                    <span style="color:#F8FAFC">julien.kouame@innovtech.ci</span>
                                    <span class="dashboard-contact-icon" style="width:13px;height:13px">
                                        <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor"><path d="M20 4H4c-1.1 0-1.99.9-1.99 2L2 18c0 1.1.9 2 2 2h16c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 4l-8 5-8-5V6l8 5 8-5v2z"/></svg>
                                    </span>
                                </div>
                                <div class="dashboard-contact-row" style="font-size:10.5px;margin-bottom:5px">
                                    <span style="color:#F8FAFC">www.innovtech.ci</span>
                                    <span class="dashboard-contact-icon" style="width:13px;height:13px">
                                        <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 17.93c-3.95-.49-7-3.85-7-7.93 0-.62.08-1.21.21-1.79L9 15v1c0 1.1.9 2 2 2v1.93zm6.9-2.54c-.26-.81-1-1.39-1.9-1.39h-1v-3c0-.55-.45-1-1-1H8v-2h2c.55 0 1-.45 1-1V7h2c1.1 0 2-.9 2-2v-.41c2.93 1.19 5 4.06 5 7.41 0 2.08-.8 3.97-2.1 5.39z"/></svg>
                                    </span>
                                </div>
                                <div class="dashboard-contact-row" style="font-size:10.5px;margin-bottom:0">
                                    <span style="color:#F8FAFC">Plateau, Abidjan · CI</span>
                                    <span class="dashboard-contact-icon" style="width:13px;height:13px">
                                        <svg width="13" height="13" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5a2.5 2.5 0 010-5 2.5 2.5 0 010 5z"/></svg>
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Left White Panel -->
                        <div class="dashboard-front-left-panel" style="width:48%;padding:16px 10px 16px 14px;min-height:200px">
                            <div>
                                <svg width="32" height="32" viewBox="0 0 21 21" aria-hidden="true" shape-rendering="crispEdges" fill="#4338CA">
                                    <path fill-rule="evenodd" d="M0 0h7v7H0zM1 1v5h5V1zM2 2h3v3H2zM14 0h7v7h-7zM15 1v5h5V1zM16 2h3v3h-3zM0 14h7v7H0zM1 15v5h5v-5zM2 16h3v3H2z"></path>
                                    <path d="M8 9h1v3H8zM12 9h1v3h-1zM9 12h1v1H9zM11 12h1v1h-1zM10 13h1v1h-1zM15 15h1v1h-1zM17 17h1v1h-1zM19 15h1v1h-1zM9 16h1v1H9zM12 18h2v1h-2z"></path>
                                </svg>
                                <div style="font-size:9px;font-weight:700;text-transform:uppercase;letter-spacing:0.8px;color:#475569;margin-top:4px">
                                    InnovTech Abidjan
                                </div>
                            </div>

                            <div style="margin-top:auto">
                                <h3 style="font-size:14px;margin:0;font-weight:800;color:#0F172A;line-height:1.2">Julien Kouamé</h3>
                                <div style="color:#64748B;font-size:11px;font-weight:600;font-style:italic;margin-top:2px">Dir. Commercial</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Hint & Flip Controls -->
            <div class="flip-hint-bar">
                <button type="button" class="flip-toggle-btn" id="flipCardsBtn" title="Retourner les deux cartes">
                    <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="23 4 23 10 17 10"></polyline>
                        <polyline points="1 20 1 14 7 14"></polyline>
                        <path d="M3.51 9a9 9 0 0 1 14.85-3.36L23 10M1 14l4.64 4.36A9 9 0 0 0 20.49 15"></path>
                    </svg>
                    <span>Cliquer pour retourner (Recto / Verso)</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Script de retournement 3D interactif -->
    <script>
    (function() {
        const card1 = document.getElementById('heroCard1');
        const card2 = document.getElementById('heroCard2');
        const flipBtn = document.getElementById('flipCardsBtn');

        function toggleCard(card) {
            if (!card) return;
            card.classList.toggle('is-flipped');
        }

        if (card1) {
            card1.addEventListener('click', function() { toggleCard(card1); });
            card1.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    toggleCard(card1);
                }
            });
        }

        if (card2) {
            card2.addEventListener('click', function() { toggleCard(card2); });
            card2.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    toggleCard(card2);
                }
            });
        }

        if (flipBtn) {
            flipBtn.addEventListener('click', function() {
                toggleCard(card1);
                toggleCard(card2);
            });
        }
    })();
    </script>

    <!-- How it Works Section -->
    <div style="margin-top:40px;padding-bottom:32px">
        <h2 style="font-size:30px;text-align:center;margin-bottom:32px">Comment ça marche</h2>
        <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(220px,1fr));gap:20px">
            <div class="c">
                <div style="font:700 15px Poppins;color:var(--color-primary, #4338CA)">01</div>
                <h3 style="font-size:18px;margin:8px 0">S'inscrire</h3>
                <p style="margin:0;color:var(--color-text-muted, #475569);font-size:14px;line-height:1.5">Un compte sécurisé créé en une minute.</p>
            </div>
            <div class="c">
                <div style="font:700 15px Poppins;color:var(--color-primary, #4338CA)">02</div>
                <h3 style="font-size:18px;margin:8px 0">Remplir sa carte</h3>
                <p style="margin:0;color:var(--color-text-muted, #475569);font-size:14px;line-height:1.5">Tu choisis précisément ce qui est public ou masqué.</p>
            </div>
            <div class="c">
                <div style="font:700 15px Poppins;color:var(--color-primary, #4338CA)">03</div>
                <h3 style="font-size:18px;margin:8px 0">Télécharger</h3>
                <p style="margin:0;color:var(--color-text-muted, #475569);font-size:14px;line-height:1.5">Exports haute résolution PNG recto/verso et PDF 300 DPI.</p>
            </div>
            <div class="c">
                <div style="font:700 15px Poppins;color:var(--color-primary, #4338CA)">04</div>
                <h3 style="font-size:18px;margin:8px 0">Partager</h3>
                <p style="margin:0;color:var(--color-text-muted, #475569);font-size:14px;line-height:1.5">Un QR code unique et stable à vie.</p>
            </div>
        </div>
    </div>
</div>
