<?php
require_once __DIR__ . '/ssrf_helper.php';

if (!defined('WALLOS_LOGO_MAX_BYTES')) {
    define('WALLOS_LOGO_MAX_BYTES', 2 * 1024 * 1024);
}
if (!defined('WALLOS_LOGO_MAX_DIMENSION')) {
    define('WALLOS_LOGO_MAX_DIMENSION', 4096);
}
if (!defined('WALLOS_LOGO_MAX_PIXELS')) {
    define('WALLOS_LOGO_MAX_PIXELS', 12000000);
}

if (!function_exists('wallosResolveRedirectUrl')) {
    function wallosResolveRedirectUrl(string $baseUrl, string $location): ?string
    {
        $location = trim($location);
        if ($location === '') {
            return null;
        }

        if (preg_match('/^https?:\/\//i', $location)) {
            return $location;
        }

        $base = parse_url($baseUrl);
        if (!$base || empty($base['scheme']) || empty($base['host'])) {
            return null;
        }

        $prefix = $base['scheme'] . '://' . $base['host'] . (isset($base['port']) ? ':' . $base['port'] : '');
        if ($location[0] === '/') {
            return $prefix . $location;
        }

        $path = $base['path'] ?? '/';
        $dir = rtrim(str_replace('\\', '/', dirname($path)), '/');
        return $prefix . ($dir === '' ? '/' : $dir . '/') . $location;
    }
}

if (!function_exists('wallosFetchLogoFromUrl')) {
    function wallosFetchLogoFromUrl(string $url, SQLite3 $db, array $options = []): array
    {
        $maxBytes = (int) ($options['max_bytes'] ?? WALLOS_LOGO_MAX_BYTES);
        $maxRedirects = (int) ($options['max_redirects'] ?? 3);
        $allowedContentTypes = $options['allowed_content_types'] ?? [
            'image/png',
            'image/jpeg',
            'image/gif',
            'image/webp',
        ];

        $currentUrl = $url;
        for ($i = 0; $i <= $maxRedirects; $i++) {
            if (!filter_var($currentUrl, FILTER_VALIDATE_URL) || !preg_match('/^https?:\/\//i', $currentUrl)) {
                return ['success' => false, 'message' => 'Invalid URL format.'];
            }

            $ssrf = is_url_safe_for_ssrf($currentUrl, $db);
            if ($ssrf === false) {
                return ['success' => false, 'message' => 'Invalid IP Address.'];
            }

            $imageData = '';
            $contentType = '';
            $location = '';
            $curlError = '';
            $rejected = '';
            $headerStatus = 0;

            $ch = curl_init($currentUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 8);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
            curl_setopt($ch, CURLOPT_RESOLVE, ["{$ssrf['host']}:{$ssrf['port']}:{$ssrf['ip']}"]);
            curl_setopt($ch, CURLOPT_HEADERFUNCTION, function ($ch, $header) use (&$contentType, &$location, &$rejected, &$headerStatus, $allowedContentTypes, $maxBytes) {
                $length = strlen($header);
                if (preg_match('/^HTTP\/\S+\s+(\d+)/i', trim($header), $match) === 1) {
                    $headerStatus = (int) $match[1];
                    $contentType = '';
                    $location = '';
                    return $length;
                }

                $parts = explode(':', $header, 2);
                if (count($parts) !== 2) {
                    return $length;
                }

                $name = strtolower(trim($parts[0]));
                $value = trim($parts[1]);

                if ($name === 'content-type') {
                    $contentType = strtolower(trim(explode(';', $value)[0]));
                    $isRedirect = $headerStatus >= 300 && $headerStatus < 400;
                    if (!$isRedirect && !in_array($contentType, $allowedContentTypes, true)) {
                        $rejected = 'Unsupported image content type.';
                        return 0;
                    }
                } elseif ($name === 'content-length' && (int) $value > $maxBytes) {
                    $rejected = 'Logo file is too large (max 2 MB).';
                    return 0;
                } elseif ($name === 'location') {
                    $location = $value;
                }

                return $length;
            });
            curl_setopt($ch, CURLOPT_WRITEFUNCTION, function ($ch, $chunk) use (&$imageData, &$rejected, $maxBytes) {
                if (strlen($imageData) + strlen($chunk) > $maxBytes) {
                    $rejected = 'Logo file is too large (max 2 MB).';
                    return 0;
                }

                $imageData .= $chunk;
                return strlen($chunk);
            });

            curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            if (curl_errno($ch)) {
                $curlError = curl_error($ch);
            }
            curl_close($ch);

            if ($rejected !== '') {
                return ['success' => false, 'message' => $rejected];
            }

            if ($httpCode >= 300 && $httpCode < 400) {
                $redirectUrl = wallosResolveRedirectUrl($currentUrl, $location);
                if ($redirectUrl === null) {
                    return ['success' => false, 'message' => 'Invalid redirect URL.'];
                }
                $currentUrl = $redirectUrl;
                continue;
            }

            if ($httpCode !== 200 || $imageData === '') {
                return ['success' => false, 'message' => $curlError ?: 'Failed to fetch image.'];
            }

            $imageInfo = @getimagesizefromstring($imageData);
            if ($imageInfo === false) {
                return ['success' => false, 'message' => 'Logo must be a valid image (PNG, JPG, GIF, or WebP).'];
            }

            $detectedType = strtolower($imageInfo['mime'] ?? '');
            if (!in_array($detectedType, $allowedContentTypes, true)) {
                return ['success' => false, 'message' => 'Unsupported logo format.'];
            }

            if ($contentType !== '' && $contentType !== $detectedType) {
                return ['success' => false, 'message' => 'Image content type does not match the file data.'];
            }

            $width = (int) $imageInfo[0];
            $height = (int) $imageInfo[1];
            if (
                $width <= 0 ||
                $height <= 0 ||
                $width > WALLOS_LOGO_MAX_DIMENSION ||
                $height > WALLOS_LOGO_MAX_DIMENSION ||
                ($width * $height) > WALLOS_LOGO_MAX_PIXELS
            ) {
                return ['success' => false, 'message' => 'Logo dimensions are too large.'];
            }

            return ['success' => true, 'data' => $imageData];
        }

        return ['success' => false, 'message' => 'Too many redirects.'];
    }
}
