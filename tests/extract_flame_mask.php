<?php
$imgPath = 'C:/Users/kouam/.gemini/antigravity-ide/brain/87f611ee-a533-4f41-ab51-615cd502d44e/.user_uploaded/media_1793189148568.png';
$im = imagecreatefrompng($imgPath);
$w = imagesx($im);
$h = imagesy($im);

// Let's create an edge detection / mask
$mask = imagecreatetruecolor($w, $h);
$bgR = 74.3; $bgG = 86.3; $bgB = 105.1;

for ($y = 0; $y < $h; $y++) {
    for ($x = 0; $x < $w; $x++) {
        $rgb = imagecolorat($im, $x, $y);
        $r = ($rgb >> 16) & 0xFF;
        $g = ($rgb >> 8) & 0xFF;
        $b = $rgb & 0xFF;
        
        $diff = ($r - $bgR)*1.5 + ($g - $bgG)*0.5 - ($b - $bgB)*1.2;
        // If warm (red/orange)
        if ($r > $b - 10 && ($r + $g) > ($b * 1.4)) {
            imagesetpixel($mask, $x, $y, imagecolorallocate($mask, 255, 255, 255));
        } else {
            // Check if inner drop (yellowish) or outer (reddish)
            $warmth = $r - $b;
            if ($warmth > 8) {
                imagesetpixel($mask, $x, $y, imagecolorallocate($mask, 200, 200, 200));
            } else {
                imagesetpixel($mask, $x, $y, imagecolorallocate($mask, 0, 0, 0));
            }
        }
    }
}

imagepng($mask, __DIR__ . '/flame_mask.png');
echo "Saved flame_mask.png\n";
