<?php

declare(strict_types=1);

namespace App\Repositories;

use App\Core\Database;
use App\Models\BusinessCard;
use App\Models\CardLink;
use App\Models\CardVisibility;
use PDO;

/**
 * CardRepository for managing business cards, their links, and visibility settings.
 */
class CardRepository extends BusinessCardRepository
{
    private PDO $db;

    public function __construct(?PDO $pdo = null)
    {
        parent::__construct($pdo);
        $this->db = $pdo ?? Database::getConnection();
    }

    /**
     * Update card information with prepared statements (Rule 2).
     *
     * @param int $cardId
     * @param array<string, mixed> $data
     */
    public function update(int $cardId, array $data): bool
    {
        $fields = [];
        $params = [
            ':id' => $cardId,
            ':updated_at' => gmdate('Y-m-d H:i:s'),
        ];

        $allowed = [
            'display_first_name',
            'display_last_name',
            'job_title',
            'department',
            'company_name',
            'company_website',
            'company_address',
            'professional_phone',
            'normalized_phone',
            'professional_email',
            'photo_path',
            'logo_path',
            'theme_key',
            'is_active',
        ];

        foreach ($allowed as $col) {
            if (array_key_exists($col, $data)) {
                $fields[] = "`{$col}` = :{$col}";
                $params[":{$col}"] = $data[$col];
            }
        }

        if (empty($fields)) {
            return false;
        }

        $fields[] = '`updated_at` = :updated_at';
        $sql = 'UPDATE `business_cards` SET ' . implode(', ', $fields) . ' WHERE `id` = :id';

        $stmt = $this->db->prepare($sql);
        return $stmt->execute($params);
    }

    /**
     * Replace all links for a card with an updated set.
     *
     * @param int $cardId
     * @param array<int, array{label: string, url: string, sort_order?: int, is_public?: bool|int}> $links
     */
    public function syncLinks(int $cardId, array $links): void
    {
        // Delete current links for card
        $del = $this->db->prepare('DELETE FROM `card_links` WHERE `card_id` = :card_id');
        $del->execute([':card_id' => $cardId]);

        // Insert new links
        if (!empty($links)) {
            $insert = $this->db->prepare('
                INSERT INTO `card_links` (`card_id`, `label`, `url`, `sort_order`, `is_public`)
                VALUES (:card_id, :label, :url, :sort_order, :is_public)
            ');

            foreach ($links as $index => $link) {
                $label = trim((string) ($link['label'] ?? ''));
                $url = trim((string) ($link['url'] ?? ''));

                if ($label === '' || $url === '') {
                    continue;
                }

                $sortOrder = isset($link['sort_order']) ? (int) $link['sort_order'] : (int) $index;
                $isPublic = isset($link['is_public']) ? (int) (bool) $link['is_public'] : 1;

                $insert->execute([
                    ':card_id' => $cardId,
                    ':label' => $label,
                    ':url' => $url,
                    ':sort_order' => $sortOrder,
                    ':is_public' => $isPublic,
                ]);
            }
        }
    }

    /**
     * Delete stored photo from card record.
     */
    public function removePhoto(int $cardId): bool
    {
        $stmt = $this->db->prepare('
            UPDATE `business_cards`
            SET `photo_path` = NULL, `updated_at` = :updated_at
            WHERE `id` = :id
        ');

        return $stmt->execute([
            ':id' => $cardId,
            ':updated_at' => gmdate('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Delete stored logo from card record.
     */
    public function removeLogo(int $cardId): bool
    {
        $stmt = $this->db->prepare('
            UPDATE `business_cards`
            SET `logo_path` = NULL, `updated_at` = :updated_at
            WHERE `id` = :id
        ');

        return $stmt->execute([
            ':id' => $cardId,
            ':updated_at' => gmdate('Y-m-d H:i:s'),
        ]);
    }

    /**
     * Set active state of business card (F-17).
     */
    public function setIsActive(int $cardId, bool $isActive): bool
    {
        $stmt = $this->db->prepare('
            UPDATE `business_cards`
            SET `is_active` = :is_active, `updated_at` = :updated_at
            WHERE `id` = :id
        ');

        return $stmt->execute([
            ':id' => $cardId,
            ':is_active' => $isActive ? 1 : 0,
            ':updated_at' => gmdate('Y-m-d H:i:s'),
        ]);
    }
}
