# HandLock Care Center

HandLock Care Center is a self-hosted WordPress plugin for tattoo-removal aftercare and customer recovery management.

It was originally built for a clinic-operated WordPress site and combines customer course tracking, recovery guidance, mobile login sessions, wiki / FAQ content management, and treatment photo comparison in a single plugin.

This repository is a sanitized open-source release of that discontinued internal project. Production exports, customer data, deployment-specific URLs, and private operational artifacts have been removed before publication.

## Overview

Use this project if you need a WordPress-based recovery portal that can:

- manage customer accounts and treatment course records
- publish phase-based aftercare guidance on the frontend
- maintain clinic knowledge-base or FAQ content
- support app-linked login sessions and token refresh flows
- compare treatment photos during customer follow-up

## Quick Start

```bash
git clone https://github.com/keysmo1992/handlock-care-center.git
cd handlock-care-center
```

Copy the plugin into `wp-content/plugins/handlock-care-center`, activate it in WordPress, and create a page with the shortcode `[hlcc_care_center]`.

## What This Project Does

The plugin is designed to help a clinic or operator run a recovery portal around tattoo-removal workflows:

- create and manage customer accounts
- create treatment course records and active recovery timelines
- show phase-based aftercare guidance on the frontend
- manage internal wiki / FAQ content for common customer questions
- manage mobile app login sessions and token refresh flow
- store and compare treatment photos with optional watermark selection
- back up and restore plugin-owned data

## Project Status

This is an open-sourced snapshot of a discontinued internal project.

- the code is published for reference, reuse, and extension
- active product development is not guaranteed
- issues and pull requests can still be used for bug reports or cleanup proposals

## Requirements

- WordPress
- PHP 7.4+
- MySQL or MariaDB

## Install Locally

### Option 1: Manual plugin install

1. Copy this folder into `wp-content/plugins/handlock-care-center`.
2. Activate the plugin from the WordPress admin.
3. Create a page containing the shortcode `[hlcc_care_center]`.
4. Configure optional settings such as the Android APK URL from the plugin admin pages.

### Option 2: Symlink during development

```bash
ln -s /absolute/path/to/handlock-care-center /path/to/wordpress/wp-content/plugins/handlock-care-center
```

Then activate the plugin in WordPress as usual.

## Repository Structure

- `assets/`: frontend, admin, icon, and manifest assets
- `includes/`: plugin PHP source code
- `data/`: safe-to-publish drafting material only
- `scripts/`: maintenance or operator helper scripts

## Notes For Open-Source Use

- the `data/` directory intentionally excludes production SQL exports
- default download URLs are blank by design; set your own deployment values in WordPress options
- if you reuse custom logos, fonts, watermarks, avatars, or treatment media, confirm redistribution rights yourself
- this repository does not include production customer records, photos, or operational backups

## Contributing

Contributions are welcome for cleanup, bug fixes, portability improvements, and documentation updates.

Before opening a pull request:

1. keep changes scoped and easy to review
2. avoid committing private clinic data, backups, SQL dumps, or credentials
3. describe any WordPress, PHP, or database assumptions clearly

See [CONTRIBUTING.md](CONTRIBUTING.md) for a short contribution guide.

## License

This project is licensed under `GPL-2.0-or-later`.

In practice, that means:

- you may use, study, modify, and redistribute the code
- redistributed copies and derivative works must remain under a GPL-compatible release model
- the project is provided without warranty

See [LICENSE](LICENSE) for the full license text.
