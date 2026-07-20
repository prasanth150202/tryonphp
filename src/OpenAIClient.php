<?php

declare(strict_types=1);

namespace VTON;

/**
 * Client for the OpenAI API (chat completions + image generation).
 * Base URL   : https://api.openai.com/v1
 * Chat model : gpt-4o-mini   (key-point extraction)
 * Image model: gpt-image-1   (infographic composition — no Fashn involved)
 */
class OpenAIClient
{
    private const BASE_URL    = 'https://api.openai.com/v1';
    private const CHAT_MODEL  = 'gpt-4o-mini';
    private const IMAGE_MODEL = 'gpt-image-1';

    // Each style is a genuinely different design treatment, not just a
    // recolor of the same layout — 'look' sets the MOOD (not a fixed color
    // or scene) so the model invents a fresh palette/background scenario
    // every generation instead of repeating the same locked colors every
    // time; 'labels' sets how the key points are actually rendered (badges
    // vs. dashed leader lines vs. plain caption list, etc.) and its accent
    // color should follow whatever palette was invented for this generation,
    // not a fixed literal color either.
    private const STYLE_GUIDES = [
        'fashion' => [
            'look'   => 'a romantic, feminine editorial mood. Invent a fresh soft pastel or jewel-toned color ' .
                        'palette and a complementary background scenario for THIS generation specifically — pick ' .
                        'your own gradient direction, texture (bokeh light, soft floral line-art, fabric weave, ' .
                        'subtle seasonal motif, etc.), and color combination; do not default to the same palette ' .
                        'or scene every time, vary it generation to generation while keeping it tasteful',
            'labels' => 'bold rounded pill-shaped badges with a matching small line-icon beside the text, ' .
                        'positioned around the product with short thin connector lines linking each badge to the ' .
                        'product; pick a badge color and text color that complement the palette you invented above',
        ],
        'luxury' => [
            'look'   => 'a premium, warm editorial mood. Invent a fresh elevated color palette (cream, champagne, ' .
                        'blush, deep emerald, charcoal, etc. — your choice) and background scenario for THIS ' .
                        'generation — a radial or diagonal gradient, a subtle marbled/linen/paper texture, foil-' .
                        'style corner flourishes, an embossed pattern, or a soft shadow scene; do not default to ' .
                        'the same palette or texture every time, vary it generation to generation',
            'labels' => 'refined thin-weight serif text labels, no connector lines and no badges — just elegant, ' .
                        'generously spaced placement around the product for a premium editorial feel; pick a ' .
                        'text color that complements the palette you invented above',
        ],
        'modern' => [
            'look'   => 'a clean, contemporary editorial mood — never a flat single-tone or plain two-color ' .
                        'gradient on its own, that reads as empty/boring; the background must always combine a ' .
                        'gradient with at least one confident graphic element layered into it — pick one and ' .
                        'commit to it fully: bold abstract geometric shapes, a soft ambient light glow with visible ' .
                        'depth, a diagonal color-block split, or a subtle repeating textured pattern. Invent a ' .
                        'fresh cool-toned or bold color palette for THIS generation; do not default to the same ' .
                        'palette or scene every time, vary it generation to generation while keeping it clean and ' .
                        'modern, but never plain or empty-looking',
            'labels' => 'clean bold sans-serif text labels, each connected to a specific point on the product ' .
                        'with a thin dashed leader line starting from a small dot; pick an accent color that ' .
                        'complements the palette you invented above',
        ],
        'minimal' => [
            'look'   => 'a plain, deliberately uncluttered background with generous negative space — no texture ' .
                        'or pattern; the richness here comes from confident typography, spacing, and a single ' .
                        'soft natural shadow beneath the product, not decoration. You may vary the exact ' .
                        'background tone (white, off-white, very pale grey, very pale tint of any color) between ' .
                        'generations, but always keep it plain and calm',
            'labels' => 'simple caption-style text arranged in a tidy single-column list along one side or the ' .
                        'bottom of the frame — no connector lines, no badges, no icons, ultra minimal; pick a ' .
                        'single understated text color (charcoal, black, or a dark tone matching the background)',
        ],
        'ecommerce' => [
            'look'   => 'a bold, punchy retail-catalog mood. Invent a fresh vibrant color palette and background ' .
                        'scenario for THIS generation — a gradient, a sunburst/radial light-ray pattern, a dotted ' .
                        'or grid texture, or a bold color-block scene; do not default to the same palette or ' .
                        'scene every time, vary it generation to generation while keeping it punchy and energetic',
            'labels' => 'bold rectangular callout chips with a small colored icon, positioned around the product ' .
                        'with short thin connector lines, punchy retail-catalog feel; pick chip and text colors ' .
                        'that complement the palette you invented above',
        ],
        'catalog' => [
            'look'   => 'a crisp technical spec-sheet mood — a plain pale paper or fine blueprint-grid background ' .
                        'with subtle hairline grid lines or ruler tick marks along one edge, a restrained two-tone ' .
                        'palette (one neutral base plus one precise accent color you invent fresh this generation); ' .
                        'no busy texture, gradient, or lifestyle scene — the feel of a product manual diagram or a ' .
                        'museum placard, not a marketing scene; vary the exact accent color and grid tone between ' .
                        'generations while keeping it precise and uncluttered',
            'labels' => 'small numbered circular markers (01, 02, 03, ...) placed directly ON the product photo at ' .
                        'the exact spot each feature refers to, each linked by a short thin straight line to a ' .
                        'matching numbered row in a compact key/legend list running down one edge of the frame — ' .
                        'every legend row shows its number then the label text in a technical sans-serif or ' .
                        'monospace type; no pills, no chips, no icons; pick a single accent color for the ' .
                        'markers, connector lines, and legend numbers that complements the palette you invented ' .
                        'above',
        ],
        'lifestyle' => [
            'look'   => 'a full-bleed lifestyle mood — the product/model photo itself IS the background, extended ' .
                        'and softened to fill the entire frame edge-to-edge (not placed on a separate flat or ' .
                        'gradient background), optionally with a subtle complementary color-tint wash or gentle ' .
                        'vignette over the extended areas so text stays readable; invent a fresh accent color for ' .
                        'the labels that pops against THIS specific photo\'s tones, and vary that choice ' .
                        'generation to generation',
            'labels' => 'each key point rendered as a two-tier label — a short bold header word or two, with a ' .
                        'smaller descriptive phrase directly beneath it in a lighter weight — linked to its exact ' .
                        'point of reference on the product/model in the photo by a smooth flowing CURVED connector ' .
                        'line (never straight, never dashed); pick a label text color and curved-line color that ' .
                        'complement the palette you invented above',
        ],
        'radial' => [
            'look'   => 'a centered radial diagram mood — the product photo sits in the middle of the frame, ' .
                        'optionally inside a thin circular guide ring, on a calm plain or very softly gradiented ' .
                        'background; invent a fresh restrained color palette for THIS generation (a neutral base ' .
                        'plus one accent) and vary it generation to generation while keeping the background calm ' .
                        'enough that it never competes with the radial layout',
            'labels' => 'each key point placed as its own small icon inside a circular badge, arranged evenly ' .
                        'spaced around the product in a ring like spokes on a wheel, each badge linked to the ' .
                        'center product by a short straight or gently curved connector line of equal length, with ' .
                        'the label\'s text sitting directly beneath its icon badge; pick an icon/connector-line ' .
                        'accent color that complements the palette you invented above',
        ],
    ];

    // Short, human-readable name for each style's label mechanic, used only
    // to build the "do NOT use these other mechanics" negative constraint
    // below — gpt-image-1 kept converging every style toward the same safe
    // "elegant serif caption, no connector lines" look (the luxury mechanic)
    // regardless of which named style was requested, because the style
    // instruction was a small, easily-ignored fragment buried inside one
    // long shared prompt block. Naming the mechanic explicitly and telling
    // the model what NOT to draw fixes that convergence.
    private const STYLE_MECHANIC_NAMES = [
        'fashion'   => 'rounded pill badges with icons and connector lines',
        'luxury'    => 'plain thin serif captions with no connector lines and no badges',
        'modern'    => 'sans-serif text with dashed leader lines',
        'minimal'   => 'a plain single-column caption list with no lines, badges, or icons',
        'ecommerce' => 'bold rectangular callout chips with connector lines',
        'catalog'   => 'numbered circular markers on the product linked to a numbered legend list',
        'collage'   => 'a 2x2 multi-panel grid of cropped photo variants with icon badges and zoom-circle callouts',
        'lifestyle' => 'the photo itself as a full-bleed background with curved connector lines to two-tier (bold header + subtext) labels',
        'radial'    => 'icon badges arranged in a ring around the centered product, each linked to it by a connector line',
    ];

    // Each "Regenerate" variation must be a genuinely different COMPOSITION,
    // not just a reworded position hint — vague wording like "arc" vs.
    // "scattered" produced near-identical results (same badges, same corner
    // positions, same crop). These force the model into structurally
    // different layouts: column vs. row vs. grid vs. split vs. diagonal.
    private const VARIATION_HINTS = [
        'Shrink the product photo to occupy only the center 55% of the frame width. Arrange ALL labels in a ' .
            'single vertical column running down the left edge of the frame, stacked evenly spaced top to ' .
            'bottom, each connected to the product with a horizontal connector line. The right side of the ' .
            'frame stays empty background.',
        'Shrink the product photo to occupy only the top 65% of the frame height, leaving it more space below. ' .
            'Arrange ALL labels in a single horizontal row along the bottom of the frame, evenly spaced left to ' .
            'right, each connected upward to the product with a short connector line.',
        'Keep the product centered and roughly life-size within the frame. Arrange labels in a symmetric grid ' .
            'around it — two labels upper-left/upper-right at the same height, the remaining labels ' .
            'lower-left/lower-right at the same height, each at a consistent 45-degree angle from the product ' .
            'with connector lines of equal length.',
        'Shrink the product photo to occupy only the center 60% of the frame, positioned slightly toward one ' .
            'side. Split the labels into two vertical stacks — half stacked along the left edge, half stacked ' .
            'along the right edge — each stack evenly spaced, connected to the product with horizontal lines, ' .
            'product framed symmetrically between the two stacks.',
        'Keep the product photo large, filling most of the frame height. Arrange labels scattered at varying ' .
            'distances and diagonal angles from the product — no two labels at the same distance or angle from ' .
            'it — connected with connector lines of clearly different lengths, for an asymmetric dynamic layout.',
    ];

    // 'lifestyle' has no separate inset photo to shrink/reposition (the
    // photo IS the full-bleed background), so VARIATION_HINTS' "shrink to
    // X% / occupy top Y%" language doesn't apply — these vary the label
    // placement zone instead.
    private const LIFESTYLE_VARIATION_HINTS = [
        'Cluster all labels toward the upper-right and lower-left thirds of the frame, each with a curved ' .
            'connector line sweeping toward its point of reference on the product, leaving the center of the ' .
            'frame clear so the product itself stays the visual focus.',
        'Arrange labels in a loose arc around the top half of the frame, each curved connector line sweeping ' .
            'down toward a different point on the product below, like spokes fanning out from a wheel.',
        'Place all labels along one vertical side of the frame (left or right), stacked with even spacing, each ' .
            'with a curved connector line sweeping across to its point of reference on the product on the ' .
            'opposite side of the frame.',
        'Scatter labels individually near their exact point of reference on the product, each with a short, ' .
            'tightly curved connector line — no two labels clustered together, spaced out across the full height ' .
            'of the frame from top to bottom.',
        'Place labels in the lower third of the frame in a gentle upward arc, each curved connector line ' .
            'sweeping up to a different point on the product above, leaving the upper two-thirds of the frame ' .
            'clear.',
    ];

    // 'radial' keeps the product centered in every variation (that's the
    // point of the layout), so variations rotate/resize the ring instead of
    // repositioning the product.
    private const RADIAL_VARIATION_HINTS = [
        'Arrange the icon badges evenly spaced around a full circle surrounding the product, starting one badge ' .
            'directly above the product and continuing clockwise at equal angular intervals.',
        'Arrange the icon badges only around the top semicircle (the upper half) surrounding the product, evenly ' .
            'spaced left to right, leaving the lower half of the frame open.',
        'Arrange the icon badges at the four corners of an implied square surrounding the product (upper-left, ' .
            'upper-right, lower-left, lower-right), each at the same distance from the center.',
        'Arrange the icon badges in a wider, larger-radius circle around a smaller centered product, giving each ' .
            'connector line more visible length.',
        'Arrange the icon badges in a tighter, smaller-radius circle close around a larger centered product, ' .
            'giving each connector line a short, compact length.',
    ];

    private string $apiKey;

    public function __construct(string $apiKey)
    {
        $this->apiKey = $apiKey;
    }

    /**
     * Extract up to 4 short, de-duplicated key-point phrases from a product
     * description paragraph. Uses OpenAI chat completions (gpt-4o-mini).
     * Returns array of short phrases (2–5 words), never full sentences.
     */
    public function extractKeyPoints(string $paragraph): array
    {
        $system =
            'You are a product copywriter. Extract 3 to 4 key selling points from the product description. ' .
            'Rules: each point must be 2–5 words only — no full sentences, no verbs, no punctuation. Each point ' .
            'must be about a genuinely different attribute — never list the same feature twice using different ' .
            'wording (e.g. do not return both "Zari Border" and "Luxurious Zari Border"). ' .
            'Focus on materials, features, benefits, or quality markers. ' .
            'Return ONLY a JSON array of strings, nothing else. ' .
            'Example: ["Pure Silk Fabric","Hand Embroidered","Machine Washable","Free Size"]';

        $response = $this->chatCompletion([
            ['role' => 'system', 'content' => $system],
            ['role' => 'user',   'content' => $paragraph],
        ], 150);

        $raw = $response['choices'][0]['message']['content'] ?? '[]';
        $raw = preg_replace('/^```(?:json)?\s*|\s*```$/m', '', trim($raw));
        $points = json_decode($raw, true);
        if (!is_array($points)) {
            return [];
        }

        // Cap at 4 (not 5) — fewer labels means more breathing room on the
        // canvas and less chance of gpt-image-1 crowding/duplicating one.
        $clean = [];
        $seen  = [];
        foreach (array_slice($points, 0, 6) as $p) {
            if (count($clean) >= 4) {
                break;
            }
            if (!is_string($p)) {
                continue;
            }
            $p = trim($p, " \t\n\r\0.,;:\"'");
            if ($p === '' || str_word_count($p) > 6) {
                continue;
            }
            // De-dupe near-identical points (e.g. the same phrase mentioned
            // twice in the source description) — sending a duplicate into
            // the image prompt guarantees a duplicate label in the image.
            $key = strtolower(preg_replace('/[^a-z0-9]+/i', '', $p));
            if (isset($seen[$key])) {
                continue;
            }
            $seen[$key] = true;
            $clean[] = $p;
        }
        return $clean;
    }

    /**
     * Composes the final infographic image from a source product/model photo
     * plus the extracted key points, entirely via OpenAI's image model
     * (gpt-image-1, images/edits). Returns raw PNG image bytes.
     *
     * $style picks the actual label design (badges, serif captions, dashed
     * leader lines, minimal list, or retail chips — see STYLE_GUIDES) and the
     * overall MOOD — the exact color palette and background scenario are
     * intentionally left to the model to invent fresh each generation within
     * that mood, rather than locked to one fixed color/scene, so repeated
     * generations and "Regenerate" variations don't all look identical.
     * $category picks the overall composition: 'clothing' places the photo
     * off-center with room around it; 'food' centers the pack with an
     * optional headline banner. Both keep the source photo completely
     * untouched — no re-illustration.
     */
    public function generateInfographicImage(
        string $imagePath,
        array $keyPoints,
        string $style,
        string $description = '',
        string $customStylePrompt = '',
        string $category = 'clothing',
        int $variation = 1,
        ?string $size = null
    ): string {
        $guide  = self::STYLE_GUIDES[$style] ?? self::STYLE_GUIDES['modern'];

        // Forces contrast against the model's default "safe" look (plain
        // serif captions, no connector lines) that every style was
        // converging toward regardless of which one was requested.
        $forbiddenMechanics = implode('; ', array_values(array_diff_key(
            self::STYLE_MECHANIC_NAMES,
            [$style => true]
        )));
        $styleLock =
            "STYLE LOCK — you are generating the \"{$style}\" template specifically. Its label mechanic is: " .
            "{$guide['labels']}. Use exactly that mechanic and nothing else. Do NOT fall back to any of the " .
            "other templates' mechanics no matter how clean or elegant they seem, especially not: " .
            "{$forbiddenMechanics}. This generation must be clearly distinguishable at a glance from the app's " .
            "other named styles — do not default to a generic minimalist serif-caption look unless \"{$style}\" " .
            "is literally \"luxury\". ";

        // Numbered list, not a flat comma-separated string — gives the model
        // an explicit slot per label instead of one blob of text to parse,
        // which measurably reduces it losing count and duplicating one.
        $points = implode('; ', array_map(
            fn($p, $i) => ($i + 1) . '. "' . $p . '"',
            $keyPoints,
            array_keys($keyPoints)
        ));
        $pointCount = count($keyPoints);
        $variationHints = match ($style) {
            'lifestyle' => self::LIFESTYLE_VARIATION_HINTS,
            'radial'    => self::RADIAL_VARIATION_HINTS,
            default     => self::VARIATION_HINTS,
        };
        $layoutHint = $variationHints[($variation - 1) % count($variationHints)];
        // 'lifestyle' has no separate background — the source photo itself
        // fills the whole frame, so labels/connector lines legitimately sit
        // over open areas of the photo rather than being confined beside it.
        $photoIsFullBleedBackground = $style === 'lifestyle';

        $shared =
            "CRITICAL — the photographed pixels of the product/model themselves must never be redrawn, restyled, " .
            "re-illustrated, or reinterpreted — reuse the exact same photograph, unaltered. The photo's SIZE and " .
            "POSITION within the frame should follow the arrangement instructions below (they intentionally vary " .
            "between creative variations), but the content of the photo itself must not change. " .
            "If a person appears in the photo, their face, identity, skin tone, expression, and hair must stay " .
            "completely identical to the source photo — do not regenerate, beautify, reinterpret, or subtly " .
            "change the face in any way; treat the person exactly like the rest of the untouched photograph. " .
            "Render exactly {$pointCount} labels as {$guide['labels']} — one label per numbered item below, each " .
            "shown verbatim, each appearing in the image exactly once and nowhere else: {$points}. Never repeat, " .
            "duplicate, or partially re-render the same or a similar label a second time anywhere in the frame, " .
            "even in a shortened or truncated form — each of the {$pointCount} numbered items above corresponds " .
            "to exactly one label and no more. Before finishing, count the labels you have drawn and confirm the " .
            "total is exactly {$pointCount}. " .
            "Composition for this variation: {$layoutHint} " .
            ($customStylePrompt !== '' ? "Merchant's background/theme request — follow this closely: {$customStylePrompt}. " : '') .
            ($variation > 1 ? "This is a fresh creative variation — it must look structurally different from a " .
                "typical or previous layout of the same product (different label arrangement and photo " .
                "size/position per the composition instruction above, AND a freshly invented color palette and " .
                "background scenario per the style description above — do not reuse the same colors as a " .
                "previous variation). Keep the same label mechanic (badges vs. lines vs. plain captions, per the " .
                "style) and the same label text. " : '') .
            'PRODUCTION QUALITY — this must be indistinguishable from a real, professionally shot and composited ' .
            'e-commerce marketing asset — photorealistic, seamless, and polished throughout, with natural lighting ' .
            'and shadow continuity between the photo and the design elements around it. Avoid any flat, uncanny, ' .
            'or "obviously AI-generated" look. Triple-check the spelling of every single rendered word against the ' .
            'key points given above, letter by letter — zero typos, zero misspellings, zero invented words. Use a ' .
            'clear, consistent typographic hierarchy — all key-point labels the same readable size as each other ' .
            '(never one label huge and another tiny), a headline (if any) modestly larger than the labels, ' .
            'generous line-height so wrapped text never looks cramped. Keep a generous safe margin on all four ' .
            'edges of the frame — no label text, connector line, badge, or icon may be cut off, clipped, or touch ' .
            'the frame border. Leave clear empty space between every label and the next so nothing overlaps or ' .
            'collides. ' .
            ($photoIsFullBleedBackground
                ? 'Because the photo itself is the full-bleed background here, every label\'s text box may sit ' .
                  'directly over an open, uncluttered area of the photo (plain fabric, plain backdrop, empty ' .
                  'space) — if the area behind a label is busy, add a subtle soft translucent scrim behind just ' .
                  'that label so its text stays fully legible. No label text box may ever sit over a face, eyes, ' .
                  'or directly over the exact product/garment detail it is pointing to — position it just beside ' .
                  'that point instead, with the curved connector line bridging the gap. '
                : 'Every label (its full text box, badge, or chip) must sit entirely in the open background ' .
                  'area beside or around the product — never on top of the product/model photo itself, never ' .
                  'overlapping the garment, skin, or hair. Only the thin connector line (and its small dot, if ' .
                  'the style uses one) may cross into the product photo to reach its point of reference; the ' .
                  'label text itself always stays clear of the photo. ') .
            ($description !== '' ? "Product context for tone only, do not add extra body text: {$description}. " : '') .
            'High-quality commercial marketing look, no watermarks, no extra logos.';

        // Food packs are roughly square; a full-length garment/model photo
        // needs a taller frame — the extra vertical room also gives labels
        // more breathing space, which helps avoid the crowding/duplication
        // that a cramped square canvas encouraged. An explicit $size (from
        // the merchant's Square/Portrait/Landscape picker) overrides this
        // category-based default.
        $size = $size ?? ($category === 'food' ? '1024x1024' : '1024x1536');

        $prompt = $styleLock . ($photoIsFullBleedBackground
            // 'lifestyle' doesn't fit the food/clothing "inset photo + separate
            // background" split above — the photo IS the background in both
            // cases, so it gets one composition sentence regardless of category.
            ? 'Design a polished, visually rich marketing infographic built around this exact lifestyle photo — ' .
              "with the specific level of overlay/tint richness described here (follow it precisely): {$guide['look']}. " .
              "Do NOT add any headline, title, subtitle, tagline, banner text, product name, or any other text " .
              "beyond exactly the {$pointCount} labels described below — the image must contain only the " .
              '(now full-bleed) photo and those labels, nothing else written anywhere in the frame. Tall portrait ' .
              'composition. ' . $shared
            : match ($category) {
                'food' =>
                    'Design a polished, visually rich marketing infographic for a packaged food product — with the specific ' .
                    'level of background depth/richness described below (follow that description precisely — some ' .
                    'styles want rich texture and gradient, others want deliberate plainness) — built around this exact ' .
                    "product photo. Center the product photo on {$guide['look']}, filling most of the frame — a bold " .
                    'circular or soft rounded background shape behind it works well. Optionally add a short one-line ' .
                    'headline in a small banner near the top introducing the product. Square 1:1 composition. ' . $shared,
                default =>
                    'Design a polished, visually rich marketing infographic for an apparel/product photo — with the specific ' .
                    'level of background depth/richness described below (follow that description precisely — some ' .
                    'styles want rich texture and gradient, others want deliberate plainness) — built around this exact ' .
                    "photo. Place the photo slightly off-center on {$guide['look']}, leaving room around it for " .
                    'labels. Tall portrait composition. Do NOT add any headline, title, subtitle, tagline, banner ' .
                    "text, product name, or any other text beyond exactly the {$pointCount} labels — the image must " .
                    'contain only the product photo and those labels, nothing else written anywhere in the frame. ' .
                    $shared,
            });

        return $this->requestGeneratedImage([$imagePath], $prompt, $size);
    }

    // Fixed named palettes for the 'collage' style — one is picked at random
    // per generation and its literal hex values are embedded in all four
    // panel prompts identically. The other styles leave color "invent fresh
    // each generation" to gpt-image-1 on purpose, but that only works
    // within a single API call; four independent calls each inventing
    // their own palette would produce four images that don't visually
    // belong together. Picking one fixed palette in PHP and quoting the
    // exact hex codes into every call keeps the set coherent while still
    // varying between different generations/regenerations.
    private const COLLAGE_PALETTES = [
        ['background' => 'warm cream (#F3EEE4)',       'accent' => 'deep forest green (#1F5C3E)', 'text' => 'near-black charcoal (#1A1A1A)'],
        ['background' => 'soft blush pink (#F7E9E6)',  'accent' => 'deep rose (#8C3B4A)',          'text' => 'near-black charcoal (#1A1A1A)'],
        ['background' => 'pale sage grey-green (#E7EAE2)', 'accent' => 'warm terracotta clay (#B5622F)', 'text' => 'near-black charcoal (#1A1A1A)'],
        ['background' => 'soft powder blue (#E7EEF3)', 'accent' => 'deep navy (#1E3A5C)',          'text' => 'near-black charcoal (#1A1A1A)'],
        ['background' => 'warm ivory (#F6F1E8)',       'accent' => 'deep burgundy (#5C2331)',      'text' => 'near-black charcoal (#1A1A1A)'],
    ];

    /**
     * Generates the 'collage' style as FOUR separate standalone images (a
     * hero shot + two close-up detail shots with zoom-circle callouts + a
     * second features shot) rather than one stitched grid image — matching
     * how the reference social-media carousels are actually separate slide
     * images, not one composited picture. All four reuse the same single
     * source photo (re-cropped/re-zoomed per image, never a genuinely new
     * angle) and the same fixed color palette so they read as one matching
     * set. Returns ['hero' => bytes, 'detail1' => bytes, 'detail2' => bytes,
     * 'features' => bytes].
     */
    public function generateCollagePanels(
        string $imagePath,
        array $keyPoints,
        string $description = '',
        string $customStylePrompt = '',
        ?string $size = null
    ): array {
        // Portrait by default — matches how the reference social-media
        // carousel slides are usually framed. An explicit $size (from the
        // merchant's Square/Portrait/Landscape picker) overrides this for
        // all four panels so the set stays consistent with itself.
        $size = $size ?? '1024x1536';

        // Split in PHP, not left for the model to improvise — an earlier
        // single-grid-image version of this style left the distribution
        // ambiguous and produced a duplicated label, a truncated label, and
        // one panel with no callout at all. Fixed slices per image avoid
        // that; reuse of the same point across images is fine (the
        // reference creatives repeat claims across slides too).
        $half   = (int) ceil(count($keyPoints) / 2);
        $heroPoints     = array_slice($keyPoints, 0, $half);
        $featurePoints  = array_slice($keyPoints, $half) ?: $heroPoints;
        $detail1Label   = $heroPoints[0];
        $detail2Label   = $featurePoints[0];

        $palette = self::COLLAGE_PALETTES[array_rand(self::COLLAGE_PALETTES)];

        // All 4 panels are independent requests against the same source
        // photo, so they run concurrently via curl_multi rather than one
        // after another — see requestGeneratedImagesParallel().
        return $this->requestGeneratedImagesParallel([
            'hero'     => [
                'imagePaths' => [$imagePath],
                'prompt'     => $this->buildCollageBadgePrompt(1, $heroPoints, $palette, $description, $customStylePrompt),
                'size'       => $size,
            ],
            'detail1'  => [
                'imagePaths' => [$imagePath],
                'prompt'     => $this->buildCollageDetailPrompt(2, $detail1Label, $palette, $customStylePrompt),
                'size'       => $size,
            ],
            'detail2'  => [
                'imagePaths' => [$imagePath],
                'prompt'     => $this->buildCollageDetailPrompt(3, $detail2Label, $palette, $customStylePrompt),
                'size'       => $size,
            ],
            'features' => [
                'imagePaths' => [$imagePath],
                'prompt'     => $this->buildCollageBadgePrompt(4, $featurePoints, $palette, $description, $customStylePrompt),
                'size'       => $size,
            ],
        ]);
    }

    /** Shared fidelity/quality rules reused by every collage panel prompt. */
    private function collageSharedRules(int $imageNum, array $palette, string $customStylePrompt): string
    {
        return
            "This is image {$imageNum} of 4 in a matching product-creative set (a hero shot, two close-up detail " .
            "shots, and a features shot) — even though each is generated separately, they must all look like they " .
            "belong to the same set: identical background color, identical badge/label design, identical " .
            "typography style. Use EXACTLY this palette, no deviation: background {$palette['background']}, " .
            "badge/accent color {$palette['accent']}, text color {$palette['text']}. " .
            "CRITICAL — reuse the exact same source photograph, unaltered pixels, for the product/model — never " .
            "redraw, re-illustrate, or reinterpret it, only crop/zoom into it. Never invent a different angle or " .
            "pose that isn't actually visible in the source photo (no true back view if the source is a " .
            "front-facing photo, for example). If a person's face is visible, their face, identity, skin tone, " .
            "expression, and hair must stay completely identical to the source photo. " .
            ($customStylePrompt !== '' ? "Merchant's background/theme request — follow this closely: {$customStylePrompt}. " : '') .
            'PRODUCTION QUALITY — indistinguishable from a real, professionally shot and composited e-commerce ' .
            'social-media creative — photorealistic, seamless, polished. Avoid any flat, uncanny, or "obviously ' .
            'AI-generated" look. Triple-check the spelling of every rendered word, letter by letter — zero typos, ' .
            'zero misspellings, zero invented words. Keep a generous safe margin on all four edges of the frame — ' .
            'no text, line, badge, icon, or zoom-circle may be cut off, clipped, or touch the frame border. ' .
            'High-quality commercial marketing look, no watermarks, no extra logos.';
    }

    /** Prompt for the two "hero"/"features" collage panels — a wide crop + a stack of icon badges. */
    private function buildCollageBadgePrompt(int $imageNum, array $points, array $palette, string $description, string $customStylePrompt): string
    {
        $list = implode('; ', array_map(
            fn($p, $i) => ($i + 1) . '. "' . $p . '"',
            $points,
            array_keys($points)
        ));

        return
            "Design a clean product marketing shot. Place the source photo off-center, filling most of the frame, " .
            "on a plain {$palette['background']} background. Add a compact vertical stack of small rounded icon " .
            "badges beside the product. Render exactly " . count($points) . " badge(s), no more and no fewer, one " .
            "badge per numbered item below, each badge holding its ENTIRE quoted phrase as one single indivisible " .
            "unit of text — never split one quoted item across two badges, never merge two items into one badge, " .
            "never shorten a multi-word phrase down to one word: {$list}. Tall portrait composition. Do NOT add " .
            "any headline, title, or extra text beyond these badges. " .
            "Every badge must sit entirely in open background space, never on top of the product/model photo " .
            "itself. " .
            $this->collageSharedRules($imageNum, $palette, $customStylePrompt) .
            ($description !== '' ? " Product context for tone only, do not add extra body text: {$description}." : '');
    }

    /** Prompt for the two detail-zoom collage panels — a tight crop + one zoom-circle callout. */
    private function buildCollageDetailPrompt(int $imageNum, string $label, array $palette, string $customStylePrompt): string
    {
        return
            "Design a close-up product detail shot. Crop and zoom tightly into one specific region of the garment " .
            "from the source photo (e.g. a sleeve, collar, seam, or waistband — whichever is actually visible), " .
            "filling most of the frame, with a plain {$palette['background']} background around the edges where " .
            "needed. Add exactly ONE small circular zoom-inset bubble showing an extreme close-up magnification " .
            "of that exact spot, linked by a short thin dashed leader line directly to exactly ONE text label box " .
            "attached to that same line. That label must show the complete phrase \"{$label}\" as one single " .
            "indivisible unit of text — not split, not shortened to a single word from it — and it must not " .
            "appear anywhere else in the image as a separate freestanding badge. Do NOT add any other text. " .
            $this->collageSharedRules($imageNum, $palette, $customStylePrompt);
    }

    /**
     * Applies a merchant-provided free-text edit to an already-generated
     * infographic image (background swap, label tweak, color change, etc.)
     * via the same OpenAI image model, iterating on the existing artwork
     * instead of starting over.
     *
     * $backgroundImagePath, if given, is sent as a second reference image —
     * gpt-image-1's edits endpoint accepts multiple input images and can
     * composite them, so the merchant's uploaded photo becomes the new
     * background while the product/labels come from the first image.
     */
    public function editInfographicImage(string $imagePath, string $editPrompt, ?string $backgroundImagePath = null): string
    {
        $prompt =
            'This is a marketing infographic that was generated for an e-commerce product (the first image). ' .
            'Apply the following edit request while keeping everything else in the image — the product, the ' .
            'other labels, the headline — unchanged unless the request says otherwise: ' . $editPrompt . '. ' .
            ($backgroundImagePath !== null
                ? 'A second reference image is provided — use it as the new background for the infographic ' .
                  '(crop/extend it to fill the frame naturally). Keep the product photo and all labels from the ' .
                  'first image exactly as they are, just composited onto this new background. '
                : '') .
            'CRITICAL — the product/model photo itself must stay pixel-for-pixel exactly as it currently appears: ' .
            'the exact same scale, crop, framing, and position within the frame. Do not zoom, re-crop, resize, or ' .
            'reposition it, even slightly, while applying this edit. If a person appears in the photo, their face, ' .
            'identity, skin tone, expression, and hair must stay completely identical to how they appear right ' .
            'now — never regenerate, beautify, or alter the face. ' .
            'PRODUCTION QUALITY — this must remain indistinguishable from a real, professionally shot and ' .
            'composited e-commerce marketing asset — photorealistic and seamless, not flat or "AI-generated" ' .
            'looking. Never repeat, duplicate, or partially re-render any label a second time anywhere in the ' .
            'frame. Triple-check the spelling of every rendered word, letter by letter, against the existing ' .
            'label text — zero typos, zero misspellings. Keep a generous safe margin on all four edges of the ' .
            'frame — no text, line, badge, or icon may be cut off, clipped, or touch the frame border. Keep all ' .
            'label text the same consistent, readable size as each other unless the edit specifically asks to ' .
            'change sizing. Keep all existing text crisp, legible, and correctly spelled.';

        $imagePaths = $backgroundImagePath !== null ? [$imagePath, $backgroundImagePath] : [$imagePath];

        // Match the output size to whatever aspect the image being edited
        // already has, so an edit never squashes a portrait infographic
        // back into a square (or vice versa).
        $dims = @getimagesize($imagePath);
        $size = ($dims && $dims[1] > $dims[0]) ? '1024x1536' : '1024x1024';

        return $this->requestGeneratedImage($imagePaths, $prompt, $size);
    }

    private function requestGeneratedImage(array $imagePaths, string $prompt, string $size = '1024x1024'): string
    {
        $ch = $this->buildImageEditHandle($imagePaths, $prompt, $size);
        $raw      = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        return $this->decodeImageResponse($this->parseImageEditResponse($raw, $httpCode, $curlErr));
    }

    /**
     * Runs several images/edits requests concurrently via curl_multi instead
     * of one after another — the 'collage' style needs 4 separate gpt-image-1
     * calls per generation, and each can individually take up to a minute or
     * more at high quality/portrait size, so running them sequentially risked
     * a 2-4 minute round trip and real timeout failures. $jobs is keyed
     * ['key' => ['imagePaths' => [...], 'prompt' => string, 'size' => string]];
     * returns ['key' => raw PNG bytes] in the same keys. Throws if any job
     * fails (mirrors the single-request behaviour — a partial collage isn't a
     * useful result).
     */
    private function requestGeneratedImagesParallel(array $jobs): array
    {
        $mh      = curl_multi_init();
        $handles = [];
        foreach ($jobs as $key => $job) {
            $ch = $this->buildImageEditHandle($job['imagePaths'], $job['prompt'], $job['size']);
            curl_multi_add_handle($mh, $ch);
            $handles[$key] = $ch;
        }

        $running = null;
        do {
            $status = curl_multi_exec($mh, $running);
            if ($running) {
                curl_multi_select($mh, 1.0);
            }
        } while ($running > 0 && $status === CURLM_OK);

        $results = [];
        $errors  = [];
        foreach ($handles as $key => $ch) {
            $raw      = curl_multi_getcontent($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlErr  = curl_error($ch);
            curl_multi_remove_handle($mh, $ch);
            curl_close($ch);

            try {
                $results[$key] = $this->decodeImageResponse($this->parseImageEditResponse($raw, $httpCode, $curlErr));
            } catch (\Throwable $e) {
                $errors[$key] = $e->getMessage();
            }
        }
        curl_multi_close($mh);

        if (!empty($errors)) {
            throw new \RuntimeException('Collage generation failed for: ' . implode('; ', array_map(
                fn($k, $m) => "{$k} ({$m})",
                array_keys($errors),
                $errors
            )));
        }

        return $results;
    }

    private function decodeImageResponse(array $response): string
    {
        $b64 = $response['data'][0]['b64_json'] ?? null;
        if (!is_string($b64) || $b64 === '') {
            throw new \RuntimeException('OpenAI did not return an image.');
        }
        $bytes = base64_decode($b64, true);
        if ($bytes === false) {
            throw new \RuntimeException('OpenAI returned an invalid image payload.');
        }
        return $bytes;
    }

    // ── Private HTTP helpers ───────────────────────────────────────────────────

    /**
     * $imagePaths is one or more source images. A single image is sent under
     * the plain `image` field (unchanged, proven behaviour); more than one is
     * sent as repeated `image[]` fields, which requires hand-building the
     * multipart body since PHP's CURLOPT_POSTFIELDS array can't hold two
     * entries under the same key. Returns an un-executed curl handle so
     * callers can either curl_exec() it directly or hand it to curl_multi for
     * concurrent execution.
     */
    private function buildImageEditHandle(array $imagePaths, string $prompt, string $size = '1024x1024')
    {
        $fieldName = count($imagePaths) > 1 ? 'image[]' : 'image';
        $parts = [
            ['name' => 'model',   'contents' => self::IMAGE_MODEL],
            ['name' => 'prompt',  'contents' => $prompt],
            ['name' => 'size',    'contents' => $size],
            ['name' => 'quality', 'contents' => 'high'],
            ['name' => 'n',       'contents' => '1'],
        ];
        foreach ($imagePaths as $path) {
            $parts[] = [
                'name'     => $fieldName,
                'file'     => $path,
                'filename' => basename($path),
                'mime'     => mime_content_type($path) ?: 'image/jpeg',
            ];
        }

        $boundary = 'ig' . bin2hex(random_bytes(16));
        $body     = $this->buildMultipartBody($parts, $boundary);

        $ch = curl_init(self::BASE_URL . '/images/edits');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            // High-quality gpt-image-1 generation at portrait size can take
            // well over a minute — give it real headroom instead of racing
            // the outer PHP/frontend timeouts.
            CURLOPT_TIMEOUT        => 160,
            CURLOPT_HTTPHEADER     => [
                'Authorization: Bearer ' . $this->apiKey,
                'Content-Type: multipart/form-data; boundary=' . $boundary,
            ],
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
        ]);

        return $ch;
    }

    private function parseImageEditResponse($raw, int $httpCode, string $curlErr): array
    {
        if ($curlErr) {
            throw new \RuntimeException("OpenAI image request failed: {$curlErr}");
        }
        $data = json_decode((string) $raw, true) ?? [];
        if ($httpCode >= 400) {
            $msg = $data['error']['message'] ?? "OpenAI image error HTTP {$httpCode}";
            writeLog('ERROR', 'OpenAI images/edits request failed', [
                'http_code' => $httpCode,
                'response'  => $data ?: substr((string) $raw, 0, 500),
            ]);
            throw new \RuntimeException($msg);
        }
        return $data;
    }

    /**
     * Hand-rolled multipart/form-data body builder — needed because array
     * fields (`image[]` repeated for multi-image edits) can't be expressed
     * as a plain PHP associative array passed to CURLOPT_POSTFIELDS, which
     * requires unique keys.
     */
    private function buildMultipartBody(array $parts, string $boundary): string
    {
        $body = '';
        foreach ($parts as $part) {
            $body .= "--{$boundary}\r\n";
            if (isset($part['file'])) {
                $body .= "Content-Disposition: form-data; name=\"{$part['name']}\"; filename=\"{$part['filename']}\"\r\n";
                $body .= "Content-Type: {$part['mime']}\r\n\r\n";
                $body .= (string) file_get_contents($part['file']);
            } else {
                $body .= "Content-Disposition: form-data; name=\"{$part['name']}\"\r\n\r\n";
                $body .= (string) $part['contents'];
            }
            $body .= "\r\n";
        }
        $body .= "--{$boundary}--\r\n";
        return $body;
    }

    private function chatCompletion(array $messages, int $maxTokens = 300): array
    {
        return $this->jsonRequest('POST', '/chat/completions', [
            'model'       => self::CHAT_MODEL,
            'messages'    => $messages,
            'max_tokens'  => $maxTokens,
            'temperature' => 0.2,
        ]);
    }

    private function jsonRequest(string $method, string $path, array $body): array
    {
        $url = self::BASE_URL . $path;
        $ch  = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_POSTFIELDS     => json_encode($body),
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $this->apiKey,
            ],
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_SSL_VERIFYHOST => false,
        ]);

        $raw      = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr  = curl_error($ch);
        curl_close($ch);

        if ($curlErr) {
            throw new \RuntimeException("OpenAI request failed: {$curlErr}");
        }
        $data = json_decode((string) $raw, true) ?? [];
        if ($httpCode >= 400) {
            $msg = $data['error']['message'] ?? "OpenAI error HTTP {$httpCode}";
            throw new \RuntimeException($msg);
        }
        return $data;
    }
}
