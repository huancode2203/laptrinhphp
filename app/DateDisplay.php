<?php

namespace App;

use Carbon\CarbonImmutable;
use DateTimeInterface;

class DateDisplay
{
    public static function text(string $text): string
    {
        $text = preg_replace('/\b(\d{4})-(\d{2})-(\d{2})\b/', '$3-$2-$1', $text);

        return preg_replace('~\b(\d{2})/(\d{2})/(\d{4})\b~', '$1-$2-$3', $text);
    }

    public static function value(mixed $value): mixed
    {
        if ($value instanceof DateTimeInterface) {
            return CarbonImmutable::instance($value)->timezone(config('app.timezone'))->format('d-m-Y H:i:s');
        }
        if (! is_string($value)) {
            return $value;
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return CarbonImmutable::parse($value)->format('d-m-Y');
        }
        if (preg_match('/^\d{4}-\d{2}-\d{2}[T ]\d{2}:\d{2}(?::\d{2}(?:\.\d+)?)?(?:Z|[+-]\d{2}:\d{2})?$/', $value)) {
            return CarbonImmutable::parse($value, config('app.timezone'))->timezone(config('app.timezone'))->format('d-m-Y H:i:s');
        }

        return $value;
    }
}
