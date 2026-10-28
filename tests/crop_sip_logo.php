<?php
$imgPath = 'C:/Users/kouam/.gemini/antigravity-ide/brain/87f611ee-a533-4f41-ab51-615cd502d44e/.user_uploaded/media_1793189048243.png';
$im = imagecreatefrompng($imgPath);

$crop = imagecrop($im, ['x' => 0, 'y' => 0, 'width' => 200, 'height' => 150]);
imagepng($crop, __DIR__ . '/crop_sip_logo.png');
echo "Saved crop_sip_logo.png\n";
