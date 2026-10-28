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
            <h1 style="font-size:24px">Connexion</h1>

            <?php if (!empty($flashSuccess)): ?>
                <div class="alert alert-success" role="alert">
                    <?= e($flashSuccess) ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($error)): ?>
                <div class="alert alert-danger" role="alert">
                    <?= e($error) ?>
                </div>
            <?php endif; ?>

            <form method="POST" action="<?= e(url('/login')) ?>" style="display:flex;flex-direction:column;gap:16px" novalidate>
                <?= csrf_field() ?>

                <label class="f">
                    E-mail
                    <input type="email" name="login_email" id="login_email" placeholder="vous@exemple.com" value="<?= e($oldEmail ?? '') ?>" required autofocus>
                </label>

                <label class="f">
                    Mot de passe
                    <input type="password" name="password" id="password" placeholder="" required>
                </label>

                <div style="text-align:right">
                    <a href="<?= e(url('/forgot')) ?>" style="font-size:14px;color:var(--color-primary, #4338CA);font-weight:600">Mot de passe oublié ?</a>
                </div>

                <button type="submit" class="b p b-lg b-full">Se connecter</button>
            </form>

            <div style="text-align:center;font-size:14px;color:var(--color-text-muted, #475569);margin-top:4px">
                Pas encore de compte ? <a href="<?= e(url('/register')) ?>" style="color:var(--color-primary, #4338CA);font-weight:600">Créer ma carte</a>
            </div>
        </div>
    </div>
</div>
