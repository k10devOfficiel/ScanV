<?php

declare(strict_types=1);

namespace App\Models;

/**
 * BusinessCard Entity Model.
 */
class BusinessCard
{
    public function __construct(
        public readonly ?int $id,
        public int $userId,
        public string $publicId,
        public string $displayFirstName,
        public string $displayLastName,
        public ?string $jobTitle = null,
        public ?string $department = null,
        public ?string $companyName = null,
        public ?string $companyWebsite = null,
        public ?string $companyAddress = null,
        public ?string $professionalPhone = null,
        public ?string $normalizedPhone = null,
        public ?string $professionalEmail = null,
        public ?string $photoPath = null,
        public ?string $logoPath = null,
        public string $themeKey = 'default',
        public bool $isActive = true,
        public ?string $createdAt = null,
        public ?string $updatedAt = null
    ) {
    }

    public static function fromArray(array $row): self
    {
        return new self(
            id: isset($row['id']) ? (int) $row['id'] : null,
            userId: (int) ($row['user_id'] ?? 0),
            publicId: (string) ($row['public_id'] ?? ''),
            displayFirstName: (string) ($row['display_first_name'] ?? ''),
            displayLastName: (string) ($row['display_last_name'] ?? ''),
            jobTitle: $row['job_title'] ?? null,
            department: $row['department'] ?? null,
            companyName: $row['company_name'] ?? null,
            companyWebsite: $row['company_website'] ?? null,
            companyAddress: $row['company_address'] ?? null,
            professionalPhone: $row['professional_phone'] ?? null,
            normalizedPhone: $row['normalized_phone'] ?? null,
            professionalEmail: $row['professional_email'] ?? null,
            photoPath: $row['photo_path'] ?? null,
            logoPath: $row['logo_path'] ?? null,
            themeKey: (string) ($row['theme_key'] ?? 'default'),
            isActive: (bool) ($row['is_active'] ?? true),
            createdAt: isset($row['created_at']) ? (string) $row['created_at'] : null,
            updatedAt: isset($row['updated_at']) ? (string) $row['updated_at'] : null
        );
    }

    public function getDisplayName(): string
    {
        return trim("{$this->displayFirstName} {$this->displayLastName}");
    }

    public function getTelUri(): ?string
    {
        if (!empty($this->normalizedPhone)) {
            return 'tel:' . $this->normalizedPhone;
        }
        if (!empty($this->professionalPhone)) {
            return 'tel:' . preg_replace('/[^\+0-9]/', '', $this->professionalPhone);
        }
        return null;
    }

    /**
     * Magic getter supporting both snake_case and camelCase property names.
     */
    public function __get(string $name): mixed
    {
        if (property_exists($this, $name)) {
            return $this->$name;
        }

        $camel = lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $name))));
        if (property_exists($this, $camel)) {
            return $this->$camel;
        }

        return null;
    }

    /**
     * Magic isset supporting both snake_case and camelCase property names.
     */
    public function __isset(string $name): bool
    {
        if (property_exists($this, $name)) {
            return $this->$name !== null;
        }

        $camel = lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $name))));
        return property_exists($this, $camel) && $this->$camel !== null;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'user_id' => $this->userId,
            'public_id' => $this->publicId,
            'display_first_name' => $this->displayFirstName,
            'display_last_name' => $this->displayLastName,
            'display_name' => $this->getDisplayName(),
            'job_title' => $this->jobTitle,
            'department' => $this->department,
            'company_name' => $this->companyName,
            'company_website' => $this->companyWebsite,
            'company_address' => $this->companyAddress,
            'professional_phone' => $this->professionalPhone,
            'normalized_phone' => $this->normalizedPhone,
            'professional_email' => $this->professionalEmail,
            'photo_path' => $this->photoPath,
            'logo_path' => $this->logoPath,
            'theme_key' => $this->themeKey,
            'is_active' => $this->isActive,
            'created_at' => $this->createdAt,
            'updated_at' => $this->updatedAt,
        ];
    }
}
