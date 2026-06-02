<?php
namespace HLCC\Domain;

if (!defined('ABSPATH')) exit;

final class Phase {
    const INFLAMMATION = 'inflammation';
    const SCAB = 'scab';
    const RECOVERY = 'recovery';

    public static function label(string $phase): string {
        switch ($phase) {
            case self::INFLAMMATION: return '炎症期';
            case self::SCAB: return '结痂/掉痂期';
            case self::RECOVERY: return '康复期';
            default: return '未知阶段';
        }
    }
}
