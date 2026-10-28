<div class="auth-wrapper">
    <div class="auth-card-box">
        <!-- Top Branding Logo -->
        <div style="display:flex;flex-direction:column;align-items:center;gap:8px;margin-bottom:24px">
            <svg width="48" height="48" viewBox="0 0 21 21" aria-hidden="true" shape-rendering="crispEdges" fill="var(--color-primary, #4338CA)">
                <path fill-rule="evenodd" d="M0 0h7v7H0zM1 1v5h5V1zM2 2h3v3H2zM14 0h7v7h-7zM15 1v5h5V1zM16 2h3v3h-3zM0 14h7v7H0zM1 15v5h5v-5zM2 16h3v3H2z"></path>
                <path d="M8 9h1v3H8zM12 9h1v3h-1zM9 12h1v1H9zM11 12h1v1h-1zM10 13h1v1h-1zM15 15h1v1h-1zM17 17h1v1h-1zM19 15h1v1h-1zM9 16h1v1H9zM12 18h2v1h-2z"></path>
            </svg>
            <span style="font:700 26px Poppins;color:var(--color-primary, #4338CA)">ScanV</span>
        </div>

        <!-- Form Card Container (.c) -->
        <div class="c" style="display:flex;flex-direction:column;gap:18px">
            <div>
                <h1 style="font-size:24px;margin-bottom:6px">Nouveau mot de passe</h1>
                <p style="color:var(--color-text-muted, #475569);font-size:14px;line-height:1.5">
                    Choisis un nouveau mot de passe fort pour sécuriser ton compte ScanV.
                </p>
            </div>

            <?php if (!empty($flashError)): ?>
                <div class="alert alert-danger" role="alert" aria-live="polite">
                    <?= e($flashError) ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($errors['general'])): ?>
                <div class="alert alert-danger" role="alert" aria-live="polite">
                    <?= e($errors['general']) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="<?= e(url('/forgot/new-password')) ?>" style="display:flex;flex-direction:column;gap:16px" novalidate>
                <?= csrf_field() ?>

                <div class="f">
                    <label for="password" style="font-size:14px;font-weight:600;color:var(--color-text, #0F172A)">
                        Nouveau mot de passe
                    </label>
                    <div style="position:relative;display:flex;align-items:center">
                        <input
                            type="password"
                            name="password"
                            id="password"
                            placeholder="10 caractères minimum"
                            minlength="10"
                            required
                            autofocus
                            autocomplete="new-password"
                            style="padding-right:44px"
                        >
                        <button
                            type="button"
                            class="toggle-pw-btn"
                            data-target="password"
                            aria-label="Afficher ou masquer le mot de passe"
                            style="position:absolute;right:8px;background:none;border:none;cursor:pointer;padding:6px;display:flex;align-items:center;justify-content:center;color:var(--color-text-muted, #64748B)"
                        >
                            <svg class="eye-open" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                            <svg class="eye-closed" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none">
                                <path d="m9.88 9.88 a3 3 0 1 0 4.24 4.24"></path>
                                <path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68"></path>
                                <path d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61"></path>
                                <line x1="2" y1="2" x2="22" y2="22"></line>
                            </svg>
                        </button>
                    </div>
                    <?php if (!empty($errors['password'])): ?>
                        <span style="color:var(--color-danger-text, #B91C1C);font-size:12px;font-weight:400;"><?= e($errors['password']) ?></span>
                    <?php endif; ?>
                </div>

                <div class="f">
                    <label for="password_confirmation" style="font-size:14px;font-weight:600;color:var(--color-text, #0F172A)">
                        Confirmer le mot de passe
                    </label>
                    <div style="position:relative;display:flex;align-items:center">
                        <input
                            type="password"
                            name="password_confirmation"
                            id="password_confirmation"
                            placeholder="Répète le mot de passe"
                            minlength="10"
                            required
                            autocomplete="new-password"
                            style="padding-right:44px"
                        >
                        <button
                            type="button"
                            class="toggle-pw-btn"
                            data-target="password_confirmation"
                            aria-label="Afficher ou masquer la confirmation du mot de passe"
                            style="position:absolute;right:8px;background:none;border:none;cursor:pointer;padding:6px;display:flex;align-items:center;justify-content:center;color:var(--color-text-muted, #64748B)"
                        >
                            <svg class="eye-open" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"></path>
                                <circle cx="12" cy="12" r="3"></circle>
                            </svg>
                            <svg class="eye-closed" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display:none">
                                <path d="m9.88 9.88 a3 3 0 1 0 4.24 4.24"></path>
                                <path d="M10.73 5.08A10.43 10.43 0 0 1 12 5c7 0 10 7 10 7a13.16 13.16 0 0 1-1.67 2.68"></path>
                                <path d="M6.61 6.61A13.526 13.526 0 0 0 2 12s3 7 10 7a9.74 9.74 0 0 0 5.39-1.61"></path>
                                <line x1="2" y1="2" x2="22" y2="22"></line>
                            </svg>
                        </button>
                    </div>
                    <?php if (!empty($errors['password_confirmation'])): ?>
                        <span style="color:var(--color-danger-text, #B91C1C);font-size:12px;font-weight:400;"><?= e($errors['password_confirmation']) ?></span>
                    <?php endif; ?>
                </div>

                <div style="background:var(--color-surface-muted, #F1F5F9);padding:12px 14px;border-radius:10px;font-size:12px;color:var(--color-text-muted, #475569);line-height:1.5">
                    <strong>Règles de sécurité :</strong>
                    <ul style="margin:4px 0 0 16px;padding:0">
                        <li>Au moins 10 caractères</li>
                        <li>Les deux saisies doivent être strictement identiques</li>
                    </ul>
                </div>

                <button type="submit" class="b p b-lg b-full" style="margin-top:4px">Enregistrer le nouveau mot de passe</button>
            </form>

            <a href="<?= e(url('/login')) ?>" style="text-align:center;font-size:14px;color:var(--color-text-muted, #64748B)">← Annuler</a>
        </div>
    </div>
</div>

<script>
(function() {
    var buttons = document.querySelectorAll('.toggle-pw-btn');
    buttons.forEach(function(btn) {
        btn.addEventListener('click', function() {
            var targetId = this.getAttribute('data-target');
            var input = document.getElementById(targetId);
            if (!input) return;

            var eyeOpen = this.querySelector('.eye-open');
            var eyeClosed = this.querySelector('.eye-closed');

            if (input.type === 'password') {
                input.type = 'text';
                if (eyeOpen) eyeOpen.style.display = 'none';
                if (eyeClosed) eyeClosed.style.display = 'block';
            } else {
                input.type = 'password';
                if (eyeOpen) eyeOpen.style.display = 'block';
                if (eyeClosed) eyeClosed.style.display = 'none';
            }
        });
    });
})();
</script>
