<?php

declare(strict_types=1);

namespace VTON;

final class ImageUtils
{
    public static function ensureJpeg(string $sourcePath): string
    {
        $type = @exif_imagetype($sourcePath);
        if ($type === IMAGETYPE_JPEG || $type === false) {
            return $sourcePath;
        }

        if (!function_exists('imagecreatefromstring')) {
            return $sourcePath;
        }

        $data = @file_get_contents($sourcePath);
        if ($data === false) {
            return $sourcePath;
        }

        $img = @imagecreatefromstring($data);
        if ($img === false) {
            return $sourcePath;
        }

        $tempPath = tempnam(sys_get_temp_dir(), 'vton_');
        if ($tempPath === false) {
            imagedestroy($img);
            return $sourcePath;
        }

        $jpegPath = $tempPath . '.jpg';
        @imagejpeg($img, $jpegPath, 95);
        imagedestroy($img);

        return is_file($jpegPath) ? $jpegPath : $sourcePath;
    }

    /**
     * Upscale $path so both dimensions are at least $minW x $minH.
     * Aspect ratio is preserved; both sides are scaled up by the same factor.
     * Returns the original path unchanged when already large enough or when GD
     * is unavailable.  Any new temp file is appended to $tempFiles.
     */
    public static function ensureMinimumSize(
        string $path,
        int    $minW,
        int    $minH,
        array  &$tempFiles
    ): string {
        if (!function_exists('imagecreatefromstring') ||
            !function_exists('imagecopyresampled')) {
            return $path;
        }

        $data = @file_get_contents($path);
        if ($data === false) {
            return $path;
        }

        $src = @imagecreatefromstring($data);
        if ($src === false) {
            return $path;
        }

        $w = imagesx($src);
        $h = imagesy($src);

        if ($w >= $minW && $h >= $minH) {
            imagedestroy($src);
            return $path;
        }

        // Scale factor: make the smaller side reach its minimum
        $scale = max($minW / max($w, 1), $minH / max($h, 1));
        $newW  = (int) ceil($w * $scale);
        $newH  = (int) ceil($h * $scale);

        $dst = imagecreatetruecolor($newW, $newH);
        if ($dst === false) {
            imagedestroy($src);
            return $path;
        }

        imagecopyresampled($dst, $src, 0, 0, 0, 0, $newW, $newH, $w, $h);
        imagedestroy($src);

        $base = tempnam(sys_get_temp_dir(), 'vton_');
        if ($base === false) {
            imagedestroy($dst);
            return $path;
        }
        $newPath   = $base . '.jpg';
        $tempFiles[] = $base;
        $tempFiles[] = $newPath;

        if (!@imagejpeg($dst, $newPath, 95)) {
            imagedestroy($dst);
            return $path;
        }

        imagedestroy($dst);
        return is_file($newPath) ? $newPath : $path;
    }

    public static function saveImageBytes(string $bytes, string $outputPath): bool
    {
        $dir = dirname($outputPath);
        if (!is_dir($dir)) {
            @mkdir($dir, 0775, true);
        }

        return file_put_contents($outputPath, $bytes) !== false;
    }

    public static function detectMimeType(string $filePath): string
    {
        $finfo = new \finfo(FILEINFO_MIME_TYPE);
        $mime = $finfo->file($filePath);

        return $mime ?: 'image/jpeg';
    }

    /**
     * Read a local image file and return it as a base64 data URI.
     * Required by APIs (e.g. Fashn.ai) that accept data URIs instead of file uploads.
     */
    public static function fileToBase64Uri(string $filePath): string
    {
        $bytes = (string)file_get_contents($filePath);
        $mime  = self::detectMimeType($filePath);
        return 'data:' . $mime . ';base64,' . base64_encode($bytes);
    }
}
