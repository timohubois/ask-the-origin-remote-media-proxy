=== Ask the Origin – Remote Media Proxy ===
Contributors: timohubois
Tags: uploads, development, media, proxy
Requires at least: 7.0
Tested up to: 7.1
Stable tag: 1.0.0
Requires PHP: 8.3
License: GPLv3 or later
License URI: https://www.gnu.org/licenses/gpl-3.0.html

Show media from the origin. Without copying the uploads library.

== Description ==

Ask the Origin – Remote Media Proxy displays missing media on a local or staging WordPress site by retrieving it on demand from a configured source site—the origin. You can use the source site's media without copying its uploads library.

Files already present on the destination site always take precedence. Retrieved files are cached privately, without being added to the uploads directory or imported into the Media Library.

Configure the source and optional HTTP Basic Auth under **Settings > Media**. No plugin is needed on the source site. The proxy stays disabled until you enable it; it does not enable itself based on the WordPress environment type.

**Features**

* Connect to sources protected by HTTP Basic Auth.
* Serve missing uploads through compatible PHP attachment readers.
* Support Timber 1 and 2 resizing, including Twig's resize filter and Flynt's normal resizeDynamic calls, subject to the compatibility limits below.
* Configure missing-upload routing automatically on supported Apache layouts.
* Use per-site settings on Multisite or supply configuration in code.

**Before you start**

The source must use a different hostname and matching uploads paths. Protect the destination site separately: source authentication does not restrict its visitors. Review Installation and the FAQs for routing requirements and compatibility limits.

== Installation ==

1. Upload the plugin to /wp-content/plugins/ask-the-origin-remote-media-proxy/ and activate it. Composer is not required. The proxy remains disabled after activation.
2. Review the routing requirements below and protect your local or staging site as needed. Configure manual server routing if required.
3. In **Settings > Media**, set **Remote site URL** to the source site's base URL and enter any required HTTP Basic Auth credentials. Enable the proxy and save changes.
4. Resolve any routing warning shown in the admin and check a page with missing media.

The source must use a different hostname and matching uploads paths relative to its site base. The plugin does not translate different single-site, Multisite or CDN upload layouts.

**Apache:** the plugin manages a marked block in WP_CONTENT_DIR/.htaccess. This requires mod_rewrite, a writable content directory, and uploads within the content directory and its public URL path. Review existing rules before enabling: child rewrite rules can replace inherited security restrictions. Keep restrictions on script execution in uploads. If missing files still return 404, check that mod_rewrite is loaded; PHP-FPM and WP-CLI cannot always detect it.

**nginx, Caddy or other layouts:** configure the server to route missing upload requests to WordPress's front controller instead of returning a static 404. Preserve upload security restrictions.

== Frequently Asked Questions ==

= Does this copy or synchronize my Media Library? =

No. The plugin serves missing files from matching uploads paths on the configured source. It does not import attachment records, migrate content or synchronize uploads. Existing local files are used first and are never replaced with source files.

If you need the source site's pages, posts and Media Library entries, copy that content separately as part of your development setup.

= Where are files cached? =

The plugin uses WordPress's temporary directory, respecting WP_TEMP_DIR. Storage must be writable and outside known web roots. Local files are used first, then cached files, then remote downloads.

Cached files are fresh for five minutes. Expiration does not delete them, and there is no automatic cache cleanup. There is no download-size limit; large files can fill temporary storage. This plugin is intended for images and modest-sized files, not large video delivery.

If the source is unavailable, local files and fresh cached files still work. Expired files are not served. Browser-cached media may remain visible briefly after disabling the proxy or changing its settings.

= Does it work with Timber and Flynt? =

The plugin integrates with Timber 1 and 2. When the local original and derivative are missing, supported resizes return signed image URLs. The image request retrieves the remote derivative or, only after a remote 404, downloads the original and resizes it with Timber. Existing local files retain native behavior.

The resizing integration requires allow_url_fopen and REST API access. Forced resizing, Retina generation from missing originals, SVG transformations, custom Resize subclasses, arbitrary transformation chains, and Flynt's separate on-demand generation mode are not supported. Filters applied only during template rendering do not carry over to the image request.

Changing the source configuration or WordPress authentication keys invalidates signed URLs; refresh cached HTML afterwards.

= Can PHP read missing attachments? =

Compatible readers can use the read-only URI returned by get_attached_file(). This requires allow_url_fopen. Obtain the URI each request rather than storing it. Code that requires a physical file in uploads may not work; general filesystem access and image editing are not covered.

= How are credentials and protected media handled? =

Use HTTPS and trust the configured source. Saved passwords are encrypted using WordPress authentication keys; changing those keys or the site ID requires re-entering saved passwords. Administrators with access to Media settings can inspect the saved password. Code-supplied passwords are not stored or displayed.

**Protect the destination site separately.** Source Basic Auth does not restrict destination visitors. The plugin follows WordPress's allowed file types, but does not sanitize SVG content if SVG uploads are enabled.

= Can I configure it in code? =

Yes. Configuration constants and the ask_the_origin_options filter can override saved settings. See the [GitHub README](https://github.com/timohubois/ask-the-origin-remote-media-proxy/#configuration-in-code) for examples.

= What happens when I disable or remove it? =

Disabling or deactivating removes the plugin's Apache directives and retains settings. Uninstalling also removes settings, never uploads or attachment data. Cached files are left for host or administrator cleanup. Routing cleanup failures are reported for retry.

Disable the proxy before moving WordPress or its content directory.

== Remote requests ==

When enabled, the plugin requests missing media from your configured source site. Requests include the file path and any Basic Auth credentials you provide. Visitor cookies and authorization headers are not forwarded. No developer-operated service is used.

== Want to contribute? ==

Visit the [GitHub repository](https://github.com/timohubois/ask-the-origin-remote-media-proxy/) to report issues or contribute.

== Changelog ==

= 1.0.0 =
* Initial release.
