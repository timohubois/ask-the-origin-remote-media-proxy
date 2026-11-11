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

The checks cover signed resizing, local precedence, caching, stream reads and cleanup. CI runs against Timber 1 and 2 and logs the resolved versions.

CI then converts the disposable installation to a subdirectory Multisite network and network-activates the plugin. Run `wp eval-file /absolute/path/to/checkout/tests/multisite.php` in that setup to check routing during new-site initialization, network synchronization and site deletion.

The disposable Timber 1 job allows legacy Twig security advisories; the audit still reports them. This exception does not apply to Timber 2 or the plugin's dependencies. Test fixtures and dependencies are not shipped with the plugin.
