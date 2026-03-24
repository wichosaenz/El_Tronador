# El Tronador - WordPress Cache & Performance Optimization

**El Tronador** is a premium-grade WordPress cache and performance optimization plugin built to compete with the best. The name is a Mexican play on words referring to a rocket ("cohete") that "truena" (blasts off at maximum power).

## Features (Phase 1 - MVP)

### Static Page Cache
- Disk-based full-page caching via WordPress `advanced-cache.php` drop-in
- Automatic cache serving before WordPress loads for blazing-fast response times
- 10-hour TTL to ensure Contact Form 7 nonces remain valid
- Smart exclusions: logged-in users, POST requests, REST API, AJAX, WP-CLI
- WPS Hide Login compatibility: custom login URLs are never cached

### Delay JavaScript
- Delays non-critical JavaScript execution until first user interaction (scroll, mouse movement, touch, keydown, click)
- Reduces main-thread blocking for improved Core Web Vitals (LCP, FID, INP)
- Contact Form 7 scripts are automatically excluded to preserve form functionality
- Google Site Kit / Analytics / Tag Manager scripts are properly delayed

### Ecosystem Compatibility
- **Breeze Plugin**: Detects conflicts and warns the administrator before activation
- **Object Cache Pro / Redis**: Flushes object cache when static cache is purged
- **WPS Hide Login**: Reads custom login slug and excludes it from caching
- **Contact Form 7**: 10-hour cache TTL prevents nonce expiration issues

## Requirements

- WordPress 6.0 or higher (tested up to 6.9.4)
- PHP 8.0 or higher
- Write permissions on `wp-content/` directory (for `advanced-cache.php` drop-in and cache storage)

## Installation

### Manual Installation
1. Download the latest release `.zip` file
2. Go to **WordPress Admin > Plugins > Add New > Upload Plugin**
3. Upload the `.zip` file and click **Install Now**
4. Activate the plugin

### From Source
1. Clone this repository into your `wp-content/plugins/` directory:
   ```bash
   git clone https://github.com/wichosaenz/El_Tronador.git wp-content/plugins/el-tronador
   ```
2. Activate the plugin from the WordPress admin panel

## Configuration

After activation, navigate to **Settings > El Tronador** in your WordPress admin panel.

- **Page Cache**: Toggle static page caching on/off
- **Delay JS**: Toggle JavaScript delay on/off
- **Purge Cache**: Clear all cached pages with one click (also available in the admin bar)

## Architecture

El Tronador is built with a **Module Registry** pattern designed for scalability:

- **OOP / PHP 8.0+**: Strict typing, interfaces, and clean class hierarchy
- **PSR-4-like Autoloader**: Classes are loaded on demand
- **Modular Design**: Each feature is an independent module implementing a common interface
- **Future-Ready**: The architecture supports adding new optimization engines without modifying the core

## Roadmap

- [ ] **Phase 1** - Page Cache + Delay JS *(current)*
- [ ] **Phase 2** - File Optimization (CSS/JS minification, Critical CSS)
- [ ] **Phase 3** - Media Optimization (Smart Lazy Load excluding LCP)
- [ ] **Phase 4** - Database Optimization (transients, revisions, expired options)
- [ ] **Phase 5** - Preload Engine (sitemap crawler bot)

## License

GPLv2 or later. See [LICENSE](https://www.gnu.org/licenses/gpl-2.0.html) for details.
