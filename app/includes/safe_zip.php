<?php
/**
 * Path-traversal-safe zip extraction.
 *
 * ZipArchive::extractTo() naively concatenates the entry name with the
 * destination directory, so a malicious zip with entries like
 * "../../etc/passwd" or "/var/www/html/shell.php" can write files
 * outside the target directory — potential RCE.
 *
 * This helper iterates each entry, rejects unsafe names, and copies
 * one entry at a time using stream_copy_to_stream so the destination
 * path is always inside the target dir (verified via realpath).
 */

if (!function_exists('wallosSafeZipExtract')) {
    /**
     * @param string $zipPath        Path to the source .zip
     * @param string $destination    Target directory (must already exist)
     * @return array{ok:bool,error?:string}  Result; on success ok=true.
     */
    function wallosSafeZipExtract(string $zipPath, string $destination): array
    {
        if (!is_dir($destination)) {
            if (!mkdir($destination, 0755, true) && !is_dir($destination)) {
                return ['ok' => false, 'error' => 'Cannot create destination directory'];
            }
        }
        $destReal = realpath($destination);
        if ($destReal === false) {
            return ['ok' => false, 'error' => 'Destination not resolvable'];
        }
        $destReal = rtrim($destReal, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;

        $zip = new ZipArchive();
        if ($zip->open($zipPath) !== true) {
            return ['ok' => false, 'error' => 'Cannot open zip'];
        }

        try {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $entryName = $zip->getNameIndex($i);
                if ($entryName === false || $entryName === '') {
                    continue;
                }

                // Reject absolute paths and any traversal segment
                if (strpos($entryName, "\0") !== false) {
                    return ['ok' => false, 'error' => 'Null byte in entry name'];
                }
                if ($entryName[0] === '/' || $entryName[0] === '\\') {
                    return ['ok' => false, 'error' => 'Absolute path in zip: ' . $entryName];
                }
                if (preg_match('#(^|/)\\.\\.(/|$)#', $entryName) === 1) {
                    return ['ok' => false, 'error' => 'Path traversal in zip: ' . $entryName];
                }
                // Reject Windows-style drive letters
                if (preg_match('/^[A-Za-z]:/', $entryName) === 1) {
                    return ['ok' => false, 'error' => 'Drive letter in zip: ' . $entryName];
                }

                // Build the target path and ensure (post-resolution) it sits
                // under the destination root.
                $targetPath = $destReal . $entryName;
                $isDir = substr($entryName, -1) === '/';

                if ($isDir) {
                    if (!is_dir($targetPath) && !mkdir($targetPath, 0755, true) && !is_dir($targetPath)) {
                        return ['ok' => false, 'error' => 'Cannot create directory: ' . $entryName];
                    }
                    continue;
                }

                $parentDir = dirname($targetPath);
                if (!is_dir($parentDir) && !mkdir($parentDir, 0755, true) && !is_dir($parentDir)) {
                    return ['ok' => false, 'error' => 'Cannot create parent directory for: ' . $entryName];
                }

                // Final containment check using realpath of the parent (file
                // doesn't exist yet so we can't realpath the file itself).
                $parentReal = realpath($parentDir);
                if ($parentReal === false || strpos($parentReal . DIRECTORY_SEPARATOR, $destReal) !== 0) {
                    return ['ok' => false, 'error' => 'Entry escapes destination: ' . $entryName];
                }

                $stream = $zip->getStream($entryName);
                if (!$stream) {
                    return ['ok' => false, 'error' => 'Cannot read entry: ' . $entryName];
                }
                $out = fopen($targetPath, 'wb');
                if (!$out) {
                    fclose($stream);
                    return ['ok' => false, 'error' => 'Cannot write entry: ' . $entryName];
                }
                stream_copy_to_stream($stream, $out);
                fclose($stream);
                fclose($out);
            }
        } finally {
            $zip->close();
        }

        return ['ok' => true];
    }
}
