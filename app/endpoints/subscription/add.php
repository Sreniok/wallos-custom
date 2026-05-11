<?php
error_reporting(E_ERROR | E_PARSE);
require_once '../../includes/connect_endpoint.php';
require_once '../../includes/validate_endpoint.php';
require_once '../../includes/inputvalidation.php';
require_once '../../includes/getsettings.php';
require_once '../../includes/logo_fetcher.php';
require_once '../../includes/request_helpers.php';
require_once '../../includes/subscription_form.php';

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
    $allowedExtensions = ['png', 'jpg', 'jpeg', 'gif', 'webp'];
    return in_array($fileExtension, $allowedExtensions);
}

function getLogoFromUrl($url, $uploadDir, $name, $settings, $i18n, SQLite3 $db)
{
    $fetchResult = wallosFetchLogoFromUrl($url, $db);
    if (!$fetchResult['success']) {
        return ['success' => false, 'message' => translate('error_fetching_image', $i18n) . ': ' . $fetchResult['message']];
    }

    $timestamp = time();
    $fileName = $timestamp . '-' . sanitizeFilename($name) . '.png';
    $uploadFile = $uploadDir . $fileName;

    if (!saveLogo($fetchResult['data'], $uploadFile, $name, $settings)) {
        return ['success' => false, 'message' => translate('error_fetching_image', $i18n)];
    }

    return ['success' => true, 'filename' => $fileName];
}

function saveLogo($imageData, $uploadFile, $name, $settings)
{
    $image = imagecreatefromstring($imageData);
    $removeBackground = isset($settings['removeBackground']) && $settings['removeBackground'] === 'true';

    if ($image !== false) {
        $tempFile = tempnam(sys_get_temp_dir(), 'logo');

        imagealphablending($image, false);
        imagesavealpha($image, true);
        imagepng($image, $tempFile);
        imagedestroy($image);

        if (extension_loaded('imagick')) {
            $imagick = new Imagick($tempFile);

            if ($removeBackground) {
                $imagick->setImageAlphaChannel(Imagick::ALPHACHANNEL_ACTIVATE);

                $pixel = $imagick->getImagePixelColor(0, 0);
                $color = $pixel->getColor();
                if ($color['a'] > 0) {
                    $bgColor = "rgb({$color['r']},{$color['g']},{$color['b']})";
                    $fuzz = Imagick::getQuantum() * 0.1;
                    $imagick->transparentPaintImage($bgColor, 0, $fuzz, false);
                }
            }

            $imagick->setImageFormat('png');
            $imagick->writeImage($uploadFile);
            $imagick->clear();
            $imagick->destroy();

        } else {
            $newImage = imagecreatefrompng($tempFile);
            if ($newImage !== false) {
                imagealphablending($newImage, false);
                imagesavealpha($newImage, true);

                if ($removeBackground) {
                    $transparent = imagecolorallocatealpha($newImage, 0, 0, 0, 127);
                    imagefill($newImage, 0, 0, $transparent);
                }

                imagepng($newImage, $uploadFile);
                imagedestroy($newImage);
            } else {
                unlink($tempFile);
                return false;
            }
        }

        unlink($tempFile);
        return true;
    }

    return false;
}

function resizeAndUploadLogo($uploadedFile, $uploadDir, $name, $settings)
{
    $targetWidth = 135;
    $targetHeight = 42;

    $timestamp = time();
    $originalFileName = $uploadedFile['name'];
    $fileExtension = pathinfo($originalFileName, PATHINFO_EXTENSION);
    $fileExtension = validateFileExtension($fileExtension) ? $fileExtension : 'png';
    $fileName = $timestamp . '-' . sanitizeFilename($name) . '.' . $fileExtension;
    $uploadFile = $uploadDir . $fileName;

    if (move_uploaded_file($uploadedFile['tmp_name'], $uploadFile)) {
        $fileInfo = getimagesize($uploadFile);

        if ($fileInfo !== false) {
            $width = $fileInfo[0];
            $height = $fileInfo[1];

            if ($fileExtension === 'png') {
                $image = imagecreatefrompng($uploadFile);
            } elseif ($fileExtension === 'jpg' || $fileExtension === 'jpeg') {
                $image = imagecreatefromjpeg($uploadFile);
            } elseif ($fileExtension === 'gif') {
                $image = imagecreatefromgif($uploadFile);
            } elseif ($fileExtension === 'webp') {
                $image = imagecreatefromwebp($uploadFile);
            } else {
                return "";
            }

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

$subscription = normalizeSubscriptionForm($_POST);
$logo = "";
$logoError = "";

$validationError = validateSubscriptionForm($subscription, $i18n);
if ($validationError !== null) {
    apiError($validationError, 400, ['status' => 'Error']);
}

if ($subscription['logo_url'] !== "") {
    $result = getLogoFromUrl($subscription['logo_url'], '../../images/uploads/logos/', $subscription['name'], $settings, $i18n, $db);
    if ($result['success']) {
        $logo = $result['filename'];
    } else {
        $logoError = $result['message'];
    }
} else {
    if (!empty($_FILES['logo']['name'])) {
        // Hardened logo validation: enforce a 2 MB cap and verify the file is
        // actually an image via getimagesize() rather than relying on
        // mime_content_type() which is spoofable via crafted magic bytes.
        $maxLogoBytes = WALLOS_LOGO_MAX_BYTES;
        if (($_FILES['logo']['size'] ?? 0) > $maxLogoBytes) {
            apiError("Logo file is too large (max 2 MB).", 400, ['status' => 'Error']);
        }
        $imageInfo = @getimagesize($_FILES['logo']['tmp_name']);
        if ($imageInfo === false) {
            apiError("Logo must be a valid image (PNG, JPG, GIF, or WebP).", 400, ['status' => 'Error']);
        }
        $allowedMime = ['image/png', 'image/jpeg', 'image/gif', 'image/webp'];
        if (!in_array($imageInfo['mime'] ?? '', $allowedMime, true)) {
            apiError("Unsupported logo format.", 400, ['status' => 'Error']);
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
            apiError("Logo dimensions are too large.", 400, ['status' => 'Error']);
        }
        $logo = resizeAndUploadLogo($_FILES['logo'], '../../images/uploads/logos/', $subscription['name'], $settings);
    }
}

if (!$subscription['is_edit']) {
    $sql = "INSERT INTO subscriptions (
                        name, logo, price, regular_price, currency_id, next_payment, last_payment_date, cycle, frequency, notes, 
                        payment_method_id, payer_user_id, category_id, notify, inactive, url, 
                        notify_days_before, user_id, cancellation_date, replacement_subscription_id,
                        auto_renew, start_date, ended_at, completion_notified, adjust_to_working_day
                    ) VALUES (
                        :name, :logo, :price, :regularPrice, :currencyId, :nextPayment, :lastPaymentDate, :cycle, :frequency, :notes,
                        :paymentMethodId, :payerUserId, :categoryId, :notify, :inactive, :url,
                        :notifyDaysBefore, :userId, :cancellationDate, :replacement_subscription_id,
                        :autoRenew, :startDate, NULL, 0, :adjustToWorkingDay
                    )";
} else {
    $sql = "UPDATE subscriptions SET 
                        name = :name, 
                        price = :price, 
                        regular_price = :regularPrice,
                        currency_id = :currencyId,
                        next_payment = :nextPayment, 
                        last_payment_date = :lastPaymentDate,
                        auto_renew = :autoRenew,
                        start_date = :startDate,
                        cycle = :cycle, 
                        frequency = :frequency, 
                        notes = :notes, 
                        payment_method_id = :paymentMethodId,
                        payer_user_id = :payerUserId, 
                        category_id = :categoryId, 
                        notify = :notify, 
                        inactive = :inactive, 
                        url = :url, 
                        notify_days_before = :notifyDaysBefore, 
                        cancellation_date = :cancellationDate, 
                        replacement_subscription_id = :replacement_subscription_id,
                        ended_at = NULL,
                        completion_notified = 0,
                        adjust_to_working_day = :adjustToWorkingDay";

    if ($logo != "") {
        $sql .= ", logo = :logo";
    }

    $sql .= " WHERE id = :id AND user_id = :userId";
}

$stmt = $db->prepare($sql);
bindSubscriptionForm($stmt, $subscription, (int) $userId, $logo);

if ($stmt->execute()) {
    $text = $subscription['is_edit'] ? "updated" : "added";
    $success = ['status' => "Success"];
    if ($logoError !== "") {
        $success['logo_warning'] = $logoError;
    }
    apiSuccess($success, translate('subscription_' . $text . '_successfuly', $i18n));
} else {
    apiError(translate('error', $i18n) . ": " . $db->lastErrorMsg(), 500, ['status' => 'Error']);
}
$db->close();
?>
