# Integration checks

Run in a **disposable WordPress installation**, as a non-root user, with:

- Ask the Origin – Remote Media Proxy active and Timber loaded before `after_setup_theme` finishes;
- PHP 8.3+, GD and `allow_url_fopen`;
- no predefined `WP_TEMP_DIR` (the suite supplies isolated storage outside web roots).

Imagick is optional for animated-GIF checks.

```sh
wp eval-file /absolute/path/to/checkout/tests/integration.php
```

Run in its own CLI process, not another test runner or a web request. The suite changes fixture permissions, substitutes a failing image editor and installs a non-removable output buffer. It intercepts HTTP requests and cleans up its fixtures without changing saved settings or attachments.

Progress and the final summary use `WP_CLI::log()`: `--quiet` suppresses them without changing assertion counts. Assertion failures still throw `RuntimeException`; the entrypoint completes `finally` cleanup before reporting them with `WP_CLI::error()` and exit code 1. Quiet mode does not hide failures. The success summary is emitted only after cleanup succeeds.

Fixture operations use a local `WP_Filesystem_Direct` instance without replacing the global transport or requesting credentials. Directories and seeded files use explicit private permissions; initial directory creation also uses a restrictive umask that is restored immediately. Writes, permission changes, expiration timestamps and intermediate deletions are checked; final cleanup attempts every deletion and reports failures instead of printing a success summary. Deletion stays non-recursive and independent of WordPress deletion filters.

The checks cover signed resizing, local precedence, caching, stream reads and cleanup. CI runs against Timber 1 and 2 and logs the resolved versions.

CI then converts the disposable installation to a subdirectory Multisite network and network-activates the plugin. Run `wp eval-file /absolute/path/to/checkout/tests/multisite.php` in that setup to check routing during new-site initialization, network synchronization and site deletion.

The disposable Timber 1 job allows legacy Twig security advisories; the audit still reports them. This exception does not apply to Timber 2 or the plugin's dependencies. Test fixtures and dependencies are not shipped with the plugin.
