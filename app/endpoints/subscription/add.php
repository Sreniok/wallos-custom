<?php
error_reporting(E_ERROR | E_PARSE);
require_once '../../includes/connect_endpoint.php';
require_once '../../includes/validate_endpoint.php';
require_once '../../includes/inputvalidation.php';
require_once '../../includes/getsettings.php';

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

function getLogoFromUrl($url, $uploadDir, $name, $settings, $i18n)
{
    $maxRedirects = 3;
    $currentUrl = $url;

    for ($i = 0; $i <= $maxRedirects; $i++) {
        if (!filter_var($currentUrl, FILTER_VALIDATE_URL) || !preg_match('/^https?:\/\//i', $currentUrl)) {
            return ['success' => false, 'message' => 'Invalid URL format.'];
        }

        $parts = parse_url($currentUrl);
        $host = $parts['host'];
        $port = $parts['port'] ?? ($parts['scheme'] === 'https' ? 443 : 80);
        $ip = gethostbyname($host);

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return ['success' => false, 'message' => 'Invalid IP Address.'];
        }

        $ch = curl_init($currentUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_TIMEOUT, 5);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
        curl_setopt($ch, CURLOPT_RESOLVE, ["$host:$port:$ip"]);

        $imageData = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        if ($httpCode >= 300 && $httpCode < 400) {
            $redirectUrl = curl_getinfo($ch, CURLINFO_REDIRECT_URL);
            unset($ch);

            if (!$redirectUrl) {
                break;
            }

            $currentUrl = $redirectUrl;
            continue;
        }

        if ($imageData !== false && $httpCode === 200) {
            $timestamp = time();
            $fileName = $timestamp . '-' . sanitizeFilename($name) . '.png';
            $uploadFile = '../../images/uploads/logos/' . $fileName;

            if (saveLogo($imageData, $uploadFile, $name, $settings)) {
                unset($ch);
                return ['success' => true, 'filename' => $fileName];
            }
        }

        $error = curl_error($ch);
        unset($ch);
        return ['success' => false, 'message' => translate('error_fetching_image', $i18n) . ': ' . $error];
    }

    return ['success' => false, 'message' => translate('error_fetching_image', $i18n)];
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

$isEdit = isset($_POST['id']) && $_POST['id'] != "";
$name = validate($_POST["name"]);
$price = $_POST['price'];
$regularPriceInput = trim($_POST['regular_price'] ?? '');
$currencyId = $_POST["currency_id"];
$frequency = $_POST["frequency"];
$cycle = $_POST["cycle"];
$nextPayment = $_POST["next_payment"];
$autoRenew = isset($_POST['auto_renew']) ? true : false;
$adjustToWorkingDay = isset($_POST['adjust_to_working_day']) ? 1 : 0;
$startDate = trim($_POST["start_date"] ?? '');
$paymentMethodId = $_POST["payment_method_id"];
$payerUserId = $_POST["payer_user_id"];
$categoryId = $_POST['category_id'];
$notes = validate($_POST["notes"]);
$url = validate($_POST['url']);
$logoUrl = validate($_POST['logo-url']);
$logo = "";
$logoError = "";
$notify = isset($_POST['notifications']) ? true : false;
$notifyDaysBefore = $_POST['notify_days_before'];
$inactive = isset($_POST['inactive']) ? true : false;
$cancellationDate = $_POST['cancellation_date'] ?? null;
$lastPaymentDate = trim($_POST['last_payment_date'] ?? '');
$replacementSubscriptionId = $_POST['replacement_subscription_id'];

$regularPrice = $regularPriceInput !== '' ? floatval($regularPriceInput) : null;
$startDate = $startDate !== '' ? $startDate : $nextPayment;
$lastPaymentDate = $lastPaymentDate !== '' ? $lastPaymentDate : null;
$cancellationDate = $cancellationDate !== '' ? $cancellationDate : null;

if ($regularPrice !== null && $regularPrice <= 0) {
    die(json_encode([
        'status' => 'Error',
        'message' => translate('regular_price_must_be_positive', $i18n)
    ]));
}

if ($lastPaymentDate !== null && strtotime($lastPaymentDate) < strtotime($nextPayment)) {
    die(json_encode([
        'status' => 'Error',
        'message' => translate('last_payment_date_after_next_payment', $i18n)
    ]));
}

if ($replacementSubscriptionId == 0 || $inactive == 0) {
    $replacementSubscriptionId = null;
}

if ($logoUrl !== "") {
    $result = getLogoFromUrl($logoUrl, '../../images/uploads/logos/', $name, $settings, $i18n);
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
        $maxLogoBytes = 2 * 1024 * 1024;
        if (($_FILES['logo']['size'] ?? 0) > $maxLogoBytes) {
            echo json_encode(["status" => "Error", "message" => "Logo file is too large (max 2 MB)."]);
            exit();
        }
        $imageInfo = @getimagesize($_FILES['logo']['tmp_name']);
        if ($imageInfo === false) {
            echo json_encode(["status" => "Error", "message" => "Logo must be a valid image (PNG, JPG, GIF, or WebP)."]);
            exit();
        }
        $allowedMime = ['image/png', 'image/jpeg', 'image/gif', 'image/webp'];
        if (!in_array($imageInfo['mime'] ?? '', $allowedMime, true)) {
            echo json_encode(["status" => "Error", "message" => "Unsupported logo format."]);
            exit();
        }
        $logo = resizeAndUploadLogo($_FILES['logo'], '../../images/uploads/logos/', $name, $settings);
    }
}

if (!$isEdit) {
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
    $id = $_POST['id'];
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
$stmt->bindParam(':name', $name, SQLITE3_TEXT);
if ($logo != "") {
    $stmt->bindParam(':logo', $logo, SQLITE3_TEXT);
}
$stmt->bindParam(':price', $price, SQLITE3_FLOAT);
$stmt->bindValue(':regularPrice', $regularPrice, $regularPrice === null ? SQLITE3_NULL : SQLITE3_FLOAT);
$stmt->bindParam(':currencyId', $currencyId, SQLITE3_INTEGER);
$stmt->bindParam(':nextPayment', $nextPayment, SQLITE3_TEXT);
$stmt->bindValue(':lastPaymentDate', $lastPaymentDate, $lastPaymentDate === null ? SQLITE3_NULL : SQLITE3_TEXT);
$stmt->bindParam(':autoRenew', $autoRenew, SQLITE3_INTEGER);
$stmt->bindParam(':startDate', $startDate, SQLITE3_TEXT);
$stmt->bindParam(':cycle', $cycle, SQLITE3_INTEGER);
$stmt->bindParam(':frequency', $frequency, SQLITE3_INTEGER);
$stmt->bindParam(':notes', $notes, SQLITE3_TEXT);
$stmt->bindParam(':paymentMethodId', $paymentMethodId, SQLITE3_INTEGER);
$stmt->bindParam(':payerUserId', $payerUserId, SQLITE3_INTEGER);
$stmt->bindParam(':categoryId', $categoryId, SQLITE3_INTEGER);
$stmt->bindParam(':notify', $notify, SQLITE3_INTEGER);
$stmt->bindParam(':inactive', $inactive, SQLITE3_INTEGER);
$stmt->bindParam(':url', $url, SQLITE3_TEXT);
$stmt->bindParam(':notifyDaysBefore', $notifyDaysBefore, SQLITE3_INTEGER);
$stmt->bindValue(':cancellationDate', $cancellationDate, $cancellationDate === null ? SQLITE3_NULL : SQLITE3_TEXT);
if ($isEdit) {
    $stmt->bindParam(':id', $id, SQLITE3_INTEGER);
}
$stmt->bindParam(':userId', $userId, SQLITE3_INTEGER);
$stmt->bindValue(':replacement_subscription_id', $replacementSubscriptionId, $replacementSubscriptionId === null ? SQLITE3_NULL : SQLITE3_INTEGER);
$stmt->bindValue(':adjustToWorkingDay', $adjustToWorkingDay, SQLITE3_INTEGER);

if ($stmt->execute()) {
    $success['status'] = "Success";
    $text = $isEdit ? "updated" : "added";
    $success['message'] = translate('subscription_' . $text . '_successfuly', $i18n);
    if ($logoError !== "") {
        $success['logo_warning'] = $logoError;
    }
    header('Content-Type: application/json');
    echo json_encode($success);
    exit();
} else {
    echo translate('error', $i18n) . ": " . $db->lastErrorMsg();
}
$db->close();
?>
