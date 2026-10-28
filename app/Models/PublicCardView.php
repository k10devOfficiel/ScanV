<?php

declare(strict_types=1);

namespace App\Models;

use JsonSerializable;

/**
 * PublicCardView Data Transfer Object (Rule 6).
 * Contains ONLY public and non-empty card attributes.
 * Server-side visibility guarantees hidden fields are completely absent.
 */
class PublicCardView implements JsonSerializable
{
    /**
     * @param string $publicId
     * @param string $displayName
     * @param string $displayFirstName
     * @param string $displayLastName
     * @param string|null $photoUrl
     * @param string|null $logoUrl
     * @param string|null $jobTitle
     * @param string|null $department
     * @param string|null $companyName
     * @param string|null $companyWebsite
     * @param string|null $companyAddress
     * @param string|null $phoneDisplay
     * @param string|null $phoneTelUri
     * @param string|null $email
     * @param string|null $emailMailtoUri
     * @param array<int, array{label: string, url: string}> $links
     * @param string $themeKey
     * @param string $vcardUrl
     */
    public function __construct(
        public readonly string $publicId,
        public readonly string $displayName,
        public readonly string $displayFirstName,
        public readonly string $displayLastName,
        public readonly ?string $photoUrl = null,
        public readonly ?string $logoUrl = null,
        public readonly ?string $jobTitle = null,
        public readonly ?string $department = null,
        public readonly ?string $companyName = null,
        public readonly ?string $companyWebsite = null,
        public readonly ?string $companyAddress = null,
        public readonly ?string $phoneDisplay = null,
        public readonly ?string $phoneTelUri = null,
        public readonly ?string $email = null,
        public readonly ?string $emailMailtoUri = null,
        public readonly array $links = [],
        public readonly string $themeKey = 'default',
        public readonly string $vcardUrl = ''
    ) {
    }

    /**
     * Export public attributes as array.
     * Guaranteed never to leak hidden fields into JSON or array dumps.
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $data = [
            'public_id' => $this->publicId,
            'display_name' => $this->displayName,
            'display_first_name' => $this->displayFirstName,
            'display_last_name' => $this->displayLastName,
            'theme_key' => $this->themeKey,
            'vcard_url' => $this->vcardUrl,
        ];

        if ($this->photoUrl !== null) {
            $data['photo_url'] = $this->photoUrl;
        }
        if ($this->logoUrl !== null) {
            $data['logo_url'] = $this->logoUrl;
        }
        if ($this->jobTitle !== null) {
            $data['job_title'] = $this->jobTitle;
        }
        if ($this->department !== null) {
            $data['department'] = $this->department;
        }
        if ($this->companyName !== null) {
            $data['company_name'] = $this->companyName;
        }
        if ($this->companyWebsite !== null) {
            $data['company_website'] = $this->companyWebsite;
        }
        if ($this->companyAddress !== null) {
            $data['company_address'] = $this->companyAddress;
        }
        if ($this->phoneDisplay !== null) {
            $data['phone_display'] = $this->phoneDisplay;
            $data['phone_tel_uri'] = $this->phoneTelUri;
        }
        if ($this->email !== null) {
            $data['email'] = $this->email;
            $data['email_mailto_uri'] = $this->emailMailtoUri;
        }
        if (!empty($this->links)) {
            $data['links'] = $this->links;
        }

        return $data;
    }

    public function jsonSerialize(): array
    {
        return $this->toArray();
    }
}
