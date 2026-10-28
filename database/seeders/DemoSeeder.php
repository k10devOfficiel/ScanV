<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Core\Env;
use App\Helpers\Security;
use App\Repositories\BusinessCardRepository;
use App\Repositories\UserRepository;
use RuntimeException;

/**
 * Demo Seeder: 1 demo user + 1 complete business card.
 * ONLY allowed in 'local' environment.
 */
class DemoSeeder
{
    private UserRepository $userRepo;
    private BusinessCardRepository $cardRepo;

    public function __construct(
        ?UserRepository $userRepo = null,
        ?BusinessCardRepository $cardRepo = null
    ) {
        $this->userRepo = $userRepo ?? new UserRepository();
        $this->cardRepo = $cardRepo ?? new BusinessCardRepository();
    }

    public function run(): array
    {
        // Enforce local-only execution
        $env = (string) Env::get('APP_ENV', 'production');
        if ($env !== 'local') {
            throw new RuntimeException("Le seeder de démonstration ne peut être exécuté qu'en environnement 'local' (actuel: '{$env}').");
        }

        $email = 'demo@scanv.test';
        $user = $this->userRepo->findByEmail($email);

        if ($user === null) {
            $user = $this->userRepo->create([
                'first_name' => 'Alexandre',
                'last_name' => 'Dupont',
                'login_email' => $email,
                'password' => 'Password123!',
                'status' => 'active',
            ]);
            echo "Utilisateur de démonstration créé : {$email} (Mot de passe: Password123!)\n";
        } else {
            echo "Utilisateur de démonstration déjà existant : {$email}\n";
        }

        // Check if card exists
        $card = $this->cardRepo->findByUserId((int) $user->id);
        if ($card === null) {
            $publicId = Security::generatePublicId();

            $card = $this->cardRepo->create([
                'user_id' => $user->id,
                'public_id' => $publicId,
                'display_first_name' => 'Alexandre',
                'display_last_name' => 'Dupont',
                'job_title' => 'Directeur des Systèmes d\'Information',
                'department' => 'Direction Technique & Innovation',
                'company_name' => 'ScanV Technologies',
                'company_website' => 'https://scanv.test',
                'company_address' => '10 Rue de la Paix, 75002 Paris, France',
                'professional_phone' => '+33 1 23 45 67 89',
                'professional_email' => 'alexandre.dupont@scanv.test',
                'photo_path' => null,
                'logo_path' => null,
                'theme_key' => 'default',
                'is_active' => 1,
                'show_photo' => 0,
                'show_job_title' => 1,
                'show_department' => 1,
                'show_company' => 1,
                'show_company_address' => 1,
                'show_phone' => 1,
                'show_email' => 1,
                'show_website' => 1,
                'show_social_links' => 1,
            ]);

            // Add demo links
            $this->cardRepo->addLink((int) $card->id, [
                'label' => 'LinkedIn',
                'url' => 'https://www.linkedin.com/in/alexandre-dupont',
                'sort_order' => 1,
                'is_public' => 1,
            ]);

            $this->cardRepo->addLink((int) $card->id, [
                'label' => 'GitHub',
                'url' => 'https://github.com/alexandre-dupont',
                'sort_order' => 2,
                'is_public' => 1,
            ]);

            $this->cardRepo->addLink((int) $card->id, [
                'label' => 'Site Personnel',
                'url' => 'https://alexandre-dupont.dev',
                'sort_order' => 3,
                'is_public' => 1,
            ]);

            echo "Carte de visite de démonstration créée (public_id: {$publicId})\n";
        } else {
            echo "Carte de visite de démonstration déjà existante (public_id: {$card->publicId})\n";
        }

        return [
            'user' => $user,
            'card' => $card,
        ];
    }
}
