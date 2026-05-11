<?php
error_reporting(E_ERROR | E_PARSE);
require_once '../../includes/connect_endpoint.php';
require_once '../../includes/inputvalidation.php';
require_once '../../includes/getsettings.php';
require_once '../../includes/validate_endpoint.php';
require_once '../../includes/logo_fetcher.php';

if (!file_exists('../../images/uploads/logos')) {
    mkdir('../../images/uploads/logos', 0777, true);
    mkdir('../../images/uploads/logos/avatars', 0777, true);
}

function sanitizeFilename($filename)
{
    $filename = preg_replace("/[^a-zA-Z0-9\s]/", "", $filename);
    $filename = str_replace(" ", "-", $filename);
    $filename = str_replace(".", "", $filename);
    return $filename;
}

function validateFileExtension($fileExtension)
{
    $allowedExtensions = ['png', 'jpg', 'jpeg', 'gif', 'jtif', 'webp'];
    return in_array($fileExtension, $allowedExtensions);
}

function getLogoFromUrl($url, $uploadDir, $name, $i18n, $settings, SQLite3 $db)
{
    $fetchResult = wallosFetchLogoFromUrl($url, $db);
    if (!$fetchResult['success']) {
        apiError(translate('error_fetching_image', $i18n) . ': ' . $fetchResult['message'], 400);
    }

    $timestamp = time();
    $fileName = $timestamp . '-payments-' . sanitizeFilename($name) . '.png';
    $uploadFile = $uploadDir . $fileName;

    if (!saveLogo($fetchResult['data'], $uploadFile, $name, $settings)) {
        apiError(translate('error_fetching_image', $i18n), 400);
    }

    return $fileName;
}


function saveLogo($imageData, $uploadFile, $name, $settings)
{
    $image = imagecreatefromstring($imageData);
    $removeBackground = isset($settings['removeBackground']) && $settings['removeBackground'] === 'true';
    if ($image !== false) {
        $tempFile = tempnam(sys_get_temp_dir(), 'logo');
        imagepng($image, $tempFile);
        imagedestroy($image);

        if (extension_loaded('imagick')) {
            $imagick = new Imagick($tempFile);
            if ($removeBackground) {
                $fuzz = Imagick::getQuantum() * 0.1; // 10%
                $imagick->transparentPaintImage("rgb(247, 247, 247)", 0, $fuzz, false);
            }
            $imagick->setImageFormat('png');
            $imagick->writeImage($uploadFile);

            $imagick->clear();
            $imagick->destroy();
        } else {
            // Alternative method if Imagick is not available
            $newImage = imagecreatefrompng($tempFile);
            if ($removeBackground) {
                imagealphablending($newImage, false);
                imagesavealpha($newImage, true);
                $transparent = imagecolorallocatealpha($newImage, 0, 0, 0, 127);
                imagefill($newImage, 0, 0, $transparent);  // Fill the entire image with transparency
                imagepng($newImage, $uploadFile);
                imagedestroy($newImage);
            }
            imagepng($newImage, $uploadFile);
            imagedestroy($newImage);
        }
        unlink($tempFile);

        return true;
    } else {
        return false;
    }
}

function resizeAndUploadLogo($uploadedFile, $uploadDir, $name)
{
    $targetWidth = 70;
    $targetHeight = 48;

    $timestamp = time();
    $originalFileName = $uploadedFile['name'];
    $fileExtension = pathinfo($originalFileName, PATHINFO_EXTENSION);
    $fileExtension = validateFileExtension($fileExtension) ? $fileExtension : 'png';
    $fileName = $timestamp . '-payments-' . sanitizeFilename($name) . '.' . $fileExtension;
    $uploadFile = $uploadDir . $fileName;

    if (move_uploaded_file($uploadedFile['tmp_name'], $uploadFile)) {
        $fileInfo = getimagesize($uploadFile);

        if ($fileInfo !== false) {
            $width = $fileInfo[0];
            $height = $fileInfo[1];

            // Load the image based on its format
            if ($fileExtension === 'png') {
                $image = imagecreatefrompng($uploadFile);
            } elseif ($fileExtension === 'jpg' || $fileExtension === 'jpeg') {
                $image = imagecreatefromjpeg($uploadFile);
            } elseif ($fileExtension === 'gif') {
                $image = imagecreatefromgif($uploadFile);
            } elseif ($fileExtension === 'webp') {
                $image = imagecreatefromwebp($uploadFile);
            } else {
                // Handle other image formats as needed
                return "";
            }

            // Enable alpha channel (transparency) for PNG images
            if ($fileExtension === 'png') {
                imagesavealpha($image, true);
            }

            $newWidth = $width;
            $newHeight = $height;

            if ($width > $targetWidth) {
                $newWidth = (int) $targetWidth;
                $newHeight = (int) (($targetWidth / $width) * $height);
            }

            if ($newHeight > $targetHeight) {
                $newWidth = (int) (($targetHeight / $newHeight) * $newWidth);
                $newHeight = (int) $targetHeight;
            }

            $resizedImage = imagecreatetruecolor($newWidth, $newHeight);
            imagesavealpha($resizedImage, true);
            $transparency = imagecolorallocatealpha($resizedImage, 0, 0, 0, 127);
            imagefill($resizedImage, 0, 0, $transparency);
            imagecopyresampled($resizedImage, $image, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

            if ($fileExtension === 'png') {
                imagepng($resizedImage, $uploadFile);
            } elseif ($fileExtension === 'jpg' || $fileExtension === 'jpeg') {
                imagejpeg($resizedImage, $uploadFile);
            } elseif ($fileExtension === 'gif') {
                imagegif($resizedImage, $uploadFile);
            } elseif ($fileExtension === 'webp') {
                imagewebp($resizedImage, $uploadFile);
            } else {
                return "";
            }

            imagedestroy($image);
            imagedestroy($resizedImage);
            return $fileName;
        }
    }

    return "";
}

$enabled = 1;
$name = validate($_POST["paymentname"]);
$iconUrl = validate($_POST['icon-url']);

if ($name === "" || ($iconUrl === "" && empty($_FILES['paymenticon']['name']))) {
    apiError(translate('fill_all_fields', $i18n), 400);
}


$icon = "";

if ($iconUrl !== "") {
    $icon = getLogoFromUrl($iconUrl, '../../images/uploads/logos/', $name, $i18n, $settings, $db);
} else {
    if (!empty($_FILES['paymenticon']['name'])) {
        if (($_FILES['paymenticon']['size'] ?? 0) > WALLOS_LOGO_MAX_BYTES) {
            apiError("Logo file is too large (max 2 MB).", 400);
        }
        $imageInfo = @getimagesize($_FILES['paymenticon']['tmp_name']);
        if ($imageInfo === false) {
            apiError("Logo must be a valid image (PNG, JPG, GIF, or WebP).", 400);
        }
        $allowedMime = ['image/png', 'image/jpeg', 'image/gif', 'image/webp'];
        if (!in_array($imageInfo['mime'] ?? '', $allowedMime, true)) {
            apiError("Unsupported logo format.", 400);
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
            apiError("Logo dimensions are too large.", 400);
        }
        $icon = resizeAndUploadLogo($_FILES['paymenticon'], '../../images/uploads/logos/', $name);
    }
}

// Get the maximum existing ID
$stmt = $db->prepare("SELECT MAX(id) as maxID FROM payment_methods");
$result = $stmt->execute();
$row = $result->fetchArray(SQLITE3_ASSOC);
$maxID = $row['maxID'];

// Ensure the new ID is greater than 31
$newID = max($maxID + 1, 32);

// Insert the new record with the new ID
$sql = "INSERT INTO payment_methods (id, name, icon, enabled, user_id) VALUES (:id, :name, :icon, :enabled, :userId)";
$stmt = $db->prepare($sql);

$stmt->bindParam(':id', $newID, SQLITE3_INTEGER);
$stmt->bindParam(':name', $name, SQLITE3_TEXT);
$stmt->bindParam(':icon', $icon, SQLITE3_TEXT);
$stmt->bindParam(':enabled', $enabled, SQLITE3_INTEGER);
$stmt->bindParam(':userId', $userId, SQLITE3_INTEGER);

if ($stmt->execute()) {
    apiSuccess(null, translate('payment_method_added_successfuly', $i18n));
} else {
    apiError(translate('error', $i18n) . ": " . $db->lastErrorMsg(), 500);
}

$db->close();

?>
