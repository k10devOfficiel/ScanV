<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Helpers\Security;
use App\Models\BusinessCard;
use App\Models\CardLink;
use App\Models\CardVisibility;
use PDO;

/**
 * Repository for BusinessCard data access and associations.
 */
class BusinessCardRepository
{
    private PDO $pdo;

    public function __construct(?PDO $pdo = null)
    {
        $this->pdo = $pdo ?? Database::getConnection();
    }

    /**
     * Find card by internal numeric ID.
     */
    public function findById(int $id): ?BusinessCard
    {
        $stmt = $this->pdo->prepare('SELECT * FROM `business_cards` WHERE `id` = :id LIMIT 1');
        $stmt->execute([':id' => $id]);
        $row = $stmt->fetch();

        return $row ? BusinessCard::fromArray($row) : null;
    }

    /**
     * Find card for a specific user ID (1 card per account in V1).
     */
    public function findByUserId(int $userId): ?BusinessCard
    {
        $stmt = $this->pdo->prepare('SELECT * FROM `business_cards` WHERE `user_id` = :user_id LIMIT 1');
        $stmt->execute([':user_id' => $userId]);
        $row = $stmt->fetch();

        return $row ? BusinessCard::fromArray($row) : null;
    }

    /**
     * Find card by public 32-hex ID (Rule 5).
     */
    public function findByPublicId(string $publicId): ?BusinessCard
    {
        $stmt = $this->pdo->prepare('SELECT * FROM `business_cards` WHERE `public_id` = :public_id LIMIT 1');
        $stmt->execute([':public_id' => trim($publicId)]);
        $row = $stmt->fetch();

        return $row ? BusinessCard::fromArray($row) : null;
    }

    /**
     * Find card associated with an uploaded photo or logo filename.
     */
    public function findByUploadFilename(string $filename): ?BusinessCard
    {
        $stmt = $this->pdo->prepare('SELECT * FROM `business_cards` WHERE `photo_path` = :fn1 OR `logo_path` = :fn2 LIMIT 1');
        $stmt->execute([
            ':fn1' => trim($filename),
            ':fn2' => trim($filename),
        ]);
        $row = $stmt->fetch();

        return $row ? BusinessCard::fromArray($row) : null;
    }

    /**
     * Create a card with default visibility settings.
     *
     * @param array<string, mixed> $data
     */
    public function create(array $data): BusinessCard
    {
        $now = gmdate('Y-m-d H:i:s');
        $publicId = !empty($data['public_id']) ? (string) $data['public_id'] : Security::generatePublicId();

        $stmt = $this->pdo->prepare('
            INSERT INTO `business_cards` (
                `user_id`,
                `public_id`,
                `display_first_name`,
                `display_last_name`,
                `job_title`,
                `department`,
                `company_name`,
                `company_website`,
                `company_address`,
                `professional_phone`,
                `professional_email`,
                `photo_path`,
                `logo_path`,
                `theme_key`,
                `is_active`,
                `created_at`,
                `updated_at`
            ) VALUES (
                :user_id,
                :public_id,
                :display_first_name,
                :display_last_name,
                :job_title,
                :department,
                :company_name,
                :company_website,
                :company_address,
                :professional_phone,
                :professional_email,
                :photo_path,
                :logo_path,
                :theme_key,
                :is_active,
                :created_at,
                :updated_at
            )
        ');

        $stmt->execute([
            ':user_id' => $data['user_id'],
            ':public_id' => $publicId,
            ':display_first_name' => $data['display_first_name'],
            ':display_last_name' => $data['display_last_name'],
            ':job_title' => $data['job_title'] ?? null,
            ':department' => $data['department'] ?? null,
            ':company_name' => $data['company_name'] ?? null,
            ':company_website' => $data['company_website'] ?? null,
            ':company_address' => $data['company_address'] ?? null,
            ':professional_phone' => $data['professional_phone'] ?? null,
            ':professional_email' => $data['professional_email'] ?? null,
            ':photo_path' => $data['photo_path'] ?? null,
            ':logo_path' => $data['logo_path'] ?? null,
            ':theme_key' => $data['theme_key'] ?? 'default',
            ':is_active' => isset($data['is_active']) ? (int) $data['is_active'] : 1,
            ':created_at' => $now,
            ':updated_at' => $now,
        ]);

        $cardId = (int) $this->pdo->lastInsertId();

        // Initialize default card visibility record (show_job_title=1, show_company=1, others=0)
        $visStmt = $this->pdo->prepare('
            INSERT INTO `card_visibility` (
                `card_id`,
                `show_photo`,
                `show_job_title`,
                `show_department`,
                `show_company`,
                `show_company_address`,
                `show_phone`,
                `show_email`,
                `show_website`,
                `show_social_links`
            ) VALUES (
                :card_id,
                :show_photo,
                :show_job_title,
                :show_department,
                :show_company,
                :show_company_address,
                :show_phone,
                :show_email,
                :show_website,
                :show_social_links
            )
        ');

        $visStmt->execute([
            ':card_id' => $cardId,
            ':show_photo' => isset($data['show_photo']) ? (int) $data['show_photo'] : 0,
            ':show_job_title' => isset($data['show_job_title']) ? (int) $data['show_job_title'] : 1,
            ':show_department' => isset($data['show_department']) ? (int) $data['show_department'] : 0,
            ':show_company' => isset($data['show_company']) ? (int) $data['show_company'] : 1,
            ':show_company_address' => isset($data['show_company_address']) ? (int) $data['show_company_address'] : 0,
            ':show_phone' => isset($data['show_phone']) ? (int) $data['show_phone'] : 0,
            ':show_email' => isset($data['show_email']) ? (int) $data['show_email'] : 0,
            ':show_website' => isset($data['show_website']) ? (int) $data['show_website'] : 0,
            ':show_social_links' => isset($data['show_social_links']) ? (int) $data['show_social_links'] : 0,
        ]);

        return new BusinessCard(
            id: $cardId,
            userId: (int) $data['user_id'],
            publicId: $publicId,
            displayFirstName: (string) $data['display_first_name'],
            displayLastName: (string) $data['display_last_name'],
            jobTitle: $data['job_title'] ?? null,
            department: $data['department'] ?? null,
            companyName: $data['company_name'] ?? null,
            companyWebsite: $data['company_website'] ?? null,
            companyAddress: $data['company_address'] ?? null,
            professionalPhone: $data['professional_phone'] ?? null,
            professionalEmail: $data['professional_email'] ?? null,
            photoPath: $data['photo_path'] ?? null,
            logoPath: $data['logo_path'] ?? null,
            themeKey: (string) ($data['theme_key'] ?? 'default'),
            isActive: isset($data['is_active']) ? (bool) $data['is_active'] : true,
            createdAt: $now,
            updatedAt: $now
        );
    }

    /**
     * Get visibility settings for a card.
     */
    public function getVisibility(int $cardId): ?CardVisibility
    {
        $stmt = $this->pdo->prepare('SELECT * FROM `card_visibility` WHERE `card_id` = :card_id LIMIT 1');
        $stmt->execute([':card_id' => $cardId]);
        $row = $stmt->fetch();

        return $row ? CardVisibility::fromArray($row) : null;
    }

    /**
     * Update visibility settings for a card.
     *
     * @param array<string, bool|int> $settings
     */
    public function updateVisibility(int $cardId, array $settings): bool
    {
        $stmt = $this->pdo->prepare('
            UPDATE `card_visibility` SET
                `show_photo` = :show_photo,
                `show_job_title` = :show_job_title,
                `show_department` = :show_department,
                `show_company` = :show_company,
                `show_company_address` = :show_company_address,
                `show_phone` = :show_phone,
                `show_email` = :show_email,
                `show_website` = :show_website,
                `show_social_links` = :show_social_links
            WHERE `card_id` = :card_id
        ');

        return $stmt->execute([
            ':card_id' => $cardId,
            ':show_photo' => (int) ($settings['show_photo'] ?? 0),
            ':show_job_title' => (int) ($settings['show_job_title'] ?? 1),
            ':show_department' => (int) ($settings['show_department'] ?? 0),
            ':show_company' => (int) ($settings['show_company'] ?? 1),
            ':show_company_address' => (int) ($settings['show_company_address'] ?? 0),
            ':show_phone' => (int) ($settings['show_phone'] ?? 0),
            ':show_email' => (int) ($settings['show_email'] ?? 0),
            ':show_website' => (int) ($settings['show_website'] ?? 0),
            ':show_social_links' => (int) ($settings['show_social_links'] ?? 0),
        ]);
    }

    /**
     * Get links for a card.
     *
     * @return array<int, CardLink>
     */
    public function getLinks(int $cardId, bool $publicOnly = true): array
    {
        $sql = 'SELECT * FROM `card_links` WHERE `card_id` = :card_id';
        if ($publicOnly) {
            $sql .= ' AND `is_public` = 1';
        }
        $sql .= ' ORDER BY `sort_order` ASC, `id` ASC';

        $stmt = $this->pdo->prepare($sql);
        $stmt->execute([':card_id' => $cardId]);
        $rows = $stmt->fetchAll();

        return array_map(fn(array $row) => CardLink::fromArray($row), $rows);
    }

    /**
     * Add a link to a card.
     *
     * @param array{label: string, url: string, sort_order?: int, is_public?: bool} $data
     */
    public function addLink(int $cardId, array $data): CardLink
    {
        $stmt = $this->pdo->prepare('
            INSERT INTO `card_links` (`card_id`, `label`, `url`, `sort_order`, `is_public`)
            VALUES (:card_id, :label, :url, :sort_order, :is_public)
        ');

        $sortOrder = (int) ($data['sort_order'] ?? 0);
        $isPublic = isset($data['is_public']) ? (int) $data['is_public'] : 1;

        $stmt->execute([
            ':card_id' => $cardId,
            ':label' => $data['label'],
            ':url' => $data['url'],
            ':sort_order' => $sortOrder,
            ':is_public' => $isPublic,
        ]);

        $id = (int) $this->pdo->lastInsertId();

        return new CardLink(
            id: $id,
            cardId: $cardId,
            label: $data['label'],
            url: $data['url'],
            sortOrder: $sortOrder,
            isPublic: (bool) $isPublic
        );
    }

    /**
     * Retrieve public card payload with STRICT server-side visibility applied (Rule 6).
     * Unrendered/hidden fields are not present in output array.
     */
    public function getPublicCardData(string $publicId): ?array
    {
        $card = $this->findByPublicId($publicId);
        if ($card === null || !$card->isActive) {
            return null;
        }

        $visibility = $this->getVisibility((int) $card->id);
        if ($visibility === null) {
            // Default visibility
            $visibility = new CardVisibility(null, (int) $card->id);
        }

        // Apply strict server-side filtering
        $filtered = $visibility->filterCard($card);

        // Include links only if social/custom links are visible
        if ($visibility->showSocialLinks) {
            $links = $this->getLinks((int) $card->id, true);
            $filtered['links'] = array_map(fn(CardLink $l) => $l->toArray(), $links);
        } else {
            $filtered['links'] = [];
        }

        return $filtered;
    }
}
