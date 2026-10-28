<?php

declare(strict_types=1);

namespace App\Services;

use App\Helpers\Security;
use App\Models\BusinessCard;
use App\Models\CardVisibility;
use App\Models\PublicCardView;
use App\Repositories\CardRepository;

/**
 * Service for public business card resolution (F-12, F-13, F-14, F-17).
 * Enforces strict server-side visibility (Rule 6) and secure URL schemes (Rule 8).
 */
class PublicCardService
{
    public const STATUS_FOUND = 'found';
    public const STATUS_NOT_FOUND = 'not_found';
    public const STATUS_INACTIVE = 'inactive';

    private CardRepository $cardRepo;

    public function __construct(?CardRepository $cardRepo = null)
    {
        $this->cardRepo = $cardRepo ?? new CardRepository();
    }

    /**
     * Resolve a public card by public_id and build a secure PublicCardView.
     *
     * @param string $publicId
     * @return array{status: string, view: ?PublicCardView}
     */
    public function resolveCard(string $publicId): array
    {
        $trimmedId = strtolower(trim($publicId));

        // Rule 5: 32 hex characters check
        if (!preg_match('/^[a-f0-9]{32}$/', $trimmedId)) {
            return [
                'status' => self::STATUS_NOT_FOUND,
                'view' => null,
            ];
        }

        $card = $this->cardRepo->findByPublicId($trimmedId);
        if ($card === null) {
            return [
                'status' => self::STATUS_NOT_FOUND,
                'view' => null,
            ];
        }

        // Deactivated card: is_active = 0 -> Inactive status without leaking data
        if (!$card->isActive) {
            return [
                'status' => self::STATUS_INACTIVE,
                'view' => null,
            ];
        }

        $visibility = $this->cardRepo->getVisibility((int) $card->id);
        if ($visibility === null) {
            $visibility = new CardVisibility(
                id: null,
                cardId: (int) $card->id,
                showPhoto: false,
                showJobTitle: true,
                showDepartment: false,
                showCompany: true,
                showCompanyAddress: false,
                showPhone: false,
                showEmail: false,
                showWebsite: false,
                showSocialLinks: false
            );
        }

        $view = $this->buildPublicView($card, $visibility);

        return [
            'status' => self::STATUS_FOUND,
            'view' => $view,
        ];
    }

    /**
     * Build PublicCardView containing ONLY visible and non-empty attributes.
     */
    private function buildPublicView(BusinessCard $card, CardVisibility $visibility): PublicCardView
    {
        // Photo
        $photoUrl = null;
        if ($visibility->isVisible('show_photo') && !empty($card->photoPath)) {
            $photoUrl = url('/uploads/' . $card->photoPath);
        }

        // Logo
        $logoUrl = null;
        if (!empty($card->logoPath)) {
            $logoUrl = url('/uploads/' . $card->logoPath);
        }

        // Job title
        $jobTitle = null;
        if ($visibility->isVisible('show_job_title') && !empty(trim((string) $card->jobTitle))) {
            $jobTitle = trim((string) $card->jobTitle);
        }

        // Department
        $department = null;
        if ($visibility->isVisible('show_department') && !empty(trim((string) $card->department))) {
            $department = trim((string) $card->department);
        }

        // Company
        $companyName = null;
        if ($visibility->isVisible('show_company') && !empty(trim((string) $card->companyName))) {
            $companyName = trim((string) $card->companyName);
        }

        // Company website
        $companyWebsite = null;
        if ($visibility->isVisible('show_website') && !empty(trim((string) $card->companyWebsite))) {
            $trimmedWebsite = trim((string) $card->companyWebsite);
            if (Security::isValidExternalUrl($trimmedWebsite)) {
                $companyWebsite = $trimmedWebsite;
            }
        }

        // Company address
        $companyAddress = null;
        if ($visibility->isVisible('show_company_address') && !empty(trim((string) $card->companyAddress))) {
            $companyAddress = trim((string) $card->companyAddress);
        }

        // Phone: visible and non-empty
        $phoneDisplay = null;
        $phoneTelUri = null;
        if ($visibility->isVisible('show_phone') && !empty(trim((string) $card->professionalPhone))) {
            $phoneDisplay = trim((string) $card->professionalPhone);
            $phoneTelUri = $card->getTelUri();
        }

        // Email: visible and non-empty
        $email = null;
        $emailMailtoUri = null;
        if ($visibility->isVisible('show_email') && !empty(trim((string) $card->professionalEmail))) {
            $email = trim((string) $card->professionalEmail);
            $emailMailtoUri = 'mailto:' . $email;
        }

        // Social / Custom links: visible and only public items with valid http/https
        $publicLinks = [];
        if ($visibility->isVisible('show_social_links')) {
            $rawLinks = $this->cardRepo->getLinks((int) $card->id, true);
            foreach ($rawLinks as $link) {
                $url = trim($link->url);
                $label = trim($link->label);
                if ($label !== '' && Security::isValidExternalUrl($url)) {
                    $publicLinks[] = [
                        'label' => $label,
                        'url' => $url,
                    ];
                }
            }
        }

        return new PublicCardView(
            publicId: $card->publicId,
            displayName: $card->getDisplayName(),
            displayFirstName: $card->displayFirstName,
            displayLastName: $card->displayLastName,
            photoUrl: $photoUrl,
            logoUrl: $logoUrl,
            jobTitle: $jobTitle,
            department: $department,
            companyName: $companyName,
            companyWebsite: $companyWebsite,
            companyAddress: $companyAddress,
            phoneDisplay: $phoneDisplay,
            phoneTelUri: $phoneTelUri,
            email: $email,
            emailMailtoUri: $emailMailtoUri,
            links: $publicLinks,
            themeKey: $card->themeKey ?: 'default',
            vcardUrl: url('/c/' . $card->publicId . '/vcard')
        );
    }
}
