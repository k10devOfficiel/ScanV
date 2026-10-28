<?php

declare(strict_types=1);

namespace App\Models;

/**
 * CardVisibility Model enforcing server-side visibility (Rule 6).
 */
class CardVisibility
{
    public function __construct(
        public readonly ?int $id,
        public int $cardId,
        public bool $showPhoto = false,
        public bool $showJobTitle = true,
        public bool $showDepartment = false,
        public bool $showCompany = true,
        public bool $showCompanyAddress = false,
        public bool $showPhone = false,
        public bool $showEmail = false,
        public bool $showWebsite = false,
        public bool $showSocialLinks = false
    ) {
    }

    public static function fromArray(array $row): self
    {
        return new self(
            id: isset($row['id']) ? (int) $row['id'] : null,
            cardId: (int) ($row['card_id'] ?? 0),
            showPhoto: (bool) ($row['show_photo'] ?? 0),
            showJobTitle: (bool) ($row['show_job_title'] ?? 1),
            showDepartment: (bool) ($row['show_department'] ?? 0),
            showCompany: (bool) ($row['show_company'] ?? 1),
            showCompanyAddress: (bool) ($row['show_company_address'] ?? 0),
            showPhone: (bool) ($row['show_phone'] ?? 0),
            showEmail: (bool) ($row['show_email'] ?? 0),
            showWebsite: (bool) ($row['show_website'] ?? 0),
            showSocialLinks: (bool) ($row['show_social_links'] ?? 0)
        );
    }

    /**
     * Check visibility by either snake_case or camelCase property name.
     */
    public function isVisible(string $name): bool
    {
        $val = $this->__get($name);
        return (bool) $val;
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
     * Apply server-side visibility filtering (Rule 6).
     * Any hidden field is unset completely from output.
     */
    public function filterCard(BusinessCard $card): array
    {
        $data = [
            'public_id' => $card->publicId,
            'display_first_name' => $card->displayFirstName,
            'display_last_name' => $card->displayLastName,
            'display_name' => $card->getDisplayName(),
            'theme_key' => $card->themeKey,
        ];

        if ($this->showPhoto && !empty($card->photoPath)) {
            $data['photo_path'] = $card->photoPath;
        }

        if ($this->showJobTitle && !empty($card->jobTitle)) {
            $data['job_title'] = $card->jobTitle;
        }

        if ($this->showDepartment && !empty($card->department)) {
            $data['department'] = $card->department;
        }

        if ($this->showCompany && !empty($card->companyName)) {
            $data['company_name'] = $card->companyName;
            if (!empty($card->logoPath)) {
                $data['logo_path'] = $card->logoPath;
            }
        }

        if ($this->showCompanyAddress && !empty($card->companyAddress)) {
            $data['company_address'] = $card->companyAddress;
        }

        if ($this->showPhone && !empty($card->professionalPhone)) {
            $data['professional_phone'] = $card->professionalPhone;
            $data['normalized_phone'] = $card->normalizedPhone;
            $data['tel_uri'] = $card->getTelUri();
        }

        if ($this->showEmail && !empty($card->professionalEmail)) {
            $data['professional_email'] = $card->professionalEmail;
        }

        if ($this->showWebsite && !empty($card->companyWebsite)) {
            $data['company_website'] = $card->companyWebsite;
        }

        return $data;
    }

    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'card_id' => $this->cardId,
            'show_photo' => $this->showPhoto,
            'show_job_title' => $this->showJobTitle,
            'show_department' => $this->showDepartment,
            'show_company' => $this->showCompany,
            'show_company_address' => $this->showCompanyAddress,
            'show_phone' => $this->showPhone,
            'show_email' => $this->showEmail,
            'show_website' => $this->showWebsite,
            'show_social_links' => $this->showSocialLinks,
        ];
    }
}
