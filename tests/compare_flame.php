<?php
$flameImg = 'C:/Users/kouam/.gemini/antigravity-ide/brain/87f611ee-a533-4f41-ab51-615cd502d44e/.user_uploaded/media_1793189148568.png';
$im = imagecreatefrompng($flameImg);
$w = imagesx($im);
$h = imagesy($im);

// Let's create an HTML comparing the photo with our SVG flame
$html = <<<HTML
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
body { background: #0F172A; color: white; font-family: sans-serif; display: flex; gap: 40px; padding: 30px; }
.box { text-align: center; }
img { border: 1px solid #444; width: 280px; height: 227px; object-fit: contain; }
svg { border: 1px solid #444; background: #0F172A; }
</style>
</head>
<body>
<div class="box">
    <h3>Photo de l'utilisateur (media_1793189148568.png)</h3>
    <img src="file:///{$flameImg}">
</div>
<div class="box">
    <h3>SVG Actuel</h3>
    <svg viewBox="0 0 100 135" width="200" height="270">
        <path d="M52 0 C54 18 68 34 76 54 C88 80 84 108 62 126 C44 140 18 136 6 114 C-2 98 0 80 12 58 C15 54 22 56 22 62 C16 78 18 96 28 106 C40 118 60 114 70 98 C78 84 76 66 66 48 C60 36 54 22 52 0 Z" fill="#DC2626" opacity="0.35"/>
        <path d="M35 28 C42 42 56 60 56 80 C56 98 44 110 32 108 C20 106 14 92 18 78 C22 64 28 48 35 28 Z" fill="#F59E0B" opacity="0.45"/>
    </svg>
</div>
</body>
</html>
HTML;

file_put_contents(__DIR__ . '/compare_flame.html', $html);

$chrome = 'C:\Program Files\Google\Chrome\Application\chrome.exe';
$cmd = sprintf(
    '"%s" --headless=new --disable-gpu --no-sandbox --window-size=700,450 --screenshot="%s" "%s"',
    $chrome,
    __DIR__ . '/compare_flame.png',
    __DIR__ . '/compare_flame.html'
);
exec($cmd, $out, $code);
echo "Screenshot rendered to compare_flame.png\n";
