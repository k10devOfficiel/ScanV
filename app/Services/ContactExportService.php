<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\PublicCardView;

/**
 * Service for vCard 3.0 Contact Export (F-15).
 * Compliant with RFC 2426 (vCard 3.0) and mobile operating systems (iOS/Android).
 */
class ContactExportService
{
    /**
     * Generate vCard 3.0 string from a PublicCardView instance.
     * Contains ONLY public fields (Rule 6).
     */
    public function generateVCard(PublicCardView $card): string
    {
        $lines = [
            'BEGIN:VCARD',
            'VERSION:3.0',
            'PRODID:-//ScanV//Digital Business Card//FR',
        ];

        // 1. Structured Name (N: FamilyName;GivenName;AdditionalNames;Prefixes;Suffixes)
        $lastName = $this->escapeText($card->displayLastName);
        $firstName = $this->escapeText($card->displayFirstName);
        $lines[] = "N:{$lastName};{$firstName};;;";

        // 2. Formatted Name (FN)
        $fn = $this->escapeText($card->displayName);
        $lines[] = "FN:{$fn}";

        // 3. Organization (ORG: Company;Department)
        if ($card->companyName !== null) {
            $org = $this->escapeText($card->companyName);
            if ($card->department !== null) {
                $org .= ';' . $this->escapeText($card->department);
            }
            $lines[] = "ORG:{$org}";
        } elseif ($card->department !== null) {
            $lines[] = "ORG:;" . $this->escapeText($card->department);
        }

        // 4. Job Title (TITLE)
        if ($card->jobTitle !== null) {
            $lines[] = "TITLE:" . $this->escapeText($card->jobTitle);
        }

        // 5. Telephone (TEL)
        if ($card->phoneTelUri !== null || $card->phoneDisplay !== null) {
            $phone = $card->phoneTelUri !== null
                ? str_replace('tel:', '', $card->phoneTelUri)
                : (string) $card->phoneDisplay;
            $lines[] = "TEL;TYPE=CELL,VOICE:" . $this->escapeText($phone);
        }

        // 6. Professional Email (EMAIL)
        if ($card->email !== null) {
            $lines[] = "EMAIL;TYPE=INTERNET,WORK:" . $this->escapeText($card->email);
        }

        // 7. Company Website (URL)
        if ($card->companyWebsite !== null) {
            $lines[] = "URL;TYPE=WORK:" . $this->escapeText($card->companyWebsite);
        }

        // 8. Public Card Page URL
        $cardPageUrl = url('/c/' . $card->publicId);
        $lines[] = "URL:" . $this->escapeText($cardPageUrl);

        // 9. Additional Public External Links
        foreach ($card->links as $link) {
            if (!empty($link['url'])) {
                $lines[] = "URL:" . $this->escapeText($link['url']);
            }
        }

        // 10. Address (ADR: post office box;extended address;street address;locality;region;postal code;country)
        if ($card->companyAddress !== null) {
            $lines[] = "ADR;TYPE=WORK:;;" . $this->escapeText($card->companyAddress) . ";;;;";
        }

        // 11. Revision timestamp
        $lines[] = "REV:" . gmdate('Y-m-d\TH:i:s\Z');

        $lines[] = 'END:VCARD';

        // Fold lines at 75 octets and join with CRLF
        $foldedLines = [];
        foreach ($lines as $line) {
            $foldedLines[] = $this->foldLine($line, 75);
        }

        return implode("\r\n", $foldedLines) . "\r\n";
    }

    /**
     * Escape special characters according to RFC 2426 Section 2.4.2:
     * - Backslash (\) -> \\
     * - Comma (,) -> \,
     * - Semicolon (;) -> \;
     * - Newlines (\r\n, \r, \n) -> \n
     */
    public function escapeText(string $text): string
    {
        // First escape backslashes
        $escaped = str_replace('\\', '\\\\', $text);

        // Escape semicolons and commas
        $escaped = str_replace(';', '\;', $escaped);
        $escaped = str_replace(',', '\,', $escaped);

        // Normalize and escape newlines to literal '\n'
        $escaped = preg_replace("/\r\n|\r|\n/", '\n', $escaped) ?? $escaped;

        return $escaped;
    }

    /**
     * Fold lines longer than 75 octets (RFC 2426).
     * Continuation lines start with a single space.
     * Respects multi-byte UTF-8 character boundaries.
     */
    public function foldLine(string $line, int $maxBytes = 75): string
    {
        if (strlen($line) <= $maxBytes) {
            return $line;
        }

        $folded = '';
        $currentLine = '';
        $currentBytes = 0;
        $chars = mb_str_split($line, 1, 'UTF-8');

        foreach ($chars as $char) {
            $charBytes = strlen($char);
            $limit = ($folded === '') ? $maxBytes : ($maxBytes - 1); // 1 byte reserved for space prefix

            if ($currentBytes + $charBytes > $limit) {
                $folded .= ($folded === '' ? '' : "\r\n ") . $currentLine;
                $currentLine = $char;
                $currentBytes = $charBytes;
            } else {
                $currentLine .= $char;
                $currentBytes += $charBytes;
            }
        }

        if ($currentLine !== '') {
            $folded .= ($folded === '' ? '' : "\r\n ") . $currentLine;
        }

        return $folded;
    }

    /**
     * Generate sanitized ASCII slug filename (e.g. prenom-nom.vcf).
     */
    public function sanitizeFilename(PublicCardView $card): string
    {
        $name = trim($card->displayFirstName . ' ' . $card->displayLastName);
        if ($name === '') {
            $name = 'contact';
        }

        $transliterator = [
            'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a',
            'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
            'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i',
            'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'ö' => 'o', 'ø' => 'o',
            'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u',
            'ÿ' => 'y', 'ñ' => 'n', 'ç' => 'c',
            'À' => 'a', 'Á' => 'a', 'Â' => 'a', 'Ã' => 'a', 'Ä' => 'a',
            'È' => 'e', 'É' => 'e', 'Ê' => 'e', 'Ë' => 'e',
            'Ì' => 'i', 'Í' => 'i', 'Î' => 'i', 'Ï' => 'i',
            'Ò' => 'o', 'Ó' => 'o', 'Ô' => 'o', 'Ö' => 'o',
            'Ù' => 'u', 'Ú' => 'u', 'Û' => 'u', 'Ü' => 'u',
            'Ç' => 'c', 'Ñ' => 'n',
        ];

        $slug = strtr($name, $transliterator);
        $slug = strtolower($slug);
        $slug = preg_replace('/[^a-z0-9\-]+/', '-', $slug);
        $slug = trim((string) $slug, '-');

        if ($slug === '') {
            $slug = 'contact';
        }

        return $slug . '.vcf';
    }
}
