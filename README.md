# HandLock Care Center

HandLock Care Center is a self-hosted WordPress plugin for tattoo-removal aftercare, customer course tracking, mobile login sessions, tutorial content, and treatment photo comparison.

This repository is a sanitized open-source snapshot of the plugin codebase. Production exports, customer data, and deployment-specific assets have been removed.

## Features

- customer account and course record management
- aftercare timeline and phase progression views
- wiki / FAQ content management
- tutorial and care-content editing
- mobile session token flow for app login
- treatment photo comparison and watermark selection
- plugin-scoped backup and restore tools

## Requirements

- WordPress
- PHP 7.4+
- MySQL / MariaDB

## Install

1. Copy this folder into `wp-content/plugins/handlock-care-center`.
2. Activate the plugin from the WordPress admin.
3. Create a page containing the shortcode `[hlcc_care_center]`.
4. Configure optional settings such as the Android APK URL from the plugin admin pages.

## Repository Notes

- The `data/` directory contains only safe-to-publish drafting material.
- Default download URLs are blank by design; set your own deployment values in WordPress options.
- If you use custom logos, fonts, watermarks, or treatment media, review redistribution rights before publishing them.

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).
