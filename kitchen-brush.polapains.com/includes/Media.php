<?php

declare(strict_types=1);

final class Media
{
    private const UPLOAD_DIR = 'assets/images/uploads';
    private const IMAGE_ROOT = 'assets/images';
    private const MAX_BYTES = 4194304;

    private const ALLOWED = [
        'jpg' => ['image/jpeg'],
        'jpeg' => ['image/jpeg'],
        'png' => ['image/png'],
        'webp' => ['image/webp'],
        'gif' => ['image/gif'],
    ];

    public static function items(): array
    {
        $root = BASE_PATH . '/' . self::IMAGE_ROOT;
        if (!is_dir($root)) {
            return [];
        }

        $items = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($root, FilesystemIterator::SKIP_DOTS)
        );

        foreach ($iterator as $file) {
            if (!$file instanceof SplFileInfo || !$file->isFile()) {
                continue;
            }

            $extension = strtolower($file->getExtension());
            if (!array_key_exists($extension, self::ALLOWED)) {
                continue;
            }

            $relative = str_replace('\\', '/', substr($file->getPathname(), strlen(BASE_PATH) + 1));
            $items[] = [
                'name' => $file->getFilename(),
                'path' => $relative,
                'url' => image_src($relative),
                'size' => $file->getSize(),
                'modified' => $file->getMTime(),
                'deletable' => true,
            ];
        }

        usort($items, static fn (array $a, array $b): int => $b['modified'] <=> $a['modified']);
        return $items;
    }

    public static function upload(array $file): string
    {
        self::ensureUploadDirectory();

        if (($file['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) {
            throw new RuntimeException('Image upload failed. Please choose a valid image file.');
        }

        $size = (int)($file['size'] ?? 0);
        if ($size <= 0 || $size > self::MAX_BYTES) {
            throw new RuntimeException('Image size must be 4 MB or smaller.');
        }

        $originalName = (string)($file['name'] ?? 'image');
        $extension = strtolower(pathinfo($originalName, PATHINFO_EXTENSION));
        if (!array_key_exists($extension, self::ALLOWED)) {
            throw new RuntimeException('Only JPG, PNG, WebP, and GIF images are allowed.');
        }

        $tmpName = (string)($file['tmp_name'] ?? '');
        $mime = self::detectMime($tmpName);
        if (!in_array($mime, self::ALLOWED[$extension], true)) {
            throw new RuntimeException('The uploaded file does not match the expected image type.');
        }

        $baseName = strtolower(pathinfo($originalName, PATHINFO_FILENAME));
        $baseName = preg_replace('/[^a-z0-9]+/', '-', $baseName) ?: 'image';
        $baseName = trim($baseName, '-');
        $fileName = $baseName . '-' . date('YmdHis') . '-' . bin2hex(random_bytes(3)) . '.' . $extension;
        $destination = BASE_PATH . '/' . self::UPLOAD_DIR . '/' . $fileName;

        if (!move_uploaded_file($tmpName, $destination)) {
            throw new RuntimeException('Could not save uploaded image.');
        }

        chmod($destination, 0644);
        return self::UPLOAD_DIR . '/' . $fileName;
    }

    public static function delete(string $relativePath): void
    {
        $relativePath = str_replace('\\', '/', trim($relativePath));
        $extension = strtolower(pathinfo($relativePath, PATHINFO_EXTENSION));
        if (!str_starts_with($relativePath, self::IMAGE_ROOT . '/') || !array_key_exists($extension, self::ALLOWED)) {
            throw new RuntimeException('Only media library image files can be deleted from admin.');
        }

        $imageRoot = realpath(BASE_PATH . '/' . self::IMAGE_ROOT);
        $target = realpath(BASE_PATH . '/' . $relativePath);

        $imageRootWithSeparator = $imageRoot === false ? '' : rtrim($imageRoot, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
        if ($imageRoot === false || $target === false || !str_starts_with($target, $imageRootWithSeparator)) {
            throw new RuntimeException('Media file was not found.');
        }

        if (!is_file($target) || !unlink($target)) {
            throw new RuntimeException('Could not delete media file.');
        }
    }

    public static function maxUploadMb(): int
    {
        return (int)(self::MAX_BYTES / 1048576);
    }

    private static function ensureUploadDirectory(): void
    {
        $directory = BASE_PATH . '/' . self::UPLOAD_DIR;
        if (!is_dir($directory) && !mkdir($directory, 0755, true)) {
            throw new RuntimeException('Could not create media upload directory.');
        }
    }

    private static function detectMime(string $tmpName): string
    {
        if ($tmpName === '' || !is_file($tmpName)) {
            throw new RuntimeException('Uploaded image could not be checked.');
        }

        if (function_exists('finfo_open')) {
            $finfo = finfo_open(FILEINFO_MIME_TYPE);
            if ($finfo !== false) {
                $mime = finfo_file($finfo, $tmpName);
                finfo_close($finfo);
                if (is_string($mime) && $mime !== '') {
                    return $mime;
                }
            }
        }

        $imageInfo = getimagesize($tmpName);
        if (is_array($imageInfo) && !empty($imageInfo['mime'])) {
            return (string)$imageInfo['mime'];
        }

        throw new RuntimeException('Uploaded image could not be checked.');
    }
}
