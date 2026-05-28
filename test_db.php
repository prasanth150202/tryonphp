<?php
header('Content-Type: application/json');
$result = [];
try {
    require_once __DIR__ . '/config/AppConfig.php';
    $result['appconfig'] = 'ok';
} catch (\Throwable $e) {
    echo json_encode(['step' => 'appconfig', 'error' => $e->getMessage()]); exit;
}
try {
    require_once __DIR__ . '/shared/helpers.php';
    $result['helpers'] = 'ok';
} catch (\Throwable $e) {
    echo json_encode(['step' => 'helpers', 'error' => $e->getMessage()]); exit;
}
try {
    require_once __DIR__ . '/db/Database.php';
    $pdo = \TryFit\Db\Database::getInstance();
    $result['db'] = 'ok';
} catch (\Throwable $e) {
    echo json_encode(['step' => 'db', 'error' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()]); exit;
}
try {
    require_once __DIR__ . '/db/MerchantRepo.php';
    $repo = new \TryFit\Db\MerchantRepo();
    $merchant = $repo->findByDomain('fitfyce.myshopify.com');
    $result['merchant'] = $merchant;
} catch (\Throwable $e) {
    echo json_encode(['step' => 'merchant', 'error' => $e->getMessage(), 'file' => $e->getFile(), 'line' => $e->getLine()]); exit;
}
echo json_encode($result);
