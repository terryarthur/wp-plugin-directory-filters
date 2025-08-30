=== WPPD Filters ===
Contributors: terryarthur
Tags: installer, filters, admin, directory
Requires at least: 5.8
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.2.1
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Enhances WordPress admin installer with advanced filtering, sorting, and rating capabilities.

== Description ==

WPPD Filters enhances the built-in WordPress plugin installer with powerful filtering and sorting capabilities while maintaining seamless integration with the existing interface.

= Key Features =

* **Advanced Filtering Options**
  * Filter by installation count (1K+, 10K+, 100K+, 1M+)
  * Filter by last update timeframe (last month, 3 months, year)
  * Filter by usability ratings and health scores
  * Filter by user ratings (1+ to 4+ stars)

* **Visual Status Indicators**
  * Colored icons for plugin update status (green, orange, red)
  * WordPress compatibility indicators with security awareness
  * Quick visual assessment of plugin maintenance status

* **Enhanced Sorting**
  * Sort by relevance, installations, ratings, last updated
  * Sort by usability scores and health metrics
  * Maintains search context during filtering

* **Modern UI Design**
  * Clean, professional interface that integrates with WordPress admin
  * Responsive design that works on all screen sizes
  * Non-intrusive enhancement of existing WordPress functionality

= Who This Is For =

* **WordPress Administrators** who need to find high-quality plugins quickly
* **Developers** who want to assess plugin maintenance and compatibility
* **Site Owners** who prioritize security and plugin quality
* **Anyone** who installs WordPress plugins and wants better filtering tools

= How It Works =

The plugin adds a filter bar to the WordPress plugin installer page (Plugins > Add New) that appears only when you want to use advanced filtering. It never interferes with the default WordPress search functionality.

1. Go to Plugins > Add New in your WordPress admin
2. Use the enhanced filter controls that appear
3. Apply filters to see results with colored status indicators
4. Install plugins with confidence using the visual quality indicators

= Technical Features =

* Integrates with WordPress.org Plugin API
* Calculates plugin health scores based on multiple factors
* Provides real-time WordPress version compatibility checking
* Uses semantic versioning for accurate compatibility assessment
* Maintains WordPress coding standards and security best practices

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/wppd-filters` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. Go to Plugins > Add New to see the enhanced plugin installer with filtering capabilities.

== Frequently Asked Questions ==

= Does this plugin modify the default WordPress plugin installer? =

No, it enhances the existing functionality without breaking or replacing WordPress core features. You can still use the default search exactly as before.

= Will this plugin slow down my site? =

No, the plugin only loads on the admin plugin installer page and has minimal performance impact. It uses efficient API calls and caching.

= Is this plugin compatible with multisite? =

Yes, the plugin works on both single sites and multisite networks.

= Does it work with other plugin installer plugins? =

This plugin is designed to work with the default WordPress plugin installer. Compatibility with other installer plugins is not guaranteed.

= What are the colored status indicators? =

* **Green**: Recently updated (last 3 months) or current WordPress compatibility
* **Orange/Yellow**: Moderately updated (3-9 months) or recent but not current WordPress version
* **Red**: Old updates (9+ months) or potential security compatibility issues

== Screenshots ==

1. Enhanced plugin installer with filter controls and colored status indicators
2. Filter dropdown options showing installation counts, update timeframes, and ratings
3. Plugin cards with colored status indicators for quick quality assessment
4. Modern branding and professional UI integration

== Changelog ==

= 1.0.0 =
* Initial release
* Advanced filtering by installations, updates, ratings, and health scores
* Colored status indicators for update status and WordPress compatibility
* Enhanced sorting capabilities with search context preservation
* Modern UI design with professional branding
* WordPress 6.7.1 compatibility
* Security-focused compatibility checking
* Responsive design for all screen sizes

== Upgrade Notice ==

= 1.0.0 =
Initial release of WPPD Filters. Enhances your plugin installer with advanced filtering and visual quality indicators.

== External Services ==

This plugin connects to the WordPress.org Plugin Directory API to retrieve plugin information and metadata. This external service is essential for the plugin's core functionality of filtering and displaying enhanced plugin information.

**Service:** WordPress.org Plugin Directory API
**Purpose:** Retrieve plugin listings, ratings, installation counts, update information, and compatibility data
**Data Sent:** Search terms, filter parameters (installation counts, rating minimums, update timeframes)
**When Data is Sent:** When users search for plugins or apply filters on the plugin installer page (Plugins > Add New)
**Data Processing:** No personal user data is sent - only search criteria and filter preferences

**Service URLs:**
- API Endpoint: https://api.wordpress.org/plugins/info/1.2/
- Terms of Service: https://wordpress.org/about/license/
- Privacy Policy: https://wordpress.org/about/privacy/

The WordPress.org Plugin Directory API is operated by the WordPress Foundation and follows WordPress.org's terms of service and privacy policy. All API calls are made to publicly available endpoints that provide the same information visible on the WordPress.org plugin directory website.

== Privacy Policy ==

This plugin does not collect, store, or transmit any personal data. It only makes API calls to the public WordPress.org Plugin Directory API to retrieve plugin information. No user data is tracked or stored.

== Support ==

For support, feature requests, or bug reports, please visit the plugin support forum or contact the author through the plugin website.

== Credits ==

Developed by Terry Arthur with a focus on enhancing the WordPress admin experience while maintaining compatibility and security standards.
