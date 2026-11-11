# Ask the Origin – Remote Media Proxy

Use production media on local and staging WordPress sites without copying the uploads library. Existing local files take precedence; missing media is cached privately, never added to uploads.

## Setup

Requires WordPress 7.0+ and PHP 8.3+. Install in `wp-content/plugins/ask-the-origin-remote-media-proxy/`, activate, and configure the source under **Settings > Media**. Composer is not required at runtime.

The source must use a different hostname and matching uploads paths. Supported Apache layouts are configured automatically; other servers need manual routing. Review existing Apache rules before enabling, and protect the destination site separately from the source.

See [readme.txt](readme.txt) for server requirements, caching, compatibility and security notes.

## Configuration in code

Define any of these constants in `wp-config.php` to override saved settings:

```php
define('ASK_THE_ORIGIN_ENABLED', true);
define('ASK_THE_ORIGIN_URL', 'https://production.example');
define('ASK_THE_ORIGIN_USERNAME', 'example-user');
define('ASK_THE_ORIGIN_PASSWORD', 'example-password');
```

Use a boolean for `ENABLED` and strings for the other values. Defined `false` and empty strings are intentional overrides. Constant-controlled fields are disabled in Media settings; their previously saved values remain available if the constants are removed. Code-supplied passwords are not saved or displayed.

The `ask_the_origin_options` filter runs after saved settings and constants:

```php
add_filter('ask_the_origin_options', static function (array $options): array {
    $options['enabled'] = true;
    $options['url'] = 'https://production.example';
    return $options;
});
```

Apply configuration site-wide, not just during template rendering. Visit the site's admin after code changes to update Apache routing.

## Development

```sh
composer install
composer check-platform-reqs
composer validate --strict
composer php:lint
composer rector:run  # Advisory dry-run
```

See [tests/README.md](tests/README.md) for integration checks. CI runs the Timber integration suite and strict WordPress Plugin Check. Tests and development dependencies are excluded from release packages.

## License

GPLv3 or later. See [LICENSE](LICENSE).
