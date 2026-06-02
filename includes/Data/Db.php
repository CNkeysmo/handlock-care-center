<?php
namespace HLCC\Data;

if (!defined('ABSPATH')) exit;

final class Db {
    public static function table(string $name): string {
        global $wpdb;
        return $wpdb->prefix . 'hlcc_' . $name;
    }
}
