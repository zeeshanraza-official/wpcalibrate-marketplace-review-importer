# WPCalibrate Marketplace Review Importer

[![WordPress](https://img.shields.io/badge/WordPress-6.5%20to%207.1.3+-blue.svg)](https://wordpress.org)
[![WooCommerce](https://img.shields.io/badge/WooCommerce-8.0%20to%2011.2.1+-96588a.svg)](https://woocommerce.com)
[![PHP](https://img.shields.io/badge/PHP-8.2%20|%208.3-777bb4.svg)](https://php.net)
[![HPOS](https://img.shields.io/badge/HPOS-Compatible-success.svg)](https://woocommerce.com)
[![License: GPL v2](https://img.shields.io/badge/License-GPL%20v2-blue.svg)](https://www.gnu.org/licenses/gpl-2.0.html)
[![Release](https://img.shields.io/badge/Release-v1.0.0-green.svg)](https://github.com/zeeshanraza-official/wpcalibrate-marketplace-review-importer/releases)

> **Import legitimate Amazon customer reviews via free CSV, JSON, or automated SerpApi directly into WooCommerce native product reviews.**

---

## Table of Contents
- [About](#about)
- [Key Features](#key-features)
- [Architecture & Ethics](#architecture--ethics)
- [Requirements](#requirements)
- [Installation](#installation)
- [Documentation & User Guide](#documentation--user-guide)
  - [1. Free CSV Import](#1-free-csv-import)
  - [2. Free JSON Import](#2-free-json-import)
  - [3. Automated SerpApi Import](#3-automated-serpapi-import)
  - [4. Frontend Product Page Display](#4-frontend-product-page-display)
  - [5. Demo Reviews Dataset](#5-demo-reviews-dataset)
- [WordPress Dashboard Automatic Updates](#wordpress-dashboard-automatic-updates)
- [Admin Settings & Configuration](#admin-settings--configuration)
- [Changelog](#changelog)
- [Support & Community](#support--community)
- [License & Trademarks](#license--trademarks)

---

## About

**WPCalibrate Marketplace Review Importer** is a robust, production-quality WordPress plugin that transfers authentic customer feedback from marketplaces like Amazon directly into native WooCommerce product reviews.

Unlike risky scraper scripts that get IP-banned or violate terms of service, this plugin operates ethically:
1. **100% Free Core Engine:** Unlimited manual importing via structured CSV spreadsheets and JSON files without any third-party accounts, fees, or paid subscriptions.
2. **Authenticated External APIs:** Optional automated review syncing powered by official API services (such as SerpApi) where store administrators supply their own private API credentials.
3. **WooCommerce Native Storage:** Persists all imported reviews as standard WordPress comments (`comment_type = 'review'`), updating product rating averages, star counts, and WooCommerce transients automatically.

---

## Key Features

- **100% Free Core Functionality:** No hidden paywalls, no recurring subscriptions, and no external registration required for CSV or JSON imports.
- **Multi-Format Importing:**
  - **CSV Spreadsheets:** Automatic column detection, flexible header mapping, preview validation, and downloadable standard templates.
  - **Structured JSON:** Versioned schema (`v1.0`) with syntax validation, batch verification, and 1-click in-browser demo paste.
  - **SerpApi Integration:** Automatic pagination, ASIN/URL extraction, and scheduled synchronization.
- **Enterprise-Grade 4-Tier Deduplication:**
  - Tier 1: Provider + Marketplace + Review ID
  - Tier 2: Provider + ASIN + Review ID
  - Tier 3: Marketplace Source URL
  - Tier 4: Normalized Content Hash (SHA-256)
- **High-Performance Background Processing:**
  - Batched processing powered by WordPress Action Scheduler for large review imports without timeouts.
  - Transient import locking to eliminate duplicate concurrent executions.
- **Frontend Presentation Overhaul:**
  - Interactive **Customer Reviews Summary Card** with 5-star to 1-star percentage breakdown progress bars.
  - Responsive **Customer Review Photos Gallery** with hover zoom.
  - **"Verified Amazon Purchase"** badges with verified checkmark icons.
  - Helpful vote counters and transparent source attribution links.
  - Beautifully redesigned review submission form with interactive golden star rating selector.
- **Built-in Demo Testing Dataset:**
  - 15 pre-validated Amazon customer reviews spanning all 5 rating tiers, authentic reviewer names, dates, and image attachments.
  - Available for 1-click download or direct in-browser testing.
- **WooCommerce HPOS Ready:** Fully compatible with High-Performance Order Storage (HPOS) and Cart/Checkout Blocks.
- **Accessibility & RTL:** Complete RTL (Right-to-Left) support, semantic HTML, and localized strings ready for translation.

---

## Architecture & Ethics

### Zero-Scraping Policy
This plugin does **NOT** scrape Amazon HTML pages directly from your WordPress server, does **NOT** attempt to bypass CAPTCHAs, bot detectors, or rate limits, and does **NOT** invent undocumented APIs. Automated imports rely exclusively on legitimate, authenticated API providers.

### Zero-Spoofing Ethics
Consumer trust is paramount:
- Imported reviews never falsely flag the `verified` WooCommerce order meta as a local store order.
- Transparent provenance metadata (`_wpcmri_source_verified_purchase`, `_wpcmri_source_marketplace`, `_wpcmri_source_url`) clearly distinguishes external marketplace feedback from local buyer purchases.

---

## Requirements

| Requirement | Minimum | Tested Up To |
|-------------|---------|--------------|
| **WordPress** | 6.5 | 7.1.3+ |
| **WooCommerce** | 8.0 | 11.2.1+ |
| **PHP** | 8.2 | 8.3.x |
| **MySQL / MariaDB** | 5.7+ / 10.4+ | 8.0+ |

---

## Installation

### Method 1: WordPress Admin Upload (Recommended)
1. Download the latest `wpcalibrate-marketplace-review-importer-1.0.0.zip` from [Releases](https://github.com/zeeshanraza-official/wpcalibrate-marketplace-review-importer/releases).
2. Go to **WordPress Admin → Plugins → Add New Plugin → Upload Plugin**.
3. Choose the downloaded ZIP file and click **Install Now**.
4. Click **Activate Plugin**.

### Method 2: Git / Manual Installation
1. Clone this repository into your WordPress plugins directory:
   ```bash
   cd wp-content/plugins/
   git clone https://github.com/zeeshanraza-official/wpcalibrate-marketplace-review-importer.git
   ```
2. Navigate to **WordPress Admin → Plugins** and click **Activate** under **WPCalibrate Marketplace Review Importer**.

---

## Documentation & User Guide

### 1. Free CSV Import
1. Navigate to **WPCalibrate → Review Importer → Import Reviews → CSV File Upload**.
2. Select your target WooCommerce product.
3. Download the sample template using **Download CSV Template** to ensure your column headers match.
4. Upload your CSV spreadsheet. Supported columns:
   - `rating` (Required: integer 1–5)
   - `review_text` / `content` / `body` (Required: review description)
   - `review_title` / `headline` (Optional: review title)
   - `reviewer_name` / `author` (Optional: reviewer name)
   - `review_date` / `date` (Optional: ISO 8601 or standard date string)
   - `verified_purchase` (Optional: `true`/`false` or `1`/`0`)
   - `review_id` (Optional: source ID for deduplication)
   - `review_images` (Optional: comma-separated image URLs)
5. Review the live preview table and click **Import Reviews Now**.

### 2. Free JSON Import
1. Navigate to **WPCalibrate → Review Importer → Import Reviews → JSON Paste / Upload**.
2. Select your target WooCommerce product.
3. Upload a `.json` file or paste a JSON array directly into the editor.
4. Click **Load 15 Demo Reviews into Box** for instant testing without preparing external files.
5. Example JSON structure:
   ```json
   {
     "schema_version": "1.0",
     "source": "amazon",
     "product_id": "B08N5WRWNW",
     "reviews": [
       {
         "id": "R_AMZ_001",
         "title": "Exceptional sound quality",
         "body": "The active noise cancellation is remarkable for daily commutes.",
         "rating": 5,
         "author": "Sarah Jenkins",
         "date": "2026-09-15 14:30:00",
         "verified_purchase": true,
         "helpful_votes": 28,
         "images": ["https://example.com/photo1.jpg"]
       }
     ]
   }
   ```
6. Click **Preview & Validate JSON**, then confirm the import.

### 3. Automated SerpApi Import
1. Obtain an API key from [SerpApi](https://serpapi.com).
2. Navigate to **WPCalibrate → Review Importer → Providers → SerpApi**.
3. Enter your private API key and click **Save Provider Settings**.
4. In the **Automated Import** screen:
   - Paste an Amazon product URL (e.g., `https://www.amazon.com/dp/B08N5WRWNW`) or a 10-character Amazon ASIN (`B08N5WRWNW`).
   - Select your target WooCommerce product.
   - Choose your sync limit (e.g., 20, 50, 100 reviews).
   - Click **Start Automated Import**.

### 4. Frontend Product Page Display
The plugin automatically enhances the WooCommerce single product reviews tab:
- **Rating Summary Breakdown:** Shows average score, golden star visualization, total review count, and percentage distribution progress bars for 5, 4, 3, 2, and 1 star ratings.
- **Review Cards:** Formatted with bold headings, Amazon verified buyer badges, customer photo galleries with hover zoom, helpful vote counts, and reviewer initials avatars.
- **Review Submission Form:** Redesigned responsive card layout with interactive rating stars and polished form inputs.

### 5. Demo Reviews Dataset
The plugin includes 15 ready-to-use sample reviews:
- **Download CSV Demo:** Available from the Overview tab or direct link.
- **Download JSON Demo:** Available from the Overview tab.
- **1-Click Load:** Click the demo button on the JSON import tab to populate the editor immediately.

---

## WordPress Dashboard Automatic Updates

This repository is configured for automatic in-dashboard updates:
- **Standard WordPress Update URI:** Configured with `Update URI: https://github.com/zeeshanraza-official/wpcalibrate-marketplace-review-importer`.
- **Git Updater / GitHub Updater Compatible:** Works seamlessly with tools like [Git Updater](https://git-updater.com/) or [Plugin Update Checker](https://github.com/YahnisElsts/plugin-update-checker).
- **GitHub Releases:** Every tagged release includes a clean, installable production ZIP archive.

To enable direct in-dashboard updates on your WordPress site:
1. Install and activate [Git Updater](https://git-updater.com/) on your WordPress installation.
2. New plugin updates published on this GitHub repository will automatically appear in your **WordPress Dashboard → Updates** and **Plugins** page with one-click update capability.

---

## Admin Settings & Configuration

Navigate to **WPCalibrate → Review Importer → Settings**:
- **Default Review Status:** Choose between `Approved` (immediate publication) or `Pending Moderation` (admin approval required).
- **Minimum Rating Filter:** Restrict imports to 1-star, 2-star, 3-star, 4-star, or 5-star reviews only.
- **Deduplication Mode:** Choose between Strict 4-Tier Matching or Relaxed Duplicate Handling.
- **Marketplace Badge Display:** Enable or disable "Verified Amazon Purchase" badges on the storefront.
- **Rating Summary Breakdown:** Toggle the visual rating breakdown card above product reviews.
- **Data Retention on Uninstall:** Choose whether to keep imported reviews and settings or perform a complete cleanup upon plugin deletion.

---

## Changelog

### [1.0.5] - 2026-10-15
- **Fixed:** Resolved broken sidebar menu icon where `esc_url()` in WordPress core `menu-header.php` stripped non-SVG `data:` protocols down to empty strings (`src=""`).
- **Enhanced:** Switched menu icon to direct HTTP URL delivery (`branding/icon-menu-normal.png`), ensuring reliable loading across all browsers.
- **Enhanced:** Added auto-healing to restore the branding logo if a parent menu exists with empty or generic dashicons.
- **Enhanced:** Applied `filter: brightness(0) invert(1) !important;` on hover/active states for crisp white rendering against dark admin sidebars.
- **Tests:** Added automated test coverage in `SecurityTest::test_admin_menu_icon_url_validity()`.

### [1.0.4] - 2026-10-15
- **Enhanced:** Integrated official provided WPCalibrate logo (`branding/icon-white.png`) downsampled to anti-aliased 40x40 Retina and 20x20 standard menu icons.
- **Enhanced:** Constrained sidebar icon bounding box strictly to 20x20px with standard WordPress dashboard silver (`#a7aaad`) and active white (`#ffffff`).

### [1.0.3] - 2026-10-15
- **Feature:** Complete Frontend Marketplace Reviews presentation overhaul (`src/Frontend/ReviewDisplay.php`).
- **Feature:** Customer Reviews Summary Card with 5-to-1 star percentage breakdown progress bars.
- **Feature:** Verified Marketplace Purchase badges, review titles, customer photo galleries, and helpful votes counters.
- **Feature:** Modern redesign of WooCommerce review submission form with interactive golden star selectors.

### [1.0.2] - 2026-10-15
- **Feature:** Added pre-validated 15-review demo dataset in CSV and JSON formats (`assets/demo/`).
- **Feature:** Direct secure download endpoints and 1-click in-browser demo loading for rapid testing.

### [1.0.1] - 2026-10-15
- **Feature:** Dedicated Overview & Getting Started onboarding dashboard with quick-action links and architecture highlights.
- **Feature:** Professional UI overhaul of all admin tabs with responsive design and modern typography.

### [1.0.0] - 2026-10-15
- **Initial Release:** Production-quality free review importer for WooCommerce.
- 100% free CSV and JSON import engine with UTF-8 support and auto-column mapping.
- Pluggable provider architecture with initial SerpApi Amazon integration.
- 4-tier idempotent duplicate detection engine.
- Action Scheduler background processing for large imports.
- WordPress Comments architecture with native WooCommerce rating recalculation.

---

## Support & Community

- **Website:** [https://wpcalibrate.com](https://wpcalibrate.com)
- **Email Support:** [support@wpcalibrate.com](mailto:support@wpcalibrate.com)
- **Documentation:** [https://wpcalibrate.com/docs/marketplace-review-importer](https://wpcalibrate.com/docs/marketplace-review-importer)
- **Report Bugs:** Open an issue on this repository's [Issue Tracker](https://github.com/zeeshanraza-official/wpcalibrate-marketplace-review-importer/issues).

---

## License & Trademarks

- **Software License:** Released under the terms of the [GNU General Public License v2.0 or later (GPL-2.0-or-later)](LICENSE.txt).
- **Trademark Disclaimer:** *WordPress* and *WooCommerce* are registered trademarks of Automattic Inc. *Amazon* is a registered trademark of Amazon.com, Inc. or its affiliates. This plugin is an independent third-party software product developed by WPCalibrate and is not affiliated with, endorsed by, or sponsored by Automattic Inc., WooCommerce, Amazon.com, or SerpApi.
