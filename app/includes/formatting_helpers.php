<?php

require_once __DIR__ . '/currency_formatter.php';
require_once __DIR__ . '/date_formatter.php';

function formatPrice($price, $currencyCode, $currencies)
{
    $formattedPrice = CurrencyFormatter::format($price, $currencyCode);

    if (is_array($currencies) && strstr($formattedPrice, $currencyCode)) {
        $symbol = $currencyCode;

        foreach ($currencies as $currency) {
            if (($currency['code'] ?? null) === $currencyCode) {
                if (!empty($currency['symbol'])) {
                    $symbol = $currency['symbol'];
                }
                break;
            }
        }

        $formattedPrice = str_replace($currencyCode, $symbol, $formattedPrice);
    } elseif (is_string($currencies) && strpos($formattedPrice, $currencyCode) !== false) {
        $formattedPrice = str_replace($currencyCode, $currencies . ' ', $formattedPrice);
        $formattedPrice = preg_replace('/\s+/', ' ', $formattedPrice);
    }

    return $formattedPrice;
}

function formatDate($date, $lang = 'en')
{
    return wallosFormatSubscriptionDate($date, $lang);
}

function getPricePerMonth($cycle, $frequency, $price)
{
    $frequency = max(1, (int) $frequency);

    switch ((int) $cycle) {
        case 1:
            return $price * (30 / $frequency);
        case 2:
            return $price * (4.35 / $frequency);
        case 3:
            return $price * (1 / $frequency);
        case 4:
            return $price / (12 * $frequency);
        default:
            return $price;
    }
}

function getPriceConverted($price, $currency, $database, $userId = null)
{
    $query = "SELECT rate FROM currencies WHERE id = :currency";
    if ($userId !== null) {
        $query .= " AND user_id = :userId";
    }

    $stmt = $database->prepare($query);
    $stmt->bindValue(':currency', $currency, SQLITE3_INTEGER);
    if ($userId !== null) {
        $stmt->bindValue(':userId', $userId, SQLITE3_INTEGER);
    }
    $result = $stmt->execute();

    $exchangeRate = $result->fetchArray(SQLITE3_ASSOC);
    if ($exchangeRate === false || empty($exchangeRate['rate'])) {
        return $price;
    }

    return $price / $exchangeRate['rate'];
}

function wallosCycleSuffix($cycle, $frequency = 1)
{
    $frequency = max(1, (int) $frequency);

    switch ((int) $cycle) {
        case 1:
            $unit = $frequency === 1 ? 'day' : 'days';
            break;
        case 2:
            $unit = $frequency === 1 ? 'week' : 'weeks';
            break;
        case 3:
            $unit = $frequency === 1 ? 'month' : 'months';
            break;
        case 4:
            $unit = $frequency === 1 ? 'year' : 'years';
            break;
        default:
            return '';
    }

    return '/' . ($frequency === 1 ? $unit : $frequency . ' ' . $unit);
}

function wallosRelativeDayLabel($dateStr)
{
    global $i18n;

    $today = strtotime('today');
    $date = strtotime($dateStr);
    if ($date === false) {
        return '';
    }

    $days = (int) round(($date - $today) / 86400);
    if ($days < 0) {
        $abs = abs($days);
        return $abs === 1
            ? translate('relative_1_day_late', $i18n)
            : sprintf(translate('relative_n_days_late', $i18n), $abs);
    }
    if ($days === 0) {
        return translate('relative_today', $i18n);
    }
    if ($days === 1) {
        return translate('relative_tomorrow', $i18n);
    }

    return sprintf(translate('relative_in_n_days', $i18n), $days);
}

