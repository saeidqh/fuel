<?php
namespace app\modules\fuel\models;

use app\components\jDateTime;

final class ConsumptionInput
{
    public static function normalize($value)
    {
        if (!is_string($value)) {
            return $value;
        }

        return trim(strtr($value, [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            '٫' => '.',
        ]));
    }

    public static function decimal($value, $precision = 2)
    {
        if (!is_string($value) && !is_int($value) && !is_float($value)) {
            return null;
        }

        $value = self::normalize((string) $value);
        $pattern = '/\A\d+(?:\.\d{1,' . (int) $precision . '})?\z/';

        return preg_match($pattern, $value) ? $value : null;
    }

    public static function toGregorian($value)
    {
        $value = self::normalize($value);

        if (!is_string($value)
            || !preg_match('/\A(1\d{3})\/(\d{1,2})\/(\d{1,2})\z/', $value, $parts)) {
            return null;
        }

        $year = (int) $parts[1];
        $month = (int) $parts[2];
        $day = (int) $parts[3];

        if ($month < 1 || $month > 12 || $day < 1 || $day > 31) {
            return null;
        }

        $calendar = new jDateTime(true, true, 'Asia/Tehran');
        $gregorian = $calendar->toGregorian($year, $month, $day);

        if (!checkdate((int) $gregorian[1], (int) $gregorian[2], (int) $gregorian[0])) {
            return null;
        }

        $jalali = $calendar->toJalali(
            (int) $gregorian[0],
            (int) $gregorian[1],
            (int) $gregorian[2]
        );

        if ((int) $jalali[0] !== $year
            || (int) $jalali[1] !== $month
            || (int) $jalali[2] !== $day) {
            return null;
        }

        return sprintf('%04d-%02d-%02d', $gregorian[0], $gregorian[1], $gregorian[2]);
    }

    public static function toJalali($value)
    {
        if (!is_string($value)) {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value);

        if (!$date || $date->format('Y-m-d') !== $value) {
            return null;
        }

        $calendar = new jDateTime(true, true, 'Asia/Tehran');
        $parts = $calendar->toJalali(
            (int) $date->format('Y'),
            (int) $date->format('m'),
            (int) $date->format('d')
        );

        return sprintf('%04d/%02d/%02d', $parts[0], $parts[1], $parts[2]);
    }
}