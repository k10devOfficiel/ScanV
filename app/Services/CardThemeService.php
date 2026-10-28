<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Service managing available business card themes and palettes.
 */
class CardThemeService
{
    /**
     * Complete catalogue of the 10 available themes.
     *
     * @var array<string, array{
     *     key: string,
     *     name: string,
     *     subtitle: string,
     *     primary: string,
     *     accent: string,
     *     rectoBg: string,
     *     rectoText: string,
     *     rectoMuted: string,
     *     versoBg: string,
     *     versoText: string
     * }>
     */
    private const THEMES = [
        'default' => [
            'key' => 'default',
            'name' => 'Classique Indigo',
            'subtitle' => 'Minimaliste, Corporate & Épuré (Par défaut)',
            'primary' => '#4338CA',
            'accent' => '#6366F1',
            'rectoBg' => '#FFFFFF',
            'rectoText' => '#0F172A',
            'rectoMuted' => '#475569',
            'versoBg' => '#4338CA',
            'versoText' => '#FFFFFF',
        ],
        'prestige_gold' => [
            'key' => 'prestige_gold',
            'name' => 'Ébène & Or',
            'subtitle' => 'Prestige, Luxe & Haute Direction',
            'primary' => '#D97706',
            'accent' => '#F59E0B',
            'rectoBg' => '#0F172A',
            'rectoText' => '#F8FAFC',
            'rectoMuted' => '#D97706',
            'versoBg' => '#090D16',
            'versoText' => '#F8FAFC',
        ],
        'emerald_lagoon' => [
            'key' => 'emerald_lagoon',
            'name' => 'Émeraude Abidjan',
            'subtitle' => 'Nature, RSE & Finance Durable',
            'primary' => '#047857',
            'accent' => '#10B981',
            'rectoBg' => '#F0FDF4',
            'rectoText' => '#064E3B',
            'rectoMuted' => '#047857',
            'versoBg' => '#065F46',
            'versoText' => '#FFFFFF',
        ],
        'ocean_tech' => [
            'key' => 'ocean_tech',
            'name' => 'Océan Tech & Cyan',
            'subtitle' => 'Start-up, Digital & Innovation',
            'primary' => '#1E40AF',
            'accent' => '#06B6D4',
            'rectoBg' => '#FFFFFF',
            'rectoText' => '#0F172A',
            'rectoMuted' => '#1E40AF',
            'versoBg' => '#1E3A8A',
            'versoText' => '#FFFFFF',
        ],
        'sunset_orange' => [
            'key' => 'sunset_orange',
            'name' => 'Coucher de Soleil',
            'subtitle' => 'Chaleureux, Créatif & Énergique',
            'primary' => '#EA580C',
            'accent' => '#F97316',
            'rectoBg' => '#FFFBEB',
            'rectoText' => '#1C1917',
            'rectoMuted' => '#C2410C',
            'versoBg' => '#C2410C',
            'versoText' => '#FFFFFF',
        ],
        'burgundy_luxe' => [
            'key' => 'burgundy_luxe',
            'name' => 'Bordeaux Exécutif',
            'subtitle' => 'Conseil, Cabinet & Excellence',
            'primary' => '#881337',
            'accent' => '#BE123C',
            'rectoBg' => '#FFF5F7',
            'rectoText' => '#4C0519',
            'rectoMuted' => '#881337',
            'versoBg' => '#881337',
            'versoText' => '#FFFFFF',
        ],
        'slate_minimal' => [
            'key' => 'slate_minimal',
            'name' => 'Ardoise Moderne',
            'subtitle' => 'Architecture, Design & Sobriété',
            'primary' => '#334155',
            'accent' => '#64748B',
            'rectoBg' => '#F8FAFC',
            'rectoText' => '#0F172A',
            'rectoMuted' => '#475569',
            'versoBg' => '#1E293B',
            'versoText' => '#FFFFFF',
        ],
        'royal_violet' => [
            'key' => 'royal_violet',
            'name' => 'Violet Impérial',
            'subtitle' => 'Média, Culture & Événementiel',
            'primary' => '#6D28D9',
            'accent' => '#8B5CF6',
            'rectoBg' => '#FAF5FF',
            'rectoText' => '#2E1065',
            'rectoMuted' => '#6D28D9',
            'versoBg' => '#5B21B6',
            'versoText' => '#FFFFFF',
        ],
        'warm_caramel' => [
            'key' => 'warm_caramel',
            'name' => 'Caramel & Cacao',
            'subtitle' => 'Authentique, Terroir & Artisanal',
            'primary' => '#78350F',
            'accent' => '#B45309',
            'rectoBg' => '#FEFCE8',
            'rectoText' => '#451A03',
            'rectoMuted' => '#78350F',
            'versoBg' => '#78350F',
            'versoText' => '#FFFFFF',
        ],
        'cobalt_silver' => [
            'key' => 'cobalt_silver',
            'name' => 'Cobalt Corporate',
            'subtitle' => 'Banque, Audit & Institutionnel',
            'primary' => '#1D4ED8',
            'accent' => '#3B82F6',
            'rectoBg' => '#F1F5F9',
            'rectoText' => '#0F172A',
            'rectoMuted' => '#1D4ED8',
            'versoBg' => '#1E3A8A',
            'versoText' => '#FFFFFF',
        ],
    ];

    /**
     * Get list of all available themes.
     *
     * @return array<string, array<string, string>>
     */
    public static function getAllThemes(): array
    {
        return self::THEMES;
    }

    /**
     * Check if a theme key is recognized.
     */
    public static function isValid(string $themeKey): bool
    {
        return isset(self::THEMES[$themeKey]);
    }

    /**
     * Normalize or fallback theme key.
     */
    public static function normalize(?string $themeKey): string
    {
        $clean = preg_replace('/[^a-z0-9_-]/i', '', strtolower(trim((string) $themeKey)));
        if (isset(self::THEMES[$clean])) {
            return $clean;
        }

        return 'default';
    }

    /**
     * Get theme metadata.
     *
     * @return array<string, string>
     */
    public static function getTheme(?string $themeKey): array
    {
        $clean = self::normalize($themeKey);
        return self::THEMES[$clean];
    }
}
