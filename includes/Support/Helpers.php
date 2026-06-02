<?php
namespace HLCC\Support;

if (!defined('ABSPATH'))
    exit;

final class Helpers
{

    public static function care_base_url(): string
    {
        $base = home_url('/care/');
        // Allow overriding the care center base URL.
        $base = apply_filters('hlcc_care_base_url', $base);
        return rtrim((string) $base, '/') . '/';
    }

    public static function preview_url(int $user_id): string
    {
        return add_query_arg(['hlcc_preview_user' => $user_id], self::care_base_url());
    }

    public static function esc_textarea_lines(?string $text): string
    {
        return esc_textarea($text ?? '');
    }

    public static function nl2p(string $text): string
    {
        $text = trim($text);
        if ($text === '')
            return '';
        $lines = preg_split("/\R/u", $text);
        $out = '';
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '')
                continue;
            $out .= '<p>' . esc_html($line) . '</p>';
        }
        return $out;
    }

    public static function lines_to_ul(string $text): string
    {
        $lines = preg_split("/\R/u", $text);
        $items = [];
        foreach ($lines as $l) {
            $l = trim($l);
            if ($l !== '')
                $items[] = $l;
        }
        if (!$items)
            return '';
        $html = '<ul class="hlcc-ul">';
        foreach ($items as $it) {
            $html .= '<li>' . esc_html($it) . '</li>';
        }
        $html .= '</ul>';
        return $html;
    }

    /**
     * Parsing lines into visual Action Cards.
     * Detected emojis at start are used as icons.
     */
    public static function lines_to_action_cards(string $text): string
    {
        $lines = preg_split("/\R/u", $text);
        $html = '<div class="hlcc-action-list">';

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '')
                continue;

            // 1. Support for [lucide:icon-name] syntax
            if (preg_match('/\[lucide:([a-z0-9-]+)\]\s*(.*)/u', $line, $matches)) {
                $icon_name = $matches[1];
                $content = $matches[2];
                $icon = self::get_icon($icon_name, 'hlcc-action-icon');
            }
            // 2. Simple Emoji extraction and Auto-mapping
            elseif (preg_match('/^([\x{1F300}-\x{1F9FF}\x{2600}-\x{26FF}\x{2700}-\x{27BF}]+)\s*(.*)/u', $line, $matches)) {
                $emoji = $matches[1];
                $content = $matches[2];
                $mapping = self::get_emoji_icon_map();

                if (isset($mapping[$emoji])) {
                    $icon = self::get_icon($mapping[$emoji]['icon'], 'hlcc-action-icon ' . ($mapping[$emoji]['class'] ?? ''));
                } else {
                    // Legacy fallback for unknown emojis
                    $icon = '<span class="hlcc-emoji-legacy">' . $emoji . '</span>';
                }
            } else {
                // Default: Use SVG Check Circle
                $icon = self::get_icon('check-circle', 'hlcc-text-green');
                $content = $line;
            }

            $html .= sprintf(
                '<div class="hlcc-action-item">
                    <div class="hlcc-action-icon-box">%s</div>
                    <div class="hlcc-action-text">%s</div>
                    <div class="hlcc-action-check">
                        <div class="hlcc-check-circle"></div>
                    </div>
                 </div>',
                $icon,
                wp_kses_post($content) // Allow inline html like bold
            );
        }

        $html .= '</div>';
        return $html;
    }

    /**
     * Convert HTML content (from rich editor) into Action Cards.
     * It splits content by block tags to create separate cards.
     */
    public static function html_to_action_cards(string $html): string
    {
        // 1. Replace block endings with newlines to ensure splitting
        $search = ['<br>', '<br/>', '<br />', '</p>', '</div>', '</li>', '</h1>', '</h2>', '</h3>', '</h4>'];
        $replace = ["\n", "\n", "\n", "\n", "\n", "\n", "\n", "\n", "\n", "\n"];
        $text = str_replace($search, $replace, $html);

        // 2. Aggressively strip ALL tags to remove inline styles (centering, colors)
        // We want clean Card styling, not user's random WYSIWYG formatting.
        $text = strip_tags($text);

        // 3. Decode entities (like &nbsp;)
        $text = html_entity_decode($text);

        // 4. Reuse lines parser
        return self::lines_to_action_cards($text);
    }

    /**
     * Parsing lines into Taboo Cards (Red warning style).
     * Always uses a warning/prohibited icon.
     */
    public static function lines_to_taboo_cards(string $text): string
    {
        $lines = preg_split("/\R/u", $text);
        $html = '<div class="hlcc-taboo-list">';

        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '')
                continue;

            // 1. Support for [lucide:icon-name] syntax
            if (preg_match('/\[lucide:([a-z0-9-]+)\]\s*(.*)/u', $line, $matches)) {
                $icon_name = $matches[1];
                $content = $matches[2];
                $icon = self::get_icon($icon_name, 'hlcc-taboo-icon');
            }
            // 2. Simple Emoji extraction and Auto-mapping
            elseif (preg_match('/^([\x{1F300}-\x{1F9FF}\x{2600}-\x{26FF}\x{2700}-\x{27BF}]+)\s*(.*)/u', $line, $matches)) {
                $emoji = $matches[1];
                $content = $matches[2];
                $mapping = self::get_emoji_icon_map();

                // Revert to Emoji for Taboo Items (User Request v8.3.31)
                $icon = '<span class="hlcc-taboo-emoji">' . $emoji . '</span>';
            } else {
                // Default: Use SVG Alert Circle for Taboo cards
                $icon = self::get_icon('alert-circle', 'hlcc-text-red');
                $content = $line;
            }

            $html .= sprintf(
                '<div class="hlcc-taboo-item">
                    <div class="hlcc-taboo-icon-box">%s</div>
                    <div class="hlcc-taboo-text">%s</div>
                 </div>',
                $icon,
                wp_kses_post($content)
            );
        }

        $html .= '</div>';
        return $html;
    }

    /**
     * Convert HTML content into Taboo Cards.
     */
    public static function html_to_taboo_cards(string $html): string
    {
        // 1. Replace block endings with newlines
        $search = ['<br>', '<br/>', '<br />', '</p>', '</div>', '</li>', '</h1>', '</h2>', '</h3>', '</h4>'];
        $replace = ["\n", "\n", "\n", "\n", "\n", "\n", "\n", "\n", "\n", "\n"];
        $text = str_replace($search, $replace, $html);

        // 2. Strip tags
        $text = strip_tags($text);

        // 3. Decode entities
        $text = html_entity_decode($text);

        // 4. Parse lines
        return self::lines_to_taboo_cards($text);
    }

    /**
     * Parsing inline text to support SVG replacement for emojis and Lucide tags.
     * Useful for progress bar tips, task descriptions etc.
     */
    public static function parse_emoji(string $text, string $icon_class = 'hlcc-inline-icon'): string
    {
        // 1. Basic escape
        $text = esc_html($text);

        // 2. Parse [lucide:icon-name] tags
        $text = preg_replace_callback('/\[lucide:([a-z0-9-]+)\]/i', function ($matches) use ($icon_class) {
            return self::get_icon($matches[1], $icon_class);
        }, $text);

        // 3. Parse explicit Emoji mappings
        $mapping = self::get_emoji_icon_map();
        foreach ($mapping as $emoji => $data) {
            $svg = self::get_icon($data['icon'], $icon_class . ' ' . ($data['class'] ?? ''));
            $text = str_replace($emoji, $svg, $text);
        }

        return $text;
    }
    /**
     * Render admin-authored rich text safely while preserving basic inline styles
     * (alignment/color/font-size) for front-end display.
     */
    public static function safe_html(string $html): string
    {
        $allowed = [
            'a' => ['href' => true, 'target' => true, 'rel' => true, 'class' => true, 'style' => true],
            'br' => [],
            'p' => ['class' => true, 'style' => true, 'align' => true],
            'div' => ['class' => true, 'style' => true, 'align' => true],
            'span' => ['class' => true, 'style' => true],
            'strong' => [],
            'b' => [],
            'em' => [],
            'i' => [],
            'u' => [],
            'ul' => ['class' => true, 'style' => true],
            'ol' => ['class' => true, 'style' => true],
            'li' => ['class' => true, 'style' => true],
            'h1' => ['class' => true, 'style' => true],
            'h2' => ['class' => true, 'style' => true],
            'h3' => ['class' => true, 'style' => true],
            'h4' => ['class' => true, 'style' => true],
        ];
        $out = wp_kses($html, $allowed);
        return $out;
    }
    public static function h(string $s): string
    {
        return esc_html($s);
    }

    /**
     * Track and return online stats.
     * Logic:
     * 1. Online: Users active in last 10 mins. Stored in transient 'hlcc_online_users'.
     * 2. Daily: Unique users today. Stored in transient 'hlcc_stats_daily_YYYYMMDD'.
     */
    public static function get_online_stats(): array
    {
        $user_id = get_current_user_id();
        if (!$user_id)
            return ['online' => 0, 'daily' => 0];

        $now = current_time('timestamp');

        // 1. Online Users (Last 10 mins)
        $online_key = 'hlcc_online_users';
        $online_users = get_transient($online_key);
        if (!is_array($online_users))
            $online_users = [];

        // Update current user
        $online_users[$user_id] = $now;

        // Cleanup stale users (>10 mins)
        foreach ($online_users as $uid => $time) {
            if ($now - $time > 600) {
                unset($online_users[$uid]);
            }
        }
        set_transient($online_key, $online_users, 600); // Expire whole list in 10m if no activity
        $online_count = count($online_users);

        // 2. Daily Stats
        $today_key = 'hlcc_stats_daily_' . date('Ymd', $now);
        $daily_users = get_transient($today_key);
        if (!is_array($daily_users))
            $daily_users = [];

        if (!in_array($user_id, $daily_users)) {
            $daily_users[] = $user_id;
            set_transient($today_key, $daily_users, 24 * HOUR_IN_SECONDS);
        }
        $daily_count = count($daily_users);

        // Base counts (fake data for internal feel)
        // If count < 5, show random 5-15 to look active
        // If daily < 50, show random 50-100
        $online_base = ($online_count < 3) ? ($online_count + 3 + (int) date('h')) : $online_count;
        $daily_base = ($daily_count < 10) ? ($daily_count + 42 + (int) date('H') * 2) : $daily_count;

        return [
            'online' => $online_base,
            'daily' => $daily_base,
        ];
    }

    /**
     * Internal mapping for Emojis to Lucide Icons
     */
    private static function get_emoji_icon_map(): array
    {
        return [
            '💊' => ['icon' => 'pill', 'class' => 'hlcc-text-blue'],
            '🧼' => ['icon' => 'droplets', 'class' => 'hlcc-text-blue'], // Soap/Liquid
            '💧' => ['icon' => 'droplet', 'class' => 'hlcc-text-blue'],
            '♨️' => ['icon' => 'thermometer-sun', 'class' => 'hlcc-text-rose'],
            '🤌' => ['icon' => 'hand', 'class' => 'hlcc-text-red'], // Avoid touch
            '🐑' => ['icon' => 'contact', 'class' => 'hlcc-text-red'], // Avoid wool/contact
            '🪿' => ['icon' => 'ban', 'class' => 'hlcc-text-red'], // Duck/Goose
            '🦆' => ['icon' => 'ban', 'class' => 'hlcc-text-red'],
            '🌶' => ['icon' => 'flame', 'class' => 'hlcc-text-red'], // Spicy
            '🍺' => ['icon' => 'beer', 'class' => 'hlcc-text-amber'], // No alcohol
            '🦞' => ['icon' => 'shell', 'class' => 'hlcc-text-red'], // Seafood
            '✊' => ['icon' => 'fist', 'class' => 'hlcc-text-red'], // Violent exercise
            '🚫' => ['icon' => 'ban', 'class' => 'hlcc-text-red'],
            '💡' => ['icon' => 'lightbulb', 'class' => 'hlcc-text-amber'],
            '🥵' => ['icon' => 'flame-kindling', 'class' => 'hlcc-text-orange'], // Sun/Heat
        ];
    }

    /**
     * Get Inline SVG Icon (Lucide/Heroicons style).
     * @param string $name Icon name (e.g. 'lightbulb', 'check')
     * @param string $class Additional CSS classes
     * @return string SVG HTML
     */
    public static function get_icon(string $name, string $class = ''): string
    {
        $svg = '';
        switch ($name) {
            case 'lightbulb':
                $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="hlcc-icon-svg ' . esc_attr($class) . '"><path d="M15 14c.2-1 .7-1.7 1.5-2.5 1-1 1.5-2 1.5-3.5 0-2.2-1.8-4-4-4s-4 1.8-4 4c0 1.5.5 2.5 1.5 3.5.8.8 1.3 1.5 1.5 2.5"/><path d="M9 18h6"/><path d="M10 22h4"/></svg>';
                break;
            case 'arrow-right':
                $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="hlcc-icon-svg ' . esc_attr($class) . '"><path d="M5 12h14"/><path d="m12 5 7 7-7 7"/></svg>';
                break;
            case 'check-circle':
                $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="hlcc-icon-svg ' . esc_attr($class) . '"><path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><path d="m9 11 3 3L22 4"/></svg>';
                break;
            case 'alert-circle':
                $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="hlcc-icon-svg ' . esc_attr($class) . '"><circle cx="12" cy="12" r="10"/><line x1="12" x2="12" y1="8" y2="12"/><line x1="12" x2="12.01" y1="16" y2="16"/></svg>';
                break;
            case 'activity': // Inflammation
                $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="hlcc-icon-svg ' . esc_attr($class) . '"><path d="M22 12h-4l-3 9L9 3l-3 9H2"/></svg>';
                break;
            case 'layers': // Scab
                $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="hlcc-icon-svg ' . esc_attr($class) . '"><path d="m12.83 2.18a2 2 0 0 0-1.66 0L2.6 6.08a1 1 0 0 0 0 1.83l8.58 3.91a2 2 0 0 0 1.66 0l8.58-3.9a1 1 0 0 0 0-1.83Z"/><path d="m22 17.65-9.17 4.16a2 2 0 0 1-1.66 0L2 17.65"/><path d="m22 12.65-9.17 4.16a2 2 0 0 1-1.66 0L2 12.65"/></svg>';
                break;
            case 'sprout': // Recovery
                $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="hlcc-icon-svg ' . esc_attr($class) . '"><path d="M7 20h10"/><path d="M10 20c5.5-2.5.8-6.4 3-10"/><path d="M9.5 9.4c1.1.8 1.8 2.2 2.3 3.6"/></svg>';
                break;
            case 'timer': // Active
                $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="hlcc-icon-svg ' . esc_attr($class) . '"><line x1="10" x2="14" y1="2" y2="2"/><line x1="12" x2="15" y1="14" y2="11"/><circle cx="12" cy="14" r="8"/></svg>';
                break;
            case 'trophy': // Completed
                $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="hlcc-icon-svg ' . esc_attr($class) . '"><path d="M6 9H4.5a2.5 2.5 0 0 1 0-5H6"/><path d="M18 9h1.5a2.5 2.5 0 0 0 0-5H18"/><path d="M4 22h16"/><path d="M10 14.66V17c0 .55-.47.98-.97 1.21C7.85 18.75 7 20.24 7 22"/><path d="M14 14.66V17c0 .55.47.98.97 1.21C16.15 18.75 17 20.24 17 22"/><path d="M18 2H6v7a6 6 0 0 0 12 0V2Z"/></svg>';
                break;
            case 'circle': // Pending
                $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="hlcc-icon-svg ' . esc_attr($class) . '"><circle cx="12" cy="12" r="10"/></svg>';
                break;
            case 'edit':
                $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="hlcc-icon-svg ' . esc_attr($class) . '"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>';
                break;
            case 'trash-2':
                $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="hlcc-icon-svg ' . esc_attr($class) . '"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>';
                break;
            case 'settings':
                $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="hlcc-icon-svg ' . esc_attr($class) . '"><path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.1a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/><circle cx="12" cy="12" r="3"/></svg>';
                break;
            case 'calendar':
                $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="hlcc-icon-svg ' . esc_attr($class) . '"><rect width="18" height="18" x="3" y="4" rx="2" ry="2"/><line x1="16" x2="16" y1="2" y2="6"/><line x1="8" x2="8" y1="2" y2="6"/><line x1="3" x2="21" y1="10" y2="10"/></svg>';
                break;
            case 'check':
                $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="hlcc-icon-svg ' . esc_attr($class) . '"><polyline points="20 6 9 17 4 12"/></svg>';
                break;
            case 'pill':
                $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="hlcc-icon-svg ' . esc_attr($class) . '"><path d="m10.5 20.5 10-10a4.95 4.95 0 1 0-7-7l-10 10a4.95 4.95 0 1 0 7 7Z"/><path d="m8.5 8.5 7 7"/></svg>';
                break;
            case 'droplet':
                $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="hlcc-icon-svg ' . esc_attr($class) . '"><path d="M12 22a7 7 0 0 0 7-7c0-2-1-3.9-3-5.5s-3.5-4-4-6.5c-.5 2.5-2 4.9-4 6.5C6 11.1 5 13 5 15a7 7 0 0 0 7 7Z"/></svg>';
                break;
            case 'droplets':
                $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="hlcc-icon-svg ' . esc_attr($class) . '"><path d="M7 16.3c2.2 0 4-1.8 4-4 0-2.2-1.9-4.4-4-7C4.9 8 3 10.1 3 12.3c0 2.2 1.8 4 4 4Z"/><path d="M17 22c2.8 0 5-2.2 5-5 0-2.8-2.4-5.5-5-8.8-2.6 3.3-5 6-5 8.8 0 2.8 2.2 5 5 5Z"/></svg>';
                break;
            case 'thermometer-sun':
                $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="hlcc-icon-svg ' . esc_attr($class) . '"><path d="M12 9a4 4 0 0 0-2 7.5"/><path d="M12 3v2"/><path d="m6.6 6.6 1.4 1.4"/><path d="M20 12h-2"/><path d="m14.5 14.5 3.5 3.5"/><path d="M21 21a2 2 0 1 1-4 0c0-1.5 1.5-2.5 2-4 .5 1.5 2 2.5 2 4Z"/><path d="M12 11a1 1 0 0 0 0 2h3a1 1 0 0 0 0-2h-3Z"/><path d="M9 13a1 1 0 0 1 1 1v6.2A2.8 2.8 0 1 1 4.3 18.2V14a1 1 0 0 1 1-1h3Z"/><path d="m16 8 1.4-1.4"/><path d="M14.5 3.5 13 5"/><path d="m19.4 6.6-1.4 1.4"/></svg>';
                break;
            case 'hand':
                $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="hlcc-icon-svg ' . esc_attr($class) . '"><path d="M18 11V6a2 2 0 0 0-2-2v0a2 2 0 0 0-2 2v0"/><path d="M14 10V4a2 2 0 0 0-2-2v0a2 2 0 0 0-2 2v10"/><path d="M10 10.5V6a2 2 0 0 0-2-2v0a2 2 0 0 0-2 2v8"/><path d="M18 8a2 2 0 1 1 4 0v6a8 8 0 0 1-8 8h-2c-2.8 0-4.5-.86-5.99-2.34l-3.6-3.6a2 2 0 0 1 2.83-2.82L7 15"/></svg>';
                break;
            case 'contact':
                $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="hlcc-icon-svg ' . esc_attr($class) . '"><path d="M17 18a2 2 0 0 0-2-2H9a2 2 0 0 0-2 2"/><rect width="18" height="18" x="3" y="4" rx="2"/><circle cx="12" cy="10" r="3"/><line x1="8" x2="8" y1="2" y2="4"/><line x1="16" x2="16" y1="2" y2="4"/></svg>';
                break;
            case 'flame':
                $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="hlcc-icon-svg ' . esc_attr($class) . '"><path d="M8.5 14.5A2.5 2.5 0 0 0 11 12c0-1.38-.5-2-1-3-1.072-2.143-.224-4.054 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 1 1-14 0c0-1.153.433-2.294 1-3a2.5 2.5 0 0 0 2.5 2.5Z"/></svg>';
                break;
            case 'flame-kindling':
                $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="hlcc-icon-svg ' . esc_attr($class) . '"><path d="M12 2c1 2 2 3.5 2 5.5 0 2.1-1.1 3.5-2 4.5-1-1-2-2.4-2-4.5 0-2 1-3.5 2-5.5Z"/><path d="m5 22 14-4"/><path d="m5 18 14 4"/></svg>';
                break;
            case 'beer':
                $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="hlcc-icon-svg ' . esc_attr($class) . '"><path d="M17 11h1a3 3 0 0 1 0 6h-1"/><path d="M5 21h12"/><path d="M6 18V4a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v14"/><line x1="6" x2="18" y1="8" y2="8"/><line x1="6" x2="18" y1="12" y2="12"/></svg>';
                break;
            case 'shell':
                $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="hlcc-icon-svg ' . esc_attr($class) . '"><path d="M12 2a10 10 0 0 1 10 10c0 2.21-1.79 4-4 4H6c-2.21 0-4-1.79-4-4A10 10 0 0 1 12 2Z"/><path d="M12 16v5"/><path d="M8 12h8"/><path d="M12 12V6"/></svg>';
                break;
            case 'fist':
                $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="hlcc-icon-svg ' . esc_attr($class) . '"><path d="M18 10V8a2 2 0 0 0-2-2h-3"/><path d="M13 6V4a2 2 0 0 0-2-2H8.5"/><path d="M7 18V8"/><path d="M10 18V6"/><path d="M13 18V6"/><path d="M16 18v-8"/><path d="M22 14v-2a2 2 0 0 0-2-2h-2"/><path d="M10 22H8a4 4 0 0 1-4-4V7"/><path d="M10 22h8a4 4 0 0 0 4-4v-4"/></svg>';
                break;
            case 'ban':
                $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="hlcc-icon-svg ' . esc_attr($class) . '"><circle cx="12" cy="12" r="10"/><path d="m4.9 4.9 14.2 14.2"/></svg>';
                break;
            case 'plus':
                $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="hlcc-icon-svg ' . esc_attr($class) . '"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>';
                break;
            case 'book-open':
                $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="hlcc-icon-svg ' . esc_attr($class) . '"><path d="M2 3h6a4 4 0 0 1 4 4v14a3 3 0 0 0-3-3H2z"/><path d="M22 3h-6a4 4 0 0 0-4 4v14a3 3 0 0 1 3-3h7z"/></svg>';
                break;
            case 'refresh-cw':
                $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="hlcc-icon-svg ' . esc_attr($class) . '"><path d="M3 12a9 9 0 0 1 9-9 9.75 9.75 0 0 1 6.74 2.74L21 8"/><path d="M21 3v5h-5"/><path d="M21 12a9 9 0 0 1-9 9 9.75 9.75 0 0 1-6.74-2.74L3 16"/><path d="M8 16H3v5"/></svg>';
                break;
            case 'image':
                $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="hlcc-icon-svg ' . esc_attr($class) . '"><rect width="18" height="18" x="3" y="3" rx="2" ry="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.086-3.086a2 2 0 0 0-2.828 0L6 21"/></svg>';
                break;
            case 'message-circle':
                $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="hlcc-icon-svg ' . esc_attr($class) . '"><path d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z"/></svg>';
                break;
            case 'heart':
                $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="hlcc-icon-svg ' . esc_attr($class) . '"><path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.3 1.5 4.05 3 5.5l7 7Z"/></svg>';
                break;
            case 'gift':
                $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="hlcc-icon-svg ' . esc_attr($class) . '"><polyline points="20 12 20 22 4 22 4 12"/><rect width="20" height="5" x="2" y="7"/><line x1="12" x2="12" y1="22" y2="7"/><path d="M12 7H7.5a2.5 2.5 0 0 1 0-5C11 2 12 7 12 7z"/><path d="M12 7h4.5a2.5 2.5 0 0 0 0-5C13 2 12 7 12 7z"/></svg>';
                break;
            case 'brush':
                $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="hlcc-icon-svg ' . esc_attr($class) . '"><path d="m9.06 11.9 8.07-8.06a2.85 2.85 0 1 1 4.03 4.03l-8.06 8.08"/><path d="M7.07 14.94c-3.91 3.91-4.63 9.06-4.03 9.06s5.15-.12 9.06-4.03"/><path d="m18 9 2 2"/></svg>';
                break;
            case 'more-horizontal':
                $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="hlcc-icon-svg ' . esc_attr($class) . '"><circle cx="12" cy="12" r="1"/><circle cx="19" cy="12" r="1"/><circle cx="5" cy="12" r="1"/></svg>';
                break;
            case 'log-out':
                $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="hlcc-icon-svg ' . esc_attr($class) . '"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" x2="9" y1="12" y2="12"/></svg>';
                break;
            case 'x':
                $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="hlcc-icon-svg ' . esc_attr($class) . '"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>';
                break;
            case 'star':
                $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="hlcc-icon-svg ' . esc_attr($class) . '"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>';
                break;
            case 'trash':
                $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="hlcc-icon-svg ' . esc_attr($class) . '"><polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/></svg>';
                break;
        }
        return $svg;
    }

    public static function disable_wp_emojis(): void
    {
        remove_action('wp_head', 'print_emoji_detection_script', 7);
        remove_action('admin_print_scripts', 'print_emoji_detection_script');
        remove_action('wp_print_styles', 'print_emoji_styles');
        remove_action('admin_print_styles', 'print_emoji_styles');
        remove_filter('the_content_feed', 'wp_staticize_emoji');
        remove_filter('comment_text_rss', 'wp_staticize_emoji');
        remove_filter('wp_mail', 'wp_staticize_emoji_for_email');
        add_filter('tiny_mce_plugins', function ($plugins) {
            if (is_array($plugins)) {
                return array_diff($plugins, ['wpemoji']);
            }
            return [];
        });
        add_filter('wp_resource_hints', function ($urls, $relation_type) {
            if ($relation_type === 'dns-prefetch') {
                $emoji_svg_url = 'https://s.w.org/images/core/emoji/';
                foreach ($urls as $key => $url) {
                    if (strpos($url, $emoji_svg_url) !== false) {
                        unset($urls[$key]);
                    }
                }
            }
            return $urls;
        }, 10, 2);
    }

    /**
     * Generate a fun anonymous name based on User ID
     */
    public static function get_fun_name(int $seed): string
    {
        // v8.6.0: Purely based on seed (usually message id) to ensure randomness and consistency
        if ($seed <= 0) {
            $seed = 999;
        }

        // Adjectives
        $adjectives = [
            '强壮的',
            '健康的',
            '神秘的',
            '快乐的',
            '勇敢的',
            '聪明的',
            '温柔的',
            '冷静的',
            '幸运的',
            '活泼的',
            '专注的',
            '无畏的',
            '自由的',
            '闪耀的',
            '温暖的',
            '治愈的',
            '机智的',
            '幽默的',
            '可爱的',
            '敏捷的',
            '优雅的',
            '正直的',
            '热情的',
            '坚定的',
            '神奇的',
            '梦幻的',
            '灵动的',
            '安静的',
            '深邃的',
            '明亮的'
        ];

        // Nouns
        $nouns = [
            '香蕉',
            '苹果',
            '山脉',
            '狮子',
            '老虎',
            '熊猫',
            '考拉',
            '海豚',
            '星星',
            '月亮',
            '太阳',
            '极光',
            '森林',
            '大海',
            '飞鸟',
            '松树',
            '白云',
            '清风',
            '细雨',
            '彩虹',
            '琴弦',
            '画笔',
            '书籍',
            '灯塔',
            '河流',
            '原野',
            '高山',
            '清泉',
            '花朵',
            '树叶',
            '雪花',
            '火焰'
        ];

        // Use seed to pick
        srand($seed);
        $adj = $adjectives[rand(0, count($adjectives) - 1)];
        $noun = $nouns[rand(0, count($nouns) - 1)];
        srand(); // Reset

        return $adj . $noun;
    }
}
