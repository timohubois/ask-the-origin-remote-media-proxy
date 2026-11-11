<?php

/** CLI-only routing regression checks; run in a disposable subdirectory Multisite installation. */

namespace AskTheOrigin\Tests;

use AskTheOrigin\Features\ApacheRouting;
use AskTheOrigin\Features\OptionsMedia;
use RuntimeException;
use WP_CLI;

if (!defined('WP_CLI') || !WP_CLI || !is_multisite() || is_subdomain_install()) {
    throw new RuntimeException('Run with WP-CLI in a disposable subdirectory Multisite installation.');
}
require_once ABSPATH . 'wp-admin/includes/plugin.php';
if (!is_plugin_active_for_network(plugin_basename(ASK_THE_ORIGIN_PLUGIN_FILE))) {
    throw new RuntimeException('Ask the Origin must be network-active.');
}

$path = trailingslashit(get_network()->path) . 'ato-routing-' . wp_generate_uuid4() . '/';
$options = static function (array $options) use ($path): array {
    return get_site()->path === $path
        ? array_replace($options, ['enabled' => true, 'url' => 'https://media.example.test']) : $options;
};
$verify = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException(esc_html($message));
    }
    WP_CLI::log('PASS: ' . $message);
};
$block = static function (int $siteId): string {
    // phpcs:ignore WordPress.WP.AlternativeFunctions.file_get_contents_file_get_contents -- Inspect the actual lifecycle-generated fixture rules.
    $contents = file_get_contents(WP_CONTENT_DIR . '/.htaccess');
    preg_match('/# BEGIN Ask the Origin [^\r\n]*\(site ' . $siteId . '\)\R(.*?)# END /s', $contents, $matches);
    return $matches[1] ?? '';
};
$siteId = 0;
add_filter('ask_the_origin_options', $options);
try {
    $siteId = wp_insert_site(['domain' => get_network()->domain, 'path' => $path]);
    if (is_wp_error($siteId)) {
        throw new RuntimeException(esc_html($siteId->get_error_message()));
    }
    $expected = preg_quote($path . 'wp-content/uploads/sites/' . $siteId . '/', '#');
    $initial = $block($siteId);
    $verify(
        str_contains($initial, 'RewriteCond %{REQUEST_URI} ^' . $expected),
        'new-site initialization writes the direct-request upload URL before any site visit'
    );
    ApacheRouting::getInstance()->syncSites(true);
    $verify($block($siteId) === $initial, 'network synchronization preserves the same per-site routing');
    switch_to_blog($siteId);
    try {
        $verify(ApacheRouting::getInstance()->sync(), 'nested switched-site synchronization succeeds');
        $verify($block($siteId) === $initial, 'nested synchronization leaves the owned rules unchanged');
        $verify(
            get_option(OptionsMedia::OPTION_NAME, []) === [],
            'routing does not persist the effective test configuration in site settings'
        );
    } finally {
        restore_current_blog();
    }
} finally {
    remove_filter('ask_the_origin_options', $options);
    if (is_int($siteId) && $siteId > 0) {
        wp_delete_site($siteId);
    }
}
$verify(!str_contains($block($siteId), 'RewriteCond'), 'site deletion clears its owned routing');
WP_CLI::success('Multisite routing checks passed.');
