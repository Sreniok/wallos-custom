<?php

function wallosIntlIsAvailable()
{
    return class_exists('IntlDateFormatter') && class_exists('ResourceBundle');
}

function wallosNormalizeLocale($locale, $fallback = 'en')
{
    if (!wallosIntlIsAvailable()) {
        return $fallback;
    }

    if (!in_array($locale, ResourceBundle::getLocales(''))) {
        return $fallback;
    }

    return $locale;
}

function wallosFallbackDatePattern($pattern)
{
    $replacements = [
        'MMMM' => 'F',
        'MMM' => 'M',
        'MM' => 'm',
        'dd' => 'd',
        'd' => 'j',
        'yyyy' => 'Y',
        'yy' => 'y',
    ];

    return strtr($pattern, $replacements);
}

function wallosFormatDateValue($date, $locale = 'en', $dateType = null, $timeType = null, $pattern = null)
{
    $dateType = $dateType ?? (wallosIntlIsAvailable() ? IntlDateFormatter::MEDIUM : null);
    $timeType = $timeType ?? (wallosIntlIsAvailable() ? IntlDateFormatter::NONE : null);

    try {
        $dateTime = $date instanceof DateTimeInterface ? $date : new DateTime((string) $date);
    } catch (Exception $e) {
        return (string) $date;
    }

    if (wallosIntlIsAvailable()) {
        $formatter = new IntlDateFormatter(
            wallosNormalizeLocale($locale),
            $dateType,
            $timeType,
            null,
            null,
            $pattern
        );

        $formatted = $formatter->format($dateTime);
        if ($formatted !== false) {
            return $formatted;
        }
    }

    $fallbackPattern = $pattern ?: 'M j, Y';
    return $dateTime->format(wallosFallbackDatePattern($fallbackPattern));
}

function wallosFormatSubscriptionDate($date, $locale = 'en')
{
    $currentYear = date('Y');
    $dateYear = date('Y', strtotime((string) $date));
    $datePattern = ($currentYear == $dateYear) ? 'MMM d' : 'MMM yyyy';

    return wallosFormatDateValue($date, $locale, null, null, $datePattern);
}
