<?php

/** CLI-only routing and toolbar checks; run in a disposable subdirectory Multisite installation. */

namespace AskTheOrigin\Tests;

use AskTheOrigin\Features\ApacheRouting;
use AskTheOrigin\Features\OptionsMedia;
use RuntimeException;
use WP_CLI;
use WP_Admin_Bar;

if (!defined('WP_CLI') || !WP_CLI || !is_multisite() || is_subdomain_install()) {
    throw new RuntimeException('Run with WP-CLI in a disposable subdirectory Multisite installation.');
}
require_once ABSPATH . 'wp-admin/includes/plugin.php';
require_once ABSPATH . 'wp-includes/class-wp-admin-bar.php';
if (!is_plugin_active_for_network(plugin_basename(ASK_THE_ORIGIN_PLUGIN_FILE))) {
    throw new RuntimeException('Ask the Origin must be network-active.');
}

$path = trailingslashit(get_network()->path) . 'ato-routing-' . wp_generate_uuid4() . '/';
$options = static function (array $options) use ($path): array {
    return get_site()->path === $path
        ? array_replace($options, ['enabled' => true, 'url' => 'https://media.example.test'])
        : array_replace($options, ['enabled' => false]);
};
$verify = static function (bool $condition, string $message): void {
    if (!$condition) {
        throw new RuntimeException(esc_html($message));
    }
    WP_CLI::log('PASS: ' . $message);
};
$block = static function (int $siteId): string {
    // Inspect the actual lifecycle-generated fixture rules.
    $contents = file_get_contents(WP_CONTENT_DIR . '/.htaccess');
    preg_match('/# BEGIN Ask the Origin [^\r\n]*\(site ' . $siteId . '\)\R(.*?)# END /s', $contents, $matches);
    return $matches[1] ?? '';
};
$status = static function (): ?object {
    $adminBar = new WP_Admin_Bar();
    do_action('admin_bar_menu', $adminBar);
    return $adminBar->get_node('ask-the-origin');
};
$originalUserId = get_current_user_id();
$superAdmin = get_user_by('login', get_super_admins()[0] ?? '');
if ($superAdmin === false) {
    throw new RuntimeException('A network super administrator is required for toolbar checks.');
}
$siteId = 0;
add_filter('ask_the_origin_options', $options);
try {
    wp_set_current_user($superAdmin->ID);
    $mainSiteName = get_bloginfo('name');
    $disabledLabel = sprintf(
        /* translators: %s: Name of the current WordPress site. */
        __('Ask the Origin is disabled on %s.', 'ask-the-origin-remote-media-proxy'),
        $mainSiteName !== '' ? $mainSiteName : home_url()
    );
    $verify(
        $status() !== null && str_contains($status()->title, esc_html($disabledLabel)),
        'network activation keeps the menu available even when the current site proxy is disabled'
    );
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
        $node = $status();
        $siteName = get_bloginfo('name');
        $label = sprintf(
            /* translators: %s: Name of the current WordPress site. */
            __('Ask the Origin is enabled on %s.', 'ask-the-origin-remote-media-proxy'),
            $siteName !== '' ? $siteName : home_url()
        );
        $verify(
            $node !== null && str_contains($node->title, esc_html($label))
            && $node->meta['title'] === __('Ask the Origin: Enabled', 'ask-the-origin-remote-media-proxy')
            && $node->href === esc_url(admin_url('options-media.php#ask_the_origin_enabled')),
            'the enabled subsite icon names that site and links to its own settings'
        );
        $verify(ApacheRouting::getInstance()->sync(), 'nested switched-site synchronization succeeds');
        $verify($block($siteId) === $initial, 'nested synchronization leaves the owned rules unchanged');
        $verify(
            get_option(OptionsMedia::OPTION_NAME, []) === [],
            'routing does not persist the effective test configuration in site settings'
        );
    } finally {
        restore_current_blog();
    }
    $verify(
        $status() !== null && str_contains($status()->title, esc_html($disabledLabel)),
        'returning to the main site keeps its menu available with the correct disabled status'
    );
} finally {
    wp_set_current_user($originalUserId);
    remove_filter('ask_the_origin_options', $options);
    if (is_int($siteId) && $siteId > 0) {
        wp_delete_site($siteId);
    }
}
$verify(!str_contains($block($siteId), 'RewriteCond'), 'site deletion clears its owned routing');
WP_CLI::success('Multisite routing and toolbar checks passed.');
