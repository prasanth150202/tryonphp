<?php
/**
 * Verifies products_json and enabled_products are correct in merchant_settings.
 * Access: https://tryonapp.digifyce.com/verify_products_json.php?key=migrate2025
 * DELETE after use.
 */
if (($_GET['key'] ?? '') !== 'migrate2025') {
    http_response_code(403);
    exit(json_encode(['error' => 'Forbidden']));
}

header('Content-Type: application/json');

require_once __DIR__ . '/config/AppConfig.php';
require_once __DIR__ . '/db/Database.php';

$pdo = \TryFit\Db\Database::getInstance();
$out = [];

// 1. merchant_settings columns
$cols = $pdo->query("SHOW COLUMNS FROM merchant_settings")->fetchAll(\PDO::FETCH_COLUMN, 0);
$out['merchant_settings_columns'] = $cols;

// 2. products_json + enabled_products per merchant
$rows = $pdo->query(
    "SELECT merchant_id, shopify_domain,
            products_json, enabled_products,
            JSON_LENGTH(products_json)    AS total_products,
            JSON_LENGTH(enabled_products) AS enabled_count
     FROM merchant_settings ORDER BY merchant_id"
)->fetchAll();

$out['merchant_settings'] = array_map(function ($r) {
    $productsJson  = json_decode($r['products_json']  ?? 'null', true);
    $enabledJson   = json_decode($r['enabled_products'] ?? 'null', true);

    // Check shopify_domain is NOT in products_json
    $domainLeaked = false;
    if (is_array($productsJson)) {
        foreach ($productsJson as $p) {
            if (isset($p['shopify_domain'])) {
                $domainLeaked = true;
                break;
            }
        }
    }

    return [
        'merchant_id'        => $r['merchant_id'],
        'shopify_domain'     => $r['shopify_domain'],
        'total_products'     => (int)$r['total_products'],
        'enabled_count'      => (int)$r['enabled_count'],
        'domain_in_json'     => $domainLeaked ? 'FAIL — domain leaked into products_json' : 'OK — no domain',
        'products_json'      => $productsJson,
        'enabled_products'   => $enabledJson,
    ];
}, $rows);

// 3. Verify products table columns
$prodCols = $pdo->query("SHOW COLUMNS FROM products")->fetchAll(\PDO::FETCH_COLUMN, 0);
$out['products_columns'] = $prodCols;

// 4. Live products rows (without shopify_domain in output)
$products = $pdo->query(
    "SELECT id AS product_id, merchant_id, shopify_product_id, shopify_product_gid,
            collection_id, collection_title, collection_handle,
            is_tryon_enabled, created_at, updated_at
     FROM products ORDER BY merchant_id, collection_title ASC"
)->fetchAll();
$out['products_rows'] = $products;

echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
