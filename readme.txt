=== WPCalibrate Marketplace Review Importer ===
Contributors: wpcalibrate
Tags: woocommerce, reviews, amazon reviews, csv review import, json review import
Requires at least: 6.5
Tested up to: 7.1.3
Requires PHP: 8.2
WC requires at least: 8.0
WC tested up to: 11.2.1
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Import legitimate third-party Amazon product reviews via CSV, JSON, or SerpApi into WooCommerce native product reviews.

== Description ==

**WPCalibrate Marketplace Review Importer** allows WooCommerce store owners and shop managers to import legitimate customer reviews belonging to products they sell or are authorized to reference directly into native WooCommerce product reviews.

The plugin provides a powerful, multi-format review import pipeline designed with performance, data integrity, and strict adherence to WordPress coding standards.

### Primary Features

* **100% Free CSV Import:** Import reviews from standard spreadsheets without registering any external service. Downloadable template included.
* **100% Free JSON Import:** Import reviews using a clean, versioned schema with automated structure and syntax validation.
* **Pluggable External Review Providers:** Optional automated review importing from Amazon via SerpApi using your own private API key.
* **WooCommerce Native Storage:** Imported reviews are stored as native WordPress product comments (`comment_type = 'review'`) with proper WooCommerce star ratings (`1` to `5`) and automatic product rating calculation.
* **Smart 4-Tier Duplicate Prevention:** Imports are strictly idempotent. The multi-level fingerprint engine prevents duplicate reviews even if an import is rerun multiple times.
* **Comprehensive Pre-Import Preview:** View destination product details, total discovered rows, duplicate estimates, rating breakdowns, and sample records before committing any data to your database.
* **Granular Filter Controls:** Filter by minimum and maximum star ratings, date ranges, verified purchase at source status, and minimum review length.
* **High-Performance Background Processing:** Built-in batching with Action Scheduler support for large imports, avoiding PHP execution timeouts.
* **Detailed Audit & Import History:** Dedicated tracking screen showing past import jobs, success/skip/duplicate/failure counts, and real-time progress.
* **High-Performance Order Storage (HPOS) Ready:** Fully compatible with WooCommerce HPOS and Cart & Checkout Blocks.

### Third-Party Service Disclosure & Privacy Notice

This plugin features optional integration with **SerpApi** (serpapi.com) for fetching Amazon product reviews:

* **What Service is Used:** SerpApi (SerpApi, LLC) Amazon Reviews API (`https://serpapi.com/search.json`).
* **What Triggers a Request:** A remote request to SerpApi is triggered **only** when an administrator deliberately initiates an Amazon lookup from the import screen or tests their API connection. The plugin **never** makes background or automated requests to SerpApi without direct administrator action.
* **What Information is Sent:** The target product's Amazon ASIN (e.g. `B08N5WRWNW`), the selected Amazon marketplace domain (e.g. `amazon.com`), request pagination parameters, and the administrator's own SerpApi API key. No merchant store customer data or personal data from the WooCommerce store is ever sent to SerpApi.
* **Credentials:** The administrator must supply their own SerpApi API key. The plugin does not bundle or proxy any credentials. Keys are masked in the UI and never logged.
* **Offline Functionality:** Both CSV and JSON importing are 100% functional without enabling SerpApi or creating any external account.
* **Terms & Policies:** Use of SerpApi is governed by SerpApi's Terms of Service (https://serpapi.com/terms) and Privacy Policy (https://serpapi.com/privacy).

### Trademark Disclaimer

Amazon is a registered trademark of Amazon.com, Inc. or its affiliates. WPCalibrate and this plugin are not affiliated with, endorsed by, or sponsored by Amazon.com, Inc., SerpApi, or WooCommerce.

== Installation ==

1. Upload the `wpcalibrate-marketplace-review-importer` folder to your `/wp-content/plugins/` directory, or install the ZIP through the WordPress Plugins screen.
2. Activate the plugin through the **Plugins** menu in WordPress.
3. Ensure WooCommerce is active.
4. Navigate to **WPCalibrate &gt; Review Importer** in your WordPress admin menu.
5. Select your target WooCommerce product, choose your source (CSV, JSON, or Amazon via SerpApi), preview the data, and import.

== Frequently Asked Questions ==

= Does this plugin scrape Amazon directly from my server? =
No. The plugin never scrapes Amazon HTML directly, does not bypass CAPTCHAs, and does not use proxy rotation or bot evasion. For automated imports, it connects only to authorized external APIs like SerpApi when configured by the store owner.

= Are imported reviews marked as WooCommerce "Verified Owners"? =
No. WooCommerce verified owner status indicates a verified purchase made directly on your local store. The plugin never falsifies WooCommerce verified-owner status. If the review was marked verified at the marketplace source, it is recorded in separate namespaced metadata (`_wpcmri_source_verified_purchase`).

= Can I import negative reviews? =
Yes. The plugin does not silently filter or exclude negative reviews by default. Star rating filters are transparent and configurable by the administrator.

= Will running the same import twice create duplicate reviews? =
No. The plugin computes unique fingerprints (marketplace review IDs, source URLs, and normalized content hashes) to ensure repeated runs safely skip or update metadata without creating duplicate reviews.

= What happens if I deactivate the plugin? =
All imported reviews and WooCommerce ratings remain intact in your WordPress database. Deactivating the plugin merely pauses import administration.

== Screenshots ==

1. Import Reviews screen with product selection and source choices.
2. Review Preview dashboard with rating breakdown and duplicate checks.
3. Import History screen tracking batch jobs and metrics.
4. Pluggable Providers configuration and connection testing.
5. Plugin settings for moderation, batching, and data retention.

== Changelog ==

= 1.0.0 =
* Initial public release.
* Support for CSV review imports with downloadable sample template.
* Support for versioned JSON schema review imports.
* Pluggable provider architecture with initial SerpApi Amazon reviews integration.
* Four-tier duplicate detection system.
* Action Scheduler background batch processing.
* Full compatibility with WordPress 7.1.3 and WooCommerce 11.2.1.
