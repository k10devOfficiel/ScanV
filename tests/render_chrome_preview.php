<?php
$svg = file_get_contents(__DIR__ . '/test_card_bg.svg');
$html = <<<HTML
<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
body { margin: 0; padding: 0; background: #333; display: flex; align-items: center; justify-content: center; height: 100vh; }
.card { width: 1004px; height: 650px; position: relative; overflow: hidden; box-shadow: 0 10px 30px rgba(0,0,0,0.5); }
.svg { width: 100%; height: 100%; display: block; }
</style>
</head>
<body>
<div class="card">
{$svg}
</div>
</body>
</html>
HTML;

file_put_contents(__DIR__ . '/preview_bar.html', $html);

$chrome = 'C:\Program Files\Google\Chrome\Application\chrome.exe';
$cmd = sprintf(
    '"%s" --headless=new --disable-gpu --no-sandbox --window-size=1004,650 --screenshot="%s" "%s"',
    $chrome,
    __DIR__ . '/preview_bar.png',
    __DIR__ . '/preview_bar.html'
);
exec($cmd, $out, $code);
echo "Chrome executed with code $code. Image size: " . (file_exists(__DIR__ . '/preview_bar.png') ? filesize(__DIR__ . '/preview_bar.png') : 0) . " bytes\n";
