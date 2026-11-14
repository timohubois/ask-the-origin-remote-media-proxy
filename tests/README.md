# Ask the Origin – Remote Media Proxy — Integration checks

These development suites exercise the plugin inside WordPress through WP-CLI.

## Single-site suite

Run in a **disposable WordPress installation**, as a non-root user, with:

- WP-CLI available, Ask the Origin – Remote Media Proxy active, and Timber 1 or 2 loaded before `after_setup_theme` finishes;
- PHP 8.3+, GD and `allow_url_fopen`;
- no predefined `WP_TEMP_DIR` (the suite supplies isolated storage outside web roots).

Imagick is optional for animated-GIF checks.

Run from the disposable WordPress installation's root, replacing the checkout path:

```sh
wp eval-file /absolute/path/to/checkout/tests/integration.php
```

Run in its own CLI process, not another test runner or a web request. The suite changes fixture permissions, substitutes a failing image editor and installs a non-removable output buffer. It intercepts HTTP requests and cleans up its fixtures without changing saved settings or attachments.

Progress and the final summary use `WP_CLI::log()`: `--quiet` suppresses them without changing assertion counts. Assertion failures still throw `RuntimeException`; the entrypoint completes `finally` cleanup before reporting them with `WP_CLI::error()` and exit code 1. Quiet mode does not hide failures. The success summary is emitted only after cleanup succeeds.

Fixture operations use a local `WP_Filesystem_Direct` instance without replacing the global transport or requesting credentials. Directories and seeded files use explicit private permissions; initial directory creation also uses a restrictive umask that is restored immediately. Writes, permission changes, expiration timestamps and intermediate deletions are checked; final cleanup attempts every deletion and reports failures instead of printing a success summary. Deletion stays non-recursive and independent of WordPress deletion filters.

The checks cover signed resizing, local precedence, caching, stream reads and cleanup. CI runs against Timber 1 and 2 and logs the resolved versions.

## Toolbar menu and toggle suite

With the plugin active in a disposable WordPress installation (single-site or Multisite), run:

```sh
wp eval-file /absolute/path/to/checkout/tests/admin-bar.php
```

One shared set of temporary users covers capability grants and denials, both status values, the native Media SVG on WordPress 7.2+ and Dashicon fallback on older versions, tooltip matching the plain dropdown status, accessible site-specific labels, dropdown labels and colors, Settings link, untouched native menus and code-controlled read-only states. Timber is not required.

Isolated CLI-subprocess requests exercise the real POST handler, including normal redirects. They reject invalid methods, permissions, nonces, states, cross-site payloads and locked configuration; verify enable/disable, repeated requests and source-URL-only filters; and preserve opaque credentials and unrelated settings. Prevented or altered saves fail safely, and every request must restore the Settings API sanitizer.

The suite restores the original option and current user and removes its users, including network-wide deletion on Multisite. It intentionally changes saved settings and can synchronize plugin-owned Apache routing: **run only in disposable installations, never on a live project**.

CI runs it with single-site activation, Multisite network activation, Network Admin context, and Multisite per-site activation.

## Multisite routing and toolbar suite

Use a disposable **subdirectory Multisite** installation with the plugin network-active and `WP_CONTENT_DIR/.htaccess` writable. CI converts its single-site fixture to this setup after the first suite. See [the CI workflow](../.github/workflows/commit-checks.yml) for the complete setup.

From that installation's root, run:

```sh
wp eval-file /absolute/path/to/checkout/tests/multisite.php
```

This suite creates and deletes a temporary site and writes plugin-owned routing blocks in `WP_CONTENT_DIR/.htaccess`. It checks new-site initialization, network synchronization and site deletion, plus site-specific toolbar status and settings links when switching between an enabled subsite and disabled main site. The toolbar menu remains available in both states. Test configuration is not persisted in saved settings.

## CI dependencies

The disposable Timber 1 job allows legacy Twig security advisories; the audit still reports them. This exception does not apply to Timber 2 or the plugin's dependencies. Test fixtures and dependencies are not shipped with the plugin.
