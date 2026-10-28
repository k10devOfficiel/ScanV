<?php
require_once __DIR__ . '/../vendor/autoload.php';

use App\Core\Database;
use App\Repositories\CardRepository;
use App\Services\CardRenderService;
use App\Services\QrCodeService;

$pdo = Database::getConnection();
$cardRepo = new CardRepository($pdo);
$card = $cardRepo->findById(1331);

$renderService = new CardRenderService($cardRepo, new QrCodeService());
$html = $renderService->renderFront($card, standalone: true, inlineCss: true);

file_put_contents(__DIR__ . '/full_card_front.html', $html);

$chrome = 'C:\Program Files\Google\Chrome\Application\chrome.exe';
$cmd = sprintf(
    '"%s" --headless=new --disable-gpu --no-sandbox --window-size=1004,650 --screenshot="%s" "%s"',
    $chrome,
    __DIR__ . '/full_card_front.png',
    __DIR__ . '/full_card_front.html'
);
exec($cmd, $out, $code);
echo "Full card rendered to full_card_front.png (size: " . filesize(__DIR__ . '/full_card_front.png') . " bytes)\n";
