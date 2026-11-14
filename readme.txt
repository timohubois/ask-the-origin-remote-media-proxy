=== Ask the Origin – Remote Media Proxy ===
Contributors: timohubois
Tags: uploads, development, media, proxy
Requires at least: 7.0
Tested up to: 7.1
Stable tag: 1.1.2
Requires PHP: 8.3
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Display missing media on local and staging sites from a remote WordPress site, without copying the uploads library.

== Description ==

Ask the Origin – Remote Media Proxy displays missing media on a local or staging WordPress site by retrieving it on demand from a configured source site—the origin.

Files already present on your site take precedence. Retrieved files are cached privately, without being added to the uploads directory or imported into the Media Library.

Configure the source and optional HTTP Basic Auth under **Settings > Media**. No plugin is needed on the source site. The proxy remains disabled until you enable it; it does not enable itself based on the WordPress environment type.

**Features**

* Connect to sources protected by HTTP Basic Auth.
* Integrate with Timber 1 and 2 resize filters and Flynt's `resizeDynamic` calls.
* Enable or disable the proxy through a native Media toolbar menu, available to users who can manage settings.
* Use per-site settings on Multisite or supply configuration in code.

**Before you start**

The source must use a different hostname and matching uploads paths relative to each site's base URL. Different single-site, Multisite or CDN upload layouts cannot be mapped.

**Protect your local or staging site separately.** Authentication on the source does not restrict visitors to your site.

== Installation ==

1. Under **Plugins > Add New**, search for **Ask the Origin – Remote Media Proxy**, install it and activate it. Alternatively, upload the plugin folder to /wp-content/plugins/ask-the-origin-remote-media-proxy/.
2. Check the server-routing requirements below before enabling the proxy.
3. In **Settings > Media**, set **Remote site URL** to the source site's base URL and enter any required HTTP Basic Auth credentials.
4. Enable the proxy and save changes, then check a page with missing media.

**Apache:** the plugin manages a marked block in `WP_CONTENT_DIR/.htaccess`. This requires `mod_rewrite`, a writable content directory, and uploads within the content directory and its public URL path.

Review existing rules before enabling: child rewrite rules can replace inherited security restrictions. Keep restrictions on script execution in uploads. If missing files still return 404, check that `mod_rewrite` is loaded; PHP-FPM and WP-CLI cannot always detect it.

**nginx, Caddy and other servers:** on the local or staging site, missing uploads must be routed internally to WordPress's `index.php`. Existing files should be served normally. Your current WordPress configuration may already do this; otherwise, ask your host to adjust the fallback while preserving upload security rules.

== Frequently Asked Questions ==

= Does this copy or synchronize my Media Library? =

No. The plugin serves missing files from matching uploads paths on the configured source. It does not import attachment records, migrate content or synchronize uploads. Existing local files are never replaced.

If you need the source site's pages, posts and Media Library entries, copy that content separately as part of your development setup.

= Where are files cached? =

Files are cached in WordPress's temporary directory, which must be writable and outside the public web root. Use `WP_TEMP_DIR` to select a different directory if needed. Local files are used first, then cached files, then remote downloads.

Cached files are fresh for five minutes. Expiration does not delete them, and there is no automatic cache cleanup. There is no download-size limit; large files can fill temporary storage. The plugin is intended for images and modest-sized files, not large video delivery.

If the source is unavailable, local files and fresh cached files still work. Expired files are not served. Browser-cached media may remain visible briefly after disabling the proxy or changing its settings.

= Does it work with Timber and Flynt? =

The plugin integrates with [Timber](https://github.com/timber/timber) 1 and 2, including Twig's `resize` filter, and [Flynt](https://github.com/flyntwp/flynt)'s `resizeDynamic` calls.

When the local original and derivative are missing, supported resizes return signed image URLs. The image request retrieves the remote derivative or, only after a remote 404, downloads the original and resizes it with Timber. Existing local files retain native behavior.

The resizing integration requires `allow_url_fopen` and REST API access. Forced resizing, Retina generation from missing originals, SVG transformations, custom Resize subclasses, arbitrary transformation chains, and Flynt's separate on-demand generation mode are not supported. Filters applied only during template rendering do not carry over to the image request.

Changing the source configuration or WordPress authentication keys invalidates signed URLs; refresh cached HTML afterwards.

= Can PHP read missing attachments? =

Compatible readers can use the read-only URI returned by `get_attached_file()`. This requires `allow_url_fopen`.

Obtain the URI each request rather than storing it. Code that requires a physical file in uploads may not work; general filesystem access and image editing are not covered.

= How are credentials and protected media handled? =

Use HTTPS and trust the configured source. Saved passwords are encrypted using WordPress authentication keys; changing those keys or the site ID requires re-entering saved passwords.

Administrators with access to Media settings can inspect the saved password. Code-supplied passwords are not stored or displayed.

The plugin follows WordPress's allowed file types, but does not sanitize SVG content if SVG uploads are enabled.

= Can I configure it in code? =

Yes. Define any of these constants in `wp-config.php` to override saved settings:

* `ASK_THE_ORIGIN_ENABLED`: a boolean.
* `ASK_THE_ORIGIN_URL`: the source site's base URL.
* `ASK_THE_ORIGIN_USERNAME`: the HTTP Basic Auth username.
* `ASK_THE_ORIGIN_PASSWORD`: the HTTP Basic Auth password.

Use strings for the URL and credentials. Defined false and empty strings are intentional overrides. Constant-controlled fields are disabled in Media settings; their saved values remain available if the constants are removed.

Configuration is applied in this order: **saved settings, constants, then the `ask_the_origin_options` filter**. The filter receives and must return an options array. Its keys are `enabled`, `url`, `username` and `password`; preserve any other values you are not changing.

```php
add_filter('ask_the_origin_options', static function (array $options): array {
    $options['enabled'] = true;
    $options['url'] = 'https://origin.example';
    return $options;
});
```

Load configuration on every request, including REST and media requests—not only during template rendering. Visit the site's admin after configuration changes to update Apache routing.

A constant or filter that fixes the proxy status makes the toolbar status control read-only. Overriding other fields alone does not lock the toggle.

See the [configuration examples and details](https://github.com/timohubois/ask-the-origin-remote-media-proxy/#configuration-in-code).

= What happens when I disable or remove it? =

Disabling or deactivating removes the plugin's Apache directives and retains settings. Uninstalling also removes settings, never uploads or attachment data.

Cached files are left for host or administrator cleanup. If Apache rule cleanup fails, correct the reported `.htaccess` problem and retry disabling, deactivating or uninstalling the plugin.

Disable the proxy before moving WordPress or its content directory.

== Screenshots ==

1. Configure the origin site, optional HTTP Basic Auth, and proxy status under Settings > Media.

== Remote requests ==

When enabled, the plugin requests missing media from your configured source site. Requests include the file path and any Basic Auth credentials you provide. Visitor cookies and authorization headers are not forwarded. No developer-operated service is used.

== Want to contribute? ==

Report issues or contribute on [GitHub](https://github.com/timohubois/ask-the-origin-remote-media-proxy/).

== Changelog ==

= 1.1.2 =
* Fix a toolbar nonce field collision that prevented native Classic Editor autosaves for users who can manage settings.

= 1.1.1 =
* Fix the Media toolbar icon and its alignment on WordPress 7.2 while keeping compatibility with older supported WordPress versions.

See [earlier releases](https://github.com/timohubois/ask-the-origin-remote-media-proxy/releases) for older changes.
