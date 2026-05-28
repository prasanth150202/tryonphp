<?php
/**
 * Browser-based API tester.
 * Access: https://tryonapp.digifyce.com/api_test.php?key=migrate2025
 * DELETE from server after use.
 */
if (($_GET['key'] ?? '') !== 'migrate2025') {
    http_response_code(403);
    exit('Forbidden');
}

require_once __DIR__ . '/config/AppConfig.php';

$baseUrl   = 'https://tryonapp.digifyce.com';
$masterKey = \TryFit\AppConfig::masterApiKey();

// Strip protocol/trailing slashes from shop input
$rawShop    = $_GET['shop'] ?? 'make-a-combo.myshopify.com';
$shopDomain = preg_replace('#^https?://#', '', rtrim($rawShop, '/'));

function curlRequest(string $url, string $apiKey, string $method = 'GET', ?string $body = null): array {
    $ch = curl_init($url);
    $headers = ["X-Api-Key: {$apiKey}", "Accept: application/json"];
    if ($body !== null) {
        $headers[] = "Content-Type: application/json";
    }
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 15,
        CURLOPT_HTTPHEADER     => $headers,
        CURLOPT_CUSTOMREQUEST  => $method,
    ]);
    if ($body !== null) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
    }
    $resp   = curl_exec($ch);
    $status = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error  = curl_error($ch);
    curl_close($ch);
    return ['status' => $status, 'body' => $resp, 'error' => $error ?: null, 'parsed' => json_decode($resp, true)];
}

// ── Resolve merchant api_key via /merchant/by-domain ─────────────────────────
$merchantLookup = curlRequest("{$baseUrl}/merchant/by-domain?domain={$shopDomain}", $masterKey);
$merchantData   = $merchantLookup['parsed'] ?? [];
$merchantApiKey = $merchantData['api_key'] ?? null;
$merchantId     = $merchantData['id']      ?? null;
$lookupOk       = $merchantLookup['status'] === 200 && $merchantApiKey;
$activeKey      = $lookupOk ? $merchantApiKey : $masterKey;

// ── GET endpoints ─────────────────────────────────────────────────────────────
$getEndpoints = [
    'GET /products'           => '/products',
    'GET /shop/products'      => '/shop/products',
    'GET /settings'           => '/settings',
    'GET /widget/settings'    => '/widget/settings',
    'GET /shop'               => '/shop',
    'GET /plans'              => '/plans',
    'GET /plan/limit'         => '/plan/limit',
    'GET /analytics'          => '/analytics',
    'GET /merchant/by-domain' => "/merchant/by-domain?domain={$shopDomain}",
];

// ── POST test payloads ────────────────────────────────────────────────────────
$postEndpoints = [
    'POST /products/sync' => [
        'url'     => '/products/sync',
        'payload' => [
            'shopify_product_id'  => '9876543210',
            'shopify_product_gid' => 'gid://shopify/Product/9876543210',
            'collection_id'       => '123456789',
            'collection_title'    => 'Test Collection',
            'collection_handle'   => 'test-collection',
            'is_tryon_enabled'    => 1,
            'shopify_variants'    => [
                [
                    'id'                  => 'gid://shopify/ProductVariant/111111111',
                    'shopify_variant_id'  => '111111111',
                    'title'               => 'Small / Red',
                    'price'               => '29.99',
                    'sku'                 => 'SKU-S-RED',
                    'image_url'           => 'https://cdn.shopify.com/s/files/test-small-red.jpg',
                ],
                [
                    'id'                  => 'gid://shopify/ProductVariant/222222222',
                    'shopify_variant_id'  => '222222222',
                    'title'               => 'Medium / Blue',
                    'price'               => '29.99',
                    'sku'                 => 'SKU-M-BLUE',
                    'image_url'           => 'https://cdn.shopify.com/s/files/test-medium-blue.jpg',
                ],
            ],
        ],
    ],
    'POST /shopify/sync' => [
        'url'     => '/shopify/sync',
        'payload' => (object)[],
    ],
    'POST /merchant/register' => [
        'url'     => '/merchant/register',
        'payload' => [
            'shopify_domain'   => $shopDomain,
            'shopify_store_id' => 'test_store_id',
            'access_token'     => 'shpat_test_token',
        ],
    ],
];

// ── Handle request ────────────────────────────────────────────────────────────
$result        = null;
$selectedLabel = null;
$selectedUrl   = null;

if (isset($_GET['ep'])) {
    $ep = $_GET['ep'];

    if (isset($getEndpoints[$ep])) {
        $selectedLabel = $ep;
        $selectedUrl   = $baseUrl . $getEndpoints[$ep];
        $useKey        = ($ep === 'GET /merchant/by-domain') ? $masterKey : $activeKey;
        $result        = curlRequest($selectedUrl, $useKey);
    } elseif (isset($postEndpoints[$ep])) {
        $selectedLabel = $ep;
        $selectedUrl   = $baseUrl . $postEndpoints[$ep]['url'];
        $payload       = json_encode($postEndpoints[$ep]['payload'], JSON_UNESCAPED_SLASHES);
        $useKey        = ($ep === 'POST /merchant/register') ? $masterKey : $activeKey;
        $result        = curlRequest($selectedUrl, $useKey, 'POST', $payload);
    }
}

$testKey = urlencode($_GET['key']);
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>API Tester — TryOn</title>
<style>
  *{box-sizing:border-box}
  body{font-family:monospace;background:#0f172a;color:#e2e8f0;margin:0;padding:24px}
  h1{color:#38bdf8;margin-bottom:4px;font-size:20px}
  p.sub{margin:0 0 16px;color:#94a3b8;font-size:13px}
  .shop-form{display:flex;gap:8px;margin-bottom:12px;align-items:center}
  .shop-form input{background:#1e293b;border:1px solid #334155;color:#e2e8f0;padding:6px 10px;border-radius:6px;font-family:monospace;width:280px}
  .shop-form button{background:#0284c7;color:#fff;border:none;padding:6px 14px;border-radius:6px;cursor:pointer}
  .merchant-badge{font-size:12px;margin-bottom:20px;padding:6px 10px;border-radius:6px;display:inline-block}
  .badge-ok{background:#052e16;border:1px solid #166534;color:#4ade80}
  .badge-err{background:#2d0e0e;border:1px solid #7f1d1d;color:#f87171}
  h3{color:#94a3b8;font-size:12px;text-transform:uppercase;letter-spacing:.05em;margin:0 0 8px}
  .ep-list{display:flex;flex-wrap:wrap;gap:8px;margin-bottom:20px}
  .ep-btn{background:#1e293b;border:1px solid #334155;color:#93c5fd;padding:7px 12px;border-radius:6px;text-decoration:none;font-size:13px;white-space:nowrap}
  .ep-btn.post{color:#fca5a5;border-color:#7f1d1d}
  .ep-btn:hover{border-color:#38bdf8;color:#38bdf8}
  .ep-btn.post:hover{border-color:#f87171;color:#f87171}
  .ep-btn.active{background:#0c4a6e;border-color:#0284c7;color:#7dd3fc}
  .ep-btn.active.post{background:#3b0a0a;border-color:#dc2626;color:#fca5a5}
  .result-box{background:#1e293b;border:1px solid #334155;border-radius:8px;padding:16px;overflow:auto;max-height:65vh}
  .url-line{color:#64748b;font-size:12px;margin-bottom:8px;word-break:break-all}
  .status-ok{color:#4ade80}.status-err{color:#f87171}
  pre{margin:0;white-space:pre-wrap;word-break:break-all;font-size:13px;line-height:1.6}
  hr{border-color:#334155;margin:10px 0}
  .payload-box{background:#0f172a;border:1px solid #334155;border-radius:6px;padding:10px;font-size:12px;color:#94a3b8;margin-bottom:12px}
</style>
</head>
<body>
<h1>API Tester</h1>
<p class="sub">Resolves merchant api_key from domain so controllers receive the real merchant id.</p>

<form class="shop-form" method="get" action="">
  <input type="hidden" name="key" value="<?= htmlspecialchars($_GET['key']) ?>">
  <?php if (isset($_GET['ep'])): ?>
  <input type="hidden" name="ep" value="<?= htmlspecialchars($_GET['ep']) ?>">
  <?php endif; ?>
  <input type="text" name="shop" value="<?= htmlspecialchars($shopDomain) ?>" placeholder="xxx.myshopify.com">
  <button type="submit">Set Shop</button>
</form>

<?php if ($lookupOk): ?>
  <div class="merchant-badge badge-ok">
    Merchant found &mdash; id: <?= (int)$merchantId ?> &mdash; domain: <?= htmlspecialchars($shopDomain) ?>
  </div>
<?php else: ?>
  <div class="merchant-badge badge-err">
    Merchant NOT found for &ldquo;<?= htmlspecialchars($shopDomain) ?>&rdquo;
    (HTTP <?= (int)$merchantLookup['status'] ?>) &mdash; using master key
  </div>
<?php endif; ?>

<h3>GET Endpoints</h3>
<div class="ep-list">
<?php foreach ($getEndpoints as $label => $path): ?>
  <a class="ep-btn <?= $selectedLabel === $label ? 'active' : '' ?>"
     href="?key=<?= $testKey ?>&shop=<?= urlencode($shopDomain) ?>&ep=<?= urlencode($label) ?>">
    <?= htmlspecialchars($label) ?>
  </a>
<?php endforeach; ?>
</div>

<h3>POST Endpoints</h3>
<div class="ep-list">
<?php foreach ($postEndpoints as $label => $def): ?>
  <a class="ep-btn post <?= $selectedLabel === $label ? 'active' : '' ?>"
     href="?key=<?= $testKey ?>&shop=<?= urlencode($shopDomain) ?>&ep=<?= urlencode($label) ?>">
    <?= htmlspecialchars($label) ?>
  </a>
<?php endforeach; ?>
</div>

<?php if ($result): ?>
<div class="result-box">
  <div class="url-line"><?= htmlspecialchars($selectedLabel) ?> &rarr; <?= htmlspecialchars($selectedUrl) ?></div>
  <?php if (isset($postEndpoints[$selectedLabel])): ?>
  <div class="payload-box">Payload: <?= htmlspecialchars(json_encode($postEndpoints[$selectedLabel]['payload'], JSON_UNESCAPED_SLASHES)) ?></div>
  <?php endif; ?>
  <div>Status: <span class="<?= $result['status'] >= 200 && $result['status'] < 300 ? 'status-ok' : 'status-err' ?>"><?= (int)$result['status'] ?></span></div>
  <?php if ($result['error']): ?>
    <div class="status-err"><?= htmlspecialchars($result['error']) ?></div>
  <?php endif; ?>
  <hr>
  <pre><?= htmlspecialchars($result['parsed'] !== null
    ? json_encode($result['parsed'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
    : $result['body']) ?></pre>
</div>
<?php else: ?>
<div class="result-box"><pre style="color:#475569">← Click an endpoint above to test it.</pre></div>
<?php endif; ?>

</body>
</html>
