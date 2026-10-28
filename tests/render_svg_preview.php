<?php

function getBarSvg(
    int $w = 1004,
    int $h = 650,
    string $rectoBg = '#FFFFFF',
    string $navyBg = '#0F172A',
    string $yellow = '#F59E0B',
    string $orange = '#EA580C',
    string $deepBlue = '#1E40AF',
    string $skyBlue = '#0284C7'
): string {
    // Slanted line equation: x = xTop - slope * y
    // xTop = 720, xBot = 370 => slope = (720 - 370) / 650 = 350 / 650 = 7 / 13 = 0.5384615
    $slope = (720 - 370) / 650;
    
    $l0 = fn($y) => round(720 - $slope * $y, 1);
    $l1 = fn($y) => round(707 - $slope * $y, 1);
    $l2 = fn($y) => round(694 - $slope * $y, 1);
    $l3 = fn($y) => round(681 - $slope * $y, 1);
    $l4 = fn($y) => round(668 - $slope * $y, 1);

    $y1 = 35;  // Yellow top shelf
    $y2 = 75;  // Orange top shelf
    $y3 = 115; // Deep Blue top shelf
    $y4 = 155; // Sky Blue top shelf

    // Points for Yellow Polygon:
    // (L4(y1), y1) -> (L0(y1), y1) -> (L0(650), 650) -> (L4(650), 650)
    $ptsYellow = "{$l4($y1)},{$y1} {$l0($y1)},{$y1} {$l0($h)},{$h} {$l4($h)},{$h}";

    // Points for Orange Polygon:
    // (L3(y2), y2) -> (L0(y2), y2) -> (L0(650), 650) -> (L3(650), 650)
    $ptsOrange = "{$l3($y2)},{$y2} {$l0($y2)},{$y2} {$l0($h)},{$h} {$l3($h)},{$h}";

    // Points for Deep Blue Polygon:
    // (L2(y3), y3) -> (L0(y3), y3) -> (L0(650), 650) -> (L2(650), 650)
    $ptsDeepBlue = "{$l2($y3)},{$y3} {$l0($y3)},{$y3} {$l0($h)},{$h} {$l2($h)},{$h}";

    // Points for Sky Blue Polygon:
    // (L1(y4), y4) -> (L0(y4), y4) -> (L0(650), 650) -> (L1(650), 650)
    $ptsSkyBlue = "{$l1($y4)},{$y4} {$l0($y4)},{$y4} {$l0($h)},{$h} {$l1($h)},{$h}";

    // Points for Dark Navy Polygon (completely straight diagonal edge on left!):
    // (L0(0), 0) -> (w, 0) -> (w, h) -> (L0(h), h)
    $ptsNavy = "{$l0(0)},0 {$w},0 {$w},{$h} {$l0($h)},{$h}";

    return <<<SVG
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 {$w} {$h}" width="{$w}" height="{$h}">
    <!-- Background (White on left) -->
    <rect width="{$w}" height="{$h}" fill="{$rectoBg}"/>

    <!-- Layer 1: Yellow / Amber -->
    <polygon points="{$ptsYellow}" fill="{$yellow}"/>

    <!-- Layer 2: Orange / Coral -->
    <polygon points="{$ptsOrange}" fill="{$orange}"/>

    <!-- Layer 3: Deep Blue -->
    <polygon points="{$ptsDeepBlue}" fill="{$deepBlue}"/>

    <!-- Layer 4: Sky Blue -->
    <polygon points="{$ptsSkyBlue}" fill="{$skyBlue}"/>

    <!-- Layer 5: Dark Navy section (Straight clean diagonal line!) -->
    <polygon points="{$ptsNavy}" fill="{$navyBg}"/>

    <!-- SIP Watermark Flame -->
    <g transform="translate(730, 40) scale(1.15)">
        <path d="M52 0 C54 18 68 34 76 54 C88 80 84 108 62 126 C44 140 18 136 6 114 C-2 98 0 80 12 58 C15 54 22 56 22 62 C16 78 18 96 28 106 C40 118 60 114 70 98 C78 84 76 66 66 48 C60 36 54 22 52 0 Z" fill="#DC2626" opacity="0.18"/>
        <path d="M35 28 C42 42 56 60 56 80 C56 98 44 110 32 108 C20 106 14 92 18 78 C22 64 28 48 35 28 Z" fill="#F59E0B" opacity="0.22"/>
    </g>
</svg>
SVG;
}

$svg = getBarSvg();
file_put_contents(__DIR__ . '/test_bar.svg', $svg);
echo "SVG written to test_bar.svg\n";
