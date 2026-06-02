<?php
namespace HLCC\Data\Repositories;

use HLCC\Data\Db;

if (!defined('ABSPATH'))
    exit;

final class SettingsRepository
{
    private string $table;

    public function __construct()
    {
        $this->table = Db::table('settings');
    }

    /**
     * Get a setting value
     */
    public function get(string $key, $default = null)
    {
        // Use WP Options API for reliability
        return get_option('hlcc_' . $key, $default);
    }

    /**
     * Update a setting value
     */
    public function update(string $key, string $value): bool
    {
        // Use WP Options API
        return update_option('hlcc_' . $key, $value);
    }

    /**
     * Get sensitive words as array
     */
    public function get_sensitive_words(): array
    {
        $raw = $this->get('sensitive_words', '');
        if (empty($raw)) {
            return [];
        }

        // Split by comma, newline, or whitespace (allow mixed separators)
        $words = preg_split('/[,，\s\r\n]+/', $raw, -1, PREG_SPLIT_NO_EMPTY);

        // Trim each word
        $words = array_map('trim', $words);
        // Remove empty strings after trim
        $words = array_filter($words);

        return array_unique($words);
    }
}
