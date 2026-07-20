<?php

declare(strict_types=1);

namespace TryFit\Controllers;

use TryFit\AppConfig;
use TryFit\Db\MerchantRepo;
use VTON\OpenAIClient;

/**
 * Infographic generation — OpenAI only, no Fashn involved anywhere in this flow:
 *  Step 1 — OpenAI (gpt-4o-mini) extracts 3–5 short key-point phrases from the description
 *  Step 2 — OpenAI (gpt-image-1) composes the final infographic image from the source
 *           photo (Fashn-generated model image or a merchant-uploaded image) + key points
 */
class InfographicController
{
    private const VALID_STYLES     = ['fashion', 'luxury', 'modern', 'minimal', 'ecommerce', 'catalog', 'collage', 'lifestyle', 'radial'];
    private const VALID_CATEGORIES = ['clothing', 'food'];

    // Maps a merchant-facing size choice to the literal dimensions gpt-image-1
    // accepts. Left unspecified (null), the caller falls back to the existing
    // category-based default (square for food, portrait otherwise).
    private const SIZE_MAP = [
        'square'    => '1024x1024',
        'portrait'  => '1024x1536',
        'landscape' => '1536x1024',
    ];

    // ── Public entry points ────────────────────────────────────────────────────

    /** OpenAI extracts key points → OpenAI composes infographic (single request). */
    public function create(): void
    {
        try { $this->doCreate(); }
        catch (\Throwable $e) { $this->logAndRespond('create', $e); }
    }

    /** Step 1 only — extract key points via OpenAI. */
    public function extract(): void
    {
        try { $this->doExtract(); }
        catch (\Throwable $e) { $this->logAndRespond('extract', $e); }
    }

    /** Step 2 only — compose infographic via OpenAI from pre-extracted key points. */
    public function generate(): void
    {
        try { $this->doGenerate(); }
        catch (\Throwable $e) { $this->logAndRespond('generate', $e); }
    }

    /** Apply a merchant-written edit prompt to an already-generated infographic. */
    public function edit(): void
    {
        try { $this->doEdit(); }
        catch (\Throwable $e) { $this->logAndRespond('edit', $e); }
    }

    private function logAndRespond(string $action, \Throwable $e): void
    {
        writeLog('ERROR', "Infographic {$action} failed", [
            'message' => $e->getMessage(),
            'class'   => get_class($e),
            'file'    => $e->getFile() . ':' . $e->getLine(),
        ]);
        respondJson(['error' => $e->getMessage()], 500);
    }

    // ── /infographic/create ────────────────────────────────────────────────────

    private function doCreate(): void
    {
        // 200s — high-quality gpt-image-1 generation at portrait size can
        // take well over a minute on its own, on top of the key-point
        // extraction and image download. The 'collage' style makes 4 such
        // calls concurrently (see composeAndSaveCollage) instead of 1, so
        // this needs headroom for the slowest of the 4, not 4x the single
        // budget.
        @set_time_limit(200);

        $openai = $this->requireOpenAI();
        if (!$this->authMerchant()) { return; }

        $payload         = $this->readJson();
        $description     = trim((string)($payload['description'] ?? ''));
        $imageUrl        = trim((string)($payload['product_image_url'] ?? ''));
        $style           = $this->resolveStyle($payload['style'] ?? '');
        $customStyle     = trim((string)($payload['custom_style_prompt'] ?? ''));
        $category        = $this->resolveCategory($payload['product_category'] ?? '');
        $size            = $this->resolveSize((string)($payload['size'] ?? ''));

        if ($description === '') { respondJson(['error' => 'description is required'], 400); return; }
        if ($imageUrl    === '') { respondJson(['error' => 'product_image_url is required'], 400); return; }

        // Step 1 — OpenAI extracts key points
        $keyPoints = $openai->extractKeyPoints($description);
        if (empty($keyPoints)) {
            respondJson(['error' => 'Could not extract key points — try rephrasing the description.'], 422);
            return;
        }

        // 'collage' produces 4 separate images (see composeAndSaveCollage);
        // every other style produces exactly one.
        if ($style === 'collage') {
            $results = $this->composeAndSaveCollage($openai, $imageUrl, $keyPoints, $description, $customStyle, $size);
            respondJson(['results' => $results, 'key_points' => $keyPoints]);
            return;
        }

        // Step 2 — OpenAI composes the infographic image
        $resultUrl = $this->composeAndSave($openai, $imageUrl, $keyPoints, $style, $description, $customStyle, $category, 1, $size);

        respondJson(['results' => $this->toResults($resultUrl, $style, 1), 'key_points' => $keyPoints]);
    }

    // ── /infographic/extract-points ───────────────────────────────────────────

    private function doExtract(): void
    {
        $openai = $this->requireOpenAI();
        if (!$this->authMerchant()) { return; }

        $payload     = $this->readJson();
        $description = trim((string)($payload['description'] ?? ''));
        if ($description === '') { respondJson(['error' => 'description is required'], 400); return; }

        $keyPoints = $openai->extractKeyPoints($description);
        if (empty($keyPoints)) {
            respondJson(['error' => 'Could not extract key points — try rephrasing the description.'], 422);
            return;
        }
        respondJson(['key_points' => $keyPoints]);
    }

    // ── /infographic/generate ─────────────────────────────────────────────────

    private function doGenerate(): void
    {
        // 200s — see doCreate() for why (collage's 4 concurrent calls).
        @set_time_limit(200);
        if (!$this->authMerchant()) { return; }
        $openai = $this->requireOpenAI();

        $payload      = $this->readJson();
        $imageUrl     = trim((string)($payload['product_image_url'] ?? ''));
        $description  = trim((string)($payload['description'] ?? ''));
        $customStyle  = trim((string)($payload['custom_style_prompt'] ?? ''));
        $category     = $this->resolveCategory($payload['product_category'] ?? '');
        $variation    = max(1, min(5, (int)($payload['variation'] ?? 1)));
        $size         = $this->resolveSize((string)($payload['size'] ?? ''));
        $keyPoints = array_values(array_filter(
            array_map(fn($p) => is_string($p) ? trim($p) : null, (array)($payload['key_points'] ?? [])),
            fn($p) => $p !== null && $p !== ''
        ));
        $style = $this->resolveStyle($payload['style'] ?? '');

        if ($imageUrl    === '') { respondJson(['error' => 'product_image_url is required'], 400); return; }
        if (empty($keyPoints)) { respondJson(['error' => 'key_points array is required'], 400); return; }

        if ($style === 'collage') {
            $results = $this->composeAndSaveCollage($openai, $imageUrl, $keyPoints, $description, $customStyle, $size);
            respondJson(['results' => $results, 'key_points' => $keyPoints]);
            return;
        }

        $resultUrl = $this->composeAndSave($openai, $imageUrl, $keyPoints, $style, $description, $customStyle, $category, $variation, $size);
        respondJson(['results' => $this->toResults($resultUrl, $style, $variation), 'key_points' => $keyPoints]);
    }

    // ── /infographic/edit ──────────────────────────────────────────────────────

    private function doEdit(): void
    {
        @set_time_limit(170);
        $openai = $this->requireOpenAI();
        if (!$this->authMerchant()) { return; }

        $payload      = $this->readJson();
        $imageUrl     = trim((string)($payload['image_url'] ?? ''));
        $editPrompt   = trim((string)($payload['edit_prompt'] ?? ''));
        $bgImageUrl   = trim((string)($payload['background_image_url'] ?? ''));

        if ($imageUrl   === '') { respondJson(['error' => 'image_url is required'], 400); return; }
        if ($editPrompt === '') { respondJson(['error' => 'edit_prompt is required'], 400); return; }

        $tempPath = $this->downloadImage($imageUrl);
        if ($tempPath === null) {
            respondJson(['error' => 'Could not load the image to edit. Check the URL and try again.'], 400);
            return;
        }

        $bgTempPath = null;
        if ($bgImageUrl !== '') {
            $bgTempPath = $this->downloadImage($bgImageUrl);
            if ($bgTempPath === null) {
                @unlink($tempPath);
                respondJson(['error' => 'Could not load the background image. Check the URL and try again.'], 400);
                return;
            }
        }

        try {
            $imageBytes = $openai->editInfographicImage($tempPath, $editPrompt, $bgTempPath);
        } finally {
            @unlink($tempPath);
            if ($bgTempPath !== null) { @unlink($bgTempPath); }
        }

        $resultUrl = $this->saveGenerated($imageBytes);
        respondJson(['results' => $this->toResults($resultUrl, 'edited', 1)]);
    }

    /**
     * The frontend (InfographicAddon + the standalone Marketing Infographic
     * workflow) renders a gallery of `{key, label, image}` entries — this
     * endpoint only ever produces one image per request, so wrap it as a
     * single-element array to match that contract. $variation is folded into
     * `key` so repeated "Regenerate" calls produce unique React keys when the
     * frontend appends results into the same gallery array.
     */
    private function toResults(string $resultUrl, string $style, int $variation): array
    {
        return [[
            'key'   => $style . '-v' . $variation,
            'label' => $variation > 1 ? "Variation {$variation}" : ucfirst($style),
            'image' => $resultUrl,
        ]];
    }

    // ── OpenAI infographic composer ───────────────────────────────────────────

    /**
     * $imageUrl is either the Fashn-generated model/result photo from one of
     * the try-on workflows, or a photo the merchant uploaded directly — both
     * are just a source image handed to OpenAI's image model. Fashn is never
     * called from this class.
     */
    private function composeAndSave(
        OpenAIClient $openai,
        string $imageUrl,
        array $keyPoints,
        string $style,
        string $description = '',
        string $customStylePrompt = '',
        string $category = 'clothing',
        int $variation = 1,
        ?string $size = null
    ): string {
        $tempPath = $this->downloadImage($imageUrl);
        if ($tempPath === null) {
            throw new \RuntimeException('Could not load the product image. Check the URL and try again.');
        }

        try {
            $imageBytes = $openai->generateInfographicImage($tempPath, $keyPoints, $style, $description, $customStylePrompt, $category, $variation, $size);
        } finally {
            @unlink($tempPath);
        }

        return $this->saveGenerated($imageBytes);
    }

    /**
     * 'collage' produces 4 separate standalone images (hero, two detail
     * zooms, features) instead of one — this saves each and returns 4
     * gallery entries instead of the single-element array toResults() makes.
     */
    private function composeAndSaveCollage(
        OpenAIClient $openai,
        string $imageUrl,
        array $keyPoints,
        string $description,
        string $customStylePrompt,
        ?string $size = null
    ): array {
        $tempPath = $this->downloadImage($imageUrl);
        if ($tempPath === null) {
            throw new \RuntimeException('Could not load the product image. Check the URL and try again.');
        }

        try {
            $panels = $openai->generateCollagePanels($tempPath, $keyPoints, $description, $customStylePrompt, $size);
        } finally {
            @unlink($tempPath);
        }

        $labels = [
            'hero'     => 'Hero Shot',
            'detail1'  => 'Detail 1',
            'detail2'  => 'Detail 2',
            'features' => 'Features',
        ];

        $results = [];
        foreach ($panels as $key => $bytes) {
            $results[] = [
                'key'   => 'collage-' . $key . '-' . uniqid('', true),
                'label' => $labels[$key] ?? ucfirst($key),
                'image' => $this->saveGenerated($bytes),
            ];
        }
        return $results;
    }

    private function saveGenerated(string $imageBytes): string
    {
        $dir      = AppConfig::resultsDir();
        $filename = 'infographic_' . uniqid('', true) . '.png';
        $outPath  = $dir . DIRECTORY_SEPARATOR . $filename;
        if (file_put_contents($outPath, $imageBytes) === false) {
            throw new \RuntimeException('Failed to save the generated infographic.');
        }

        return baseUrl() . basePath() . '/results/' . $filename;
    }

    // ── Shared helpers ─────────────────────────────────────────────────────────

    private function requireOpenAI(): OpenAIClient
    {
        $key = AppConfig::openAiApiKey();
        if ($key === '') {
            respondJson(['error' => 'OpenAI API key is not configured.'], 503);
            exit;
        }
        return new OpenAIClient($key);
    }

    private function authMerchant(): bool
    {
        $shop = trim((string)($_GET['shop'] ?? ''));
        if ($shop === '') { respondJson(['error' => 'shop parameter is required'], 400); return false; }
        $mRepo    = new MerchantRepo();
        $merchant = $mRepo->findByDomain($shop) ?? $mRepo->findByDomainLike($shop);
        if ($merchant === null || (int)$merchant['is_active'] !== 1) {
            respondJson(['error' => 'Unauthorized'], 401);
            return false;
        }
        return true;
    }

    private function resolveStyle(string $raw): string
    {
        $s = strtolower(trim($raw));
        return in_array($s, self::VALID_STYLES, true) ? $s : 'modern';
    }

    private function resolveCategory(string $raw): string
    {
        $c = strtolower(trim($raw));
        return in_array($c, self::VALID_CATEGORIES, true) ? $c : 'clothing';
    }

    /** Returns null (meaning "use the category-based default") for anything not a recognized size key. */
    private function resolveSize(string $raw): ?string
    {
        $s = strtolower(trim($raw));
        return self::SIZE_MAP[$s] ?? null;
    }

    private function readJson(): array
    {
        $decoded = json_decode((string)file_get_contents('php://input'), true);
        return is_array($decoded) ? $decoded : [];
    }

    private function downloadImage(string $url): ?string
    {
        if ($url === '') return null;

        if (!str_starts_with($url, 'http')) {
            $local = dirname(__DIR__) . '/' . ltrim($url, '/');
            return is_readable($local) ? $local : null;
        }

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 20,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_SSL_VERIFYPEER => false,
        ]);
        $bytes = curl_exec($ch);
        $code  = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if (!$bytes || $code >= 400) return null;

        $ext  = preg_match('/\.(jpe?g|png|webp)$/i', $url, $m) ? '.' . $m[1] : '.jpg';
        $path = sys_get_temp_dir() . '/infographic_' . uniqid('', true) . $ext;
        file_put_contents($path, $bytes);
        return $path;
    }
}
