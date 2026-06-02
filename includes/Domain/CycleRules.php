<?php
namespace HLCC\Domain;

if (!defined('ABSPATH'))
    exit;

final class CycleRules
{
    public static function normalize_project_key($key): string
    {
        $key = is_string($key) ? $key : (string) $key;
        $key = trim($key);
        if ($key === 'tattoo' || $key === 'brow' || $key === 'scar')
            return $key;
        // Backward/buggy values fallback
        return 'tattoo';
    }

    public static function is_valid_project_key($key): bool
    {
        $k = self::normalize_project_key($key);
        return in_array($k, ['tattoo', 'brow', 'scar'], true);
    }

    public static function project_label(string $key): string
    {
        $key = self::normalize_project_key($key);
        switch ($key) {
            case 'tattoo':
                return '洗纹身';
            case 'brow':
                return '洗眉毛';
            case 'scar':
                return '疤痕修复';
            default:
                return $key;
        }
    }

    public static function cycle_days(string $key, ?int $custom_days = null): int
    {
        if ($custom_days !== null && $custom_days > 0) {
            return $custom_days;
        }
        $key = self::normalize_project_key($key);
        switch ($key) {
            case 'tattoo':
                return 101;
            case 'brow':
                return 65;
            case 'scar':
                return 35;
            default:
                return 101;
        }
    }

    public static function project_options(bool $with_cycle = true): array
    {
        if ($with_cycle) {
            return [
                'tattoo' => '洗纹身（101天）',
                'brow' => '洗眉毛（65天）',
                'scar' => '疤痕修复（35天）',
            ];
        }
        return [
            'tattoo' => '洗纹身',
            'brow' => '洗眉毛',
            'scar' => '疤痕修复',
        ];
    }

    public static function project_options_plain(): array
    {
        return self::project_options(false);
    }
}
