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
        <div class="c" style="display:flex;flex-direction:column;gap:16px">
            <h1 style="font-size:24px">Créer mon compte</h1>

            <?php if (!empty($errors['general'])): ?>
                <div class="alert alert-danger" role="alert">
                    <?= e($errors['general']) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="<?= e(url('/register')) ?>" style="display:flex;flex-direction:column;gap:14px" novalidate>
                <?= csrf_field() ?>

                <label class="f">
                    Prénom
                    <input type="text" name="first_name" id="first_name" placeholder="Prénom" value="<?= e($old['first_name'] ?? '') ?>" required autofocus>
                    <?php if (!empty($errors['first_name'])): ?>
                        <span style="color:var(--color-danger-text, #B91C1C);font-size:12px;font-weight:400;"><?= e($errors['first_name']) ?></span>
                    <?php endif; ?>
                </label>

                <label class="f">
                    Nom
                    <input type="text" name="last_name" id="last_name" placeholder="Nom" value="<?= e($old['last_name'] ?? '') ?>" required>
                    <?php if (!empty($errors['last_name'])): ?>
                        <span style="color:var(--color-danger-text, #B91C1C);font-size:12px;font-weight:400;"><?= e($errors['last_name']) ?></span>
                    <?php endif; ?>
                </label>

                <label class="f">
                    E-mail
                    <input type="email" name="login_email" id="login_email" placeholder="vous@exemple.com" value="<?= e($old['login_email'] ?? '') ?>" required>
                    <?php if (!empty($errors['login_email'])): ?>
                        <span style="color:var(--color-danger-text, #B91C1C);font-size:12px;font-weight:400;"><?= e($errors['login_email']) ?></span>
                    <?php endif; ?>
                </label>

                <label class="f">
                    Mot de passe
                    <input type="password" name="password" id="password" placeholder="10 caractères minimum" required>
                    <?php if (!empty($errors['password'])): ?>
                        <span style="color:var(--color-danger-text, #B91C1C);font-size:12px;font-weight:400;"><?= e($errors['password']) ?></span>
                    <?php endif; ?>
                </label>

                <label class="f">
                    Confirmer le mot de passe
                    <input type="password" name="password_confirmation" id="password_confirmation" placeholder="" required>
                    <?php if (!empty($errors['password_confirmation'])): ?>
                        <span style="color:var(--color-danger-text, #B91C1C);font-size:12px;font-weight:400;"><?= e($errors['password_confirmation']) ?></span>
                    <?php endif; ?>
                </label>

                <label class="t" style="align-items:flex-start;margin-top:2px">
                    <input type="checkbox" name="terms" value="1" <?= !empty($old['terms']) ? 'checked' : '' ?> style="margin-top:3px" required>
                    <span>J'accepte les <a href="<?= e(url('/conditions-utilisation')) ?>" target="_blank" style="color:var(--color-primary, #4338CA);font-weight:600">conditions d'utilisation</a> et la <a href="<?= e(url('/politique-confidentialite')) ?>" target="_blank" style="color:var(--color-primary, #4338CA);font-weight:600">politique de confidentialité</a></span>
                </label>
                <?php if (!empty($errors['terms'])): ?>
                    <span style="color:var(--color-danger-text, #B91C1C);font-size:12px;font-weight:400;"><?= e($errors['terms']) ?></span>
                <?php endif; ?>

                <button type="submit" class="b p b-lg b-full" style="margin-top:4px">Créer mon compte</button>
            </form>

            <div style="text-align:center;font-size:14px;color:var(--color-text-muted, #475569);margin-top:4px">
                Déjà un compte ? <a href="<?= e(url('/login')) ?>" style="color:var(--color-primary, #4338CA);font-weight:600">Se connecter</a>
            </div>
        </div>
    </div>
</div>
