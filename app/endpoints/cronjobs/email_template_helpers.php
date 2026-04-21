<?php

use PHPMailer\PHPMailer\PHPMailer;

function wallosEmailEscape($value)
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function wallosEmailBrandLogoPath()
{
    return __DIR__ . '/../../images/siteicons/wallos.png';
}

function wallosEmailSubscriptionLogoPath($logo)
{
    if (!$logo) {
        return null;
    }

    $fileName = basename((string) $logo);
    $path = __DIR__ . '/../../images/uploads/logos/' . $fileName;

    return is_file($path) ? $path : null;
}

function wallosEmailEmbedBrandLogo(PHPMailer $mail)
{
    $brandLogoPath = wallosEmailBrandLogoPath();
    if (!is_file($brandLogoPath)) {
        return null;
    }

    $cid = 'wallos-brand-logo';
    $mail->addEmbeddedImage($brandLogoPath, $cid, 'wallos.png');

    return $cid;
}

function wallosEmailEmbedSubscriptionLogos(PHPMailer $mail, array &$subscriptions)
{
    foreach ($subscriptions as &$subscription) {
        if (empty($subscription['logo'])) {
            continue;
        }

        $logoPath = wallosEmailSubscriptionLogoPath($subscription['logo']);
        if ($logoPath === null) {
            continue;
        }

        $cid = 'subscription-logo-' . md5($subscription['logo']);
        $mail->addEmbeddedImage($logoPath, $cid, basename($logoPath));
        $subscription['logo_cid'] = $cid;
    }
}

function wallosBuildPlainTextEmail($intro, array $subscriptions)
{
    $lines = [$intro, ''];

    foreach ($subscriptions as $subscription) {
        $line = $subscription['name'] . ' for ' . $subscription['price'];

        if (!empty($subscription['badge'])) {
            $line .= ' (' . $subscription['badge'] . ')';
        }

        $lines[] = $line;

        if (!empty($subscription['date'])) {
            $lines[] = 'Date: ' . $subscription['date'];
        }

        if (!empty($subscription['category'])) {
            $lines[] = 'Category: ' . $subscription['category'];
        }

        if (!empty($subscription['payer'])) {
            $lines[] = 'Payer: ' . $subscription['payer'];
        }

        $lines[] = '';
    }

    return trim(implode("\n", $lines));
}

function wallosBuildHtmlEmail($title, $intro, array $subscriptions, $actionUrl = '', $actionLabel = 'Open Wallos', $brandLogoCid = null)
{
    $title = wallosEmailEscape($title);
    $intro = wallosEmailEscape($intro);

    $cards = '';
    foreach ($subscriptions as $subscription) {
        $name = wallosEmailEscape($subscription['name']);
        $price = wallosEmailEscape($subscription['price']);
        $badge = !empty($subscription['badge'])
            ? '<div style="display:inline-block;background:#e9f2ff;color:#1d4ed8;border-radius:999px;padding:6px 10px;font-size:12px;font-weight:700;">' . wallosEmailEscape($subscription['badge']) . '</div>'
            : '';
        $date = !empty($subscription['date'])
            ? '<div style="margin-top:6px;color:#475569;font-size:14px;"><strong>Date:</strong> ' . wallosEmailEscape($subscription['date']) . '</div>'
            : '';
        $category = !empty($subscription['category'])
            ? '<div style="margin-top:6px;color:#475569;font-size:14px;"><strong>Category:</strong> ' . wallosEmailEscape($subscription['category']) . '</div>'
            : '';
        $payer = !empty($subscription['payer'])
            ? '<div style="margin-top:6px;color:#475569;font-size:14px;"><strong>Payer:</strong> ' . wallosEmailEscape($subscription['payer']) . '</div>'
            : '';

        $logo = '<div style="width:52px;height:52px;border-radius:14px;background:#f8fafc;border:1px solid #e2e8f0;text-align:center;line-height:52px;font-size:20px;font-weight:700;color:#0f172a;">'
            . strtoupper(substr((string) $subscription['name'], 0, 1))
            . '</div>';

        if (!empty($subscription['logo_cid'])) {
            $logo = '<img src="cid:' . wallosEmailEscape($subscription['logo_cid']) . '" alt="' . $name . ' logo" style="width:52px;height:52px;border-radius:14px;object-fit:contain;background:#f8fafc;border:1px solid #e2e8f0;padding:8px;box-sizing:border-box;" />';
        }

        $cards .= ''
            . '<tr>'
            . '<td style="padding:0 0 14px 0;">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse:separate;border-spacing:0;background:#ffffff;border:1px solid #e2e8f0;border-radius:18px;">'
            . '<tr>'
            . '<td style="padding:18px;">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0">'
            . '<tr>'
            . '<td width="68" valign="top" style="padding-right:16px;">' . $logo . '</td>'
            . '<td valign="top">'
            . '<div style="font-size:18px;line-height:24px;font-weight:700;color:#0f172a;">' . $name . '</div>'
            . '<div style="margin-top:6px;font-size:16px;line-height:22px;color:#111827;font-weight:600;">' . $price . '</div>'
            . '<div style="margin-top:10px;">' . $badge . '</div>'
            . $date
            . $category
            . $payer
            . '</td>'
            . '</tr>'
            . '</table>'
            . '</td>'
            . '</tr>'
            . '</table>'
            . '</td>'
            . '</tr>';
    }

    $logoMarkup = '';
    if ($brandLogoCid !== null) {
        $logoMarkup = '<img src="cid:' . wallosEmailEscape($brandLogoCid) . '" alt="Wallos" style="display:block;width:140px;max-width:140px;height:auto;margin:0 auto 18px auto;" />';
    }

    $actionMarkup = '';
    if ($actionUrl !== '') {
        $actionMarkup = ''
            . '<tr>'
            . '<td style="padding-top:10px;text-align:center;">'
            . '<a href="' . wallosEmailEscape($actionUrl) . '" style="display:inline-block;background:#0f172a;color:#ffffff;text-decoration:none;padding:12px 18px;border-radius:12px;font-size:14px;font-weight:700;">'
            . wallosEmailEscape($actionLabel)
            . '</a>'
            . '</td>'
            . '</tr>';
    }

    return ''
        . '<!DOCTYPE html>'
        . '<html lang="en">'
        . '<head>'
        . '<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" />'
        . '<meta name="viewport" content="width=device-width, initial-scale=1.0" />'
        . '<title>' . $title . '</title>'
        . '</head>'
        . '<body style="margin:0;padding:24px;background:#f1f5f9;font-family:Arial,Helvetica,sans-serif;color:#0f172a;">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:680px;margin:0 auto;border-collapse:collapse;">'
        . '<tr>'
        . '<td style="padding:0;">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:linear-gradient(135deg,#0f172a 0%,#1d4ed8 100%);border-radius:28px 28px 0 0;">'
        . '<tr>'
        . '<td style="padding:28px 28px 24px 28px;text-align:center;">'
        . $logoMarkup
        . '<div style="font-size:13px;letter-spacing:0.12em;text-transform:uppercase;color:#bfdbfe;font-weight:700;">Wallos Notification</div>'
        . '<div style="margin-top:10px;font-size:28px;line-height:34px;font-weight:800;color:#ffffff;">' . $title . '</div>'
        . '<div style="margin-top:12px;font-size:16px;line-height:24px;color:#dbeafe;">' . $intro . '</div>'
        . '</td>'
        . '</tr>'
        . '</table>'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#ffffff;border:1px solid #e2e8f0;border-top:none;border-radius:0 0 28px 28px;">'
        . '<tr>'
        . '<td style="padding:24px 24px 10px 24px;">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0">'
        . $cards
        . $actionMarkup
        . '<tr>'
        . '<td style="padding-top:18px;font-size:12px;line-height:18px;color:#64748b;text-align:center;">'
        . 'You are receiving this reminder from Wallos.'
        . '</td>'
        . '</tr>'
        . '</table>'
        . '</td>'
        . '</tr>'
        . '</table>'
        . '</td>'
        . '</tr>'
        . '</table>'
        . '</body>'
        . '</html>';
}
