<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= e($title ?? 'ScanV - Carte de Visite Digitale') ?></title>
    <!-- Fonts Google Inter & Poppins -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Poppins:wght@600;700&display=swap" rel="stylesheet">
    <!-- Design tokens and components CSS -->
    <link rel="stylesheet" href="<?= e(url('/assets/css/tokens.css')) ?>">
    <link rel="stylesheet" href="<?= e(url('/assets/css/app.css')) ?>">
</head>
<body>
    <!-- Top Site Navigation Header -->
    <header class="site-header">
        <div class="site-header-inner">
            <!-- Brand Badge -->
            <a href="<?= e(url('/')) ?>" class="brand-badge" title="Retour à l'accueil ScanV">
                <svg class="brand-logo-icon" viewBox="0 0 21 21" aria-hidden="true" shape-rendering="crispEdges">
                    <path fill-rule="evenodd" d="M0 0h7v7H0zM1 1v5h5V1zM2 2h3v3H2zM14 0h7v7h-7zM15 1v5h5V1zM16 2h3v3h-3zM0 14h7v7H0zM1 15v5h5v-5zM2 16h3v3H2z"></path>
                    <path d="M8 9h1v3H8zM12 9h1v3h-1zM9 12h1v1H9zM11 12h1v1h-1zM10 13h1v1h-1zM15 15h1v1h-1zM17 17h1v1h-1zM19 15h1v1h-1zM9 16h1v1H9zM12 18h2v1h-2z"></path>
                </svg>
                <span class="brand-title">ScanV</span>
            </a>

            <!-- Right Controls & Navigation -->
            <nav class="header-nav">
                <a href="<?= e(url('/')) ?>" class="nav-link nav-link-home" style="color:#0F172A;font-weight:600;text-decoration:none;">Accueil</a>
                <?php if (is_logged_in()): ?>
                    <a href="<?= e(url('/dashboard')) ?>" class="nav-link" style="color:#4338CA;font-weight:600;text-decoration:none;">Tableau de bord</a>
                    <a href="<?= e(url('/card/edit')) ?>" class="nav-link" style="color:#475569;font-weight:500;text-decoration:none;">Modifier</a>
                    <span class="user-greeting" style="color:#475569;font-size:14px;"><?= e(auth_user()?->getFullName() ?? '') ?></span>
                    <form method="POST" action="<?= e(url('/logout')) ?>" style="display:inline; margin:0;">
                        <?= csrf_field() ?>
                        <button type="submit" class="b b-sm">Déconnexion</button>
                    </form>
                <?php else: ?>
                    <a class="nav-link" href="<?= e(url('/login')) ?>" style="color:#4338CA;font-weight:600;text-decoration:none;">Connexion</a>
                    <a class="b p" href="<?= e(url('/register')) ?>">Créer une carte</a>
                <?php endif; ?>
            </nav>
        </div>
    </header>

    <!-- Main Content Area -->
    <main class="main-wrapper">
        <?= $content ?? '' ?>
    </main>

    <!-- Site Footer -->
    <footer class="site-footer">
        <div class="site-footer-inner" style="display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:16px;">
            <div style="display: flex; align-items: center; gap: 8px;">
                <svg width="20" height="20" viewBox="0 0 21 21" aria-hidden="true" shape-rendering="crispEdges" fill="currentColor">
                    <path fill-rule="evenodd" d="M0 0h7v7H0zM1 1v5h5V1zM2 2h3v3H2zM14 0h7v7h-7zM15 1v5h5V1zM16 2h3v3h-3zM0 14h7v7H0zM1 15v5h5v-5zM2 16h3v3H2z"></path>
                    <path d="M8 9h1v3H8zM12 9h1v3h-1zM9 12h1v1H9zM11 12h1v1h-1zM10 13h1v1h-1zM15 15h1v1h-1zM17 17h1v1h-1zM19 15h1v1h-1zM9 16h1v1H9zM12 18h2v1h-2z"></path>
                </svg>
                <span>&copy; <?= date('Y') ?> ScanV. Tous droits réservés.</span>
            </div>

            <!-- Navigation Links in Footer for returning easily from any page -->
            <div style="display: flex; align-items: center; gap: 20px; font-size: 14px; flex-wrap: wrap;">
                <a href="<?= e(url('/')) ?>" style="color:#4338CA;font-weight:600;text-decoration:none;">Accueil</a>
                <a href="<?= e(url('/login')) ?>" style="color:#475569;text-decoration:none;">Connexion</a>
                <a href="<?= e(url('/register')) ?>" style="color:#475569;text-decoration:none;">Créer une carte</a>
                <a href="<?= e(url('/conditions-utilisation')) ?>" style="color:#475569;text-decoration:none;">Conditions d'utilisation</a>
                <a href="<?= e(url('/politique-confidentialite')) ?>" style="color:#475569;text-decoration:none;">Confidentialité</a>
            </div>
        </div>
    </footer>
</body>
</html>
