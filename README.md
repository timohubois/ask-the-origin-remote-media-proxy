# Ask the Origin – Remote Media Proxy

Show media from the origin. Without copying the uploads library.

Ask the Origin – Remote Media Proxy serves missing media on a local or staging WordPress site from a configured source site (the origin). Existing files on the destination site take precedence. Retrieved files are cached privately, without being added to uploads or the Media Library.

## Requirements

Requires WordPress 7.0+ and PHP 8.3+. Composer is not required to run the plugin. No plugin is needed on the source site.

The source must use a different hostname and matching uploads paths relative to its site base. The plugin does not translate different single-site, Multisite or CDN upload layouts. Use HTTPS and trust the source.

The private cache requires writable storage outside known web roots in WordPress's temporary directory, respecting `WP_TEMP_DIR`. Cached files are fresh for five minutes. There is no automatic cache cleanup or download-size limit; use this for images and modest-sized files rather than large video delivery.

PHP attachment readers and supported Timber resizing require `allow_url_fopen`; resizing also requires REST API access.

## Installation

1. Place the plugin in `wp-content/plugins/ask-the-origin-remote-media-proxy/` and activate it on the destination site. The proxy remains disabled.
2. Review the routing requirements below and protect the destination site before enabling the proxy. Configure manual server routing if required.
3. Under **Settings > Media**, set **Remote site URL** to the source site's base URL, enter any required HTTP Basic Auth credentials, enable the proxy and save.
4. Resolve any routing warning and check a page with missing media.

Protect the destination site separately: source authentication does not restrict its visitors. The plugin does not sanitize SVG content if SVG uploads are enabled.

### Server routing

On supported Apache layouts, the plugin manages a marked block in `WP_CONTENT_DIR/.htaccess`. This requires `mod_rewrite`, a writable content directory, and uploads within that directory and its public URL path. Review existing rules: child rewrite rules can replace inherited security restrictions. Keep restrictions on script execution in uploads. If missing files still return 404, check that `mod_rewrite` is loaded; PHP-FPM and WP-CLI cannot always detect it.

On nginx, Caddy or other layouts, configure the server to route missing upload requests to WordPress's front controller instead of returning a static 404. Preserve upload security restrictions.

Disable the proxy before moving WordPress or its content directory.

## Usage and compatibility

While the plugin is active, users with the `manage_options` capability (administrators by default) see a standalone native Media icon in the WordPress toolbar, even when the proxy is disabled. The icon keeps the native toolbar colors. Its tooltip matches the dropdown status text exactly—**Ask the Origin: Enabled** or **Ask the Origin: Disabled**—without the colored dot. Existing toolbar menus are unchanged, and a hidden toolbar is not forced to appear.

The dropdown has two items:

- **Ask the Origin: Enabled** or **Ask the Origin: Disabled**, with a green or gray status dot. Click this item to switch the current site's proxy status. Screen-reader text explains the action and identifies the site without adding visible text.
- **Settings**, linking to the Status setting under **Settings > Media**. The icon itself links there too.

Status changes use a permission-checked, site-bound, nonce-protected POST and retain the other saved settings and credentials. Without JavaScript, the status link opens Media settings instead. If `ASK_THE_ORIGIN_ENABLED` or the options filter prevents changing the effective status, the row is read-only; its screen-reader text explains that configuration code controls it. Filters that only set other fields do not lock the status toggle.

On Multisite, the status and toggle apply only to the current site, whether the plugin is site-activated or network-activated. This remains a per-site status in Network Admin, not a network-wide proxy switch. The accessible menu description identifies the site. The icon remains available at WordPress's mobile toolbar breakpoint, and flex alignment centers the icon, dropdown dot and status text within the native responsive row sizes.

Existing destination files are used first, then fresh cached files, then media retrieved from the source. The plugin does not copy content, synchronize uploads or import Media Library records. Copy the site's content separately when preparing a local or staging site.

The plugin supports Timber 1 and 2 resizing, including Twig's resize filter and Flynt's normal `resizeDynamic` calls. Forced resizing, Retina generation from missing originals, SVG transformations, custom Resize subclasses, arbitrary transformation chains, and Flynt's separate on-demand generation mode are not supported. PHP readers that require a physical file in uploads may not work; general filesystem access and image editing are not covered.

See the [listing FAQ](readme.txt) for details of cache behavior, resizing, credentials and removal.

## Configuration in code

Define any of these constants in `wp-config.php` to override saved settings:

```php
define('ASK_THE_ORIGIN_ENABLED', true);
define('ASK_THE_ORIGIN_URL', 'https://origin.example');
define('ASK_THE_ORIGIN_USERNAME', 'example-user');
define('ASK_THE_ORIGIN_PASSWORD', 'example-password');
```

Use a boolean for `ENABLED` and strings for the other values. Defined `false` and empty strings are intentional overrides. Constant-controlled fields are disabled in Media settings; their previously saved values remain available if the constants are removed. Code-supplied passwords are not saved or displayed.

Configuration is applied in this order: saved settings, constants, then the `ask_the_origin_options` filter. The filter can override both saved settings and constants:

```php
add_filter('ask_the_origin_options', static function (array $options): array {
    $options['enabled'] = true;
    $options['url'] = 'https://origin.example';
    return $options;
});
```

Load configuration on every request, including REST and media requests, rather than only during template rendering. Visit the site's admin after configuration changes to update Apache routing.

## Development

```sh
composer install
composer check-platform-reqs
composer validate --strict
composer php:lint
composer rector:run  # Advisory dry-run
```

See [tests/README.md](tests/README.md) for integration checks. CI runs the Timber integration suite and strict WordPress Plugin Check. Tests and development dependencies are excluded from release packages.

## Releases

Configure the repository's `SVN_USERNAME` and `SVN_PASSWORD` Actions secrets with WordPress.org SVN deployment credentials.

Keep the plugin header version, `readme.txt` stable tag and changelog in sync. Push the release commit to `main`, then push a numeric version tag such as `1.0.1` (without a `v` prefix). **Pushing a tag publishes the plugin:** the release workflow runs the existing lint, Timber integration/Multisite and strict Plugin Check jobs, deploys to WordPress.org after they pass, and creates a GitHub release with the installable ZIP.

The `.wordpress-org/` artwork is deployed to WordPress.org's separate assets directory and excluded from the plugin ZIP through `.distignore`.

Between releases, run **Publish listing assets and readme** manually from `main`. Only use it after the current code version has been released; the action refuses to publish when files other than the readme/assets differ from WordPress.org. This workflow also writes to the live directory listing.

## License

GPLv3 or later. See [LICENSE](LICENSE).
