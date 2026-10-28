<div class="auth-wrapper">
    <div class="auth-card-box">
        <div style="display:flex;flex-direction:column;align-items:center;gap:8px;margin-bottom:24px">
            <svg width="48" height="48" viewBox="0 0 21 21" aria-hidden="true" shape-rendering="crispEdges" fill="var(--color-primary, #4338CA)">
                <path fill-rule="evenodd" d="M0 0h7v7H0zM1 1v5h5V1zM2 2h3v3H2zM14 0h7v7h-7zM15 1v5h5V1zM16 2h3v3h-3zM0 14h7v7H0zM1 15v5h5v-5zM2 16h3v3H2z"></path>
                <path d="M8 9h1v3H8zM12 9h1v3h-1zM9 12h1v1H9zM11 12h1v1h-1zM10 13h1v1h-1zM15 15h1v1h-1zM17 17h1v1h-1zM19 15h1v1h-1zM9 16h1v1H9zM12 18h2v1h-2z"></path>
            </svg>
            <span style="font:700 26px Poppins;color:var(--color-primary, #4338CA)">ScanV</span>
        </div>

        <div class="c" style="display:flex;flex-direction:column;gap:16px">
            <h1 style="font-size:24px">Nouveau mot de passe</h1>

            <?php if (!$valid): ?>
                <div class="alert alert-danger" role="alert">
                    <?= e($tokenError ?? 'Ce lien de réinitialisation est invalide ou a expiré.') ?>
                </div>
                <a class="b p b-lg b-full" href="<?= e(url('/forgot-password')) ?>">Demander un nouveau lien</a>
            <?php else: ?>
                <?php if (!empty($errors['token'])): ?>
                    <div class="alert alert-danger" role="alert">
                        <?= e($errors['token']) ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($errors['password'])): ?>
                    <div class="alert alert-danger" role="alert">
                        <?= e($errors['password']) ?>
                    </div>
                <?php endif; ?>

                <?php if (!empty($errors['password_confirmation'])): ?>
                    <div class="alert alert-danger" role="alert">
                        <?= e($errors['password_confirmation']) ?>
                    </div>
                <?php endif; ?>

                <form method="POST" action="<?= e(url('/reset-password')) ?>" style="display:flex;flex-direction:column;gap:16px" novalidate>
                    <?= csrf_field() ?>
                    <input type="hidden" name="token" value="<?= e($token) ?>">

                    <label class="f">
                        Nouveau mot de passe
                        <input type="password" name="password" id="password" placeholder="10 caractères minimum" required autofocus>
                    </label>

                    <label class="f">
                        Confirmer le mot de passe
                        <input type="password" name="password_confirmation" id="password_confirmation" placeholder="" required>
                    </label>

                    <button type="submit" class="b p b-lg b-full">Enregistrer</button>
                </form>
            <?php endif; ?>

            <a href="<?= e(url('/login')) ?>" style="text-align:center;font-size:14px;color:var(--color-primary, #4338CA);font-weight:600">← Retour à la connexion</a>
        </div>
    </div>
</div>
