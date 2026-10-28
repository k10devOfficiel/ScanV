<?php

declare(strict_types=1);

namespace App\Models;

/**
 * CardLink Model.
 */
class CardLink
{
    public function __construct(
        public readonly ?int $id,
        public int $cardId,
        public string $label,
        public string $url,
        public int $sortOrder = 0,
        public bool $isPublic = true
    ) {
    }

    public static function fromArray(array $row): self
    {
        return new self(
            id: isset($row['id']) ? (int) $row['id'] : null,
            cardId: (int) ($row['card_id'] ?? 0),
            label: (string) ($row['label'] ?? ''),
            url: (string) ($row['url'] ?? ''),
            sortOrder: (int) ($row['sort_order'] ?? 0),
            isPublic: (bool) ($row['is_public'] ?? 1)
        );
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'card_id' => $this->cardId,
            'label' => $this->label,
            'url' => $this->url,
            'sort_order' => $this->sortOrder,
            'is_public' => $this->isPublic,
        ];
    }
}
