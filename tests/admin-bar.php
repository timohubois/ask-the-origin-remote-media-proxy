<?php

/** CLI-only toolbar and toggle checks; run in a disposable WordPress installation with this plugin active. */

namespace AskTheOrigin\Tests;

use AskTheOrigin\Features\AdminBar;
use AskTheOrigin\Features\OptionsMedia;
use RuntimeException;
use WP_Admin_Bar;
use WP_CLI;

if (!defined('WP_CLI') || !WP_CLI || !class_exists(AdminBar::class)) {
    throw new RuntimeException('Run with WP-CLI and Ask the Origin active in a disposable installation.');
}

// Each request gets a separate process so successful redirects can exit normally.
if (($args[0] ?? '') === 'request') {
    $request = json_decode(base64_decode($args[1] ?? '', true) ?: '', true);
    if (!is_array($request)) {
        throw new RuntimeException('Invalid CLI request fixture.');
    }
    wp_set_current_user($request['user']);
    if (($request['config'] ?? '') === 'constant') {
        define('ASK_THE_ORIGIN_ENABLED', false);
    } elseif (($request['config'] ?? '') === 'forced') {
        add_filter('ask_the_origin_options', static fn(array $options): array =>
            array_replace($options, ['enabled' => false]));
    } elseif (($request['config'] ?? '') === 'url-only') {
        add_filter('ask_the_origin_options', static fn(array $options): array =>
            array_replace($options, ['url' => 'https://code-origin.example']));
    }
    if (($request['config'] ?? '') === 'blocked-save') {
        add_filter('pre_update_option_' . OptionsMedia::OPTION_NAME, static fn($value, $previous) => $previous, 10, 2);
    } elseif (($request['config'] ?? '') === 'altered-save') {
        add_filter('pre_update_option_' . OptionsMedia::OPTION_NAME, static fn(array $value): array =>
            $value['enabled'] ? array_replace($value, ['extension' => 'unexpected-change']) : $value);
    }
    $_SERVER['REQUEST_METHOD'] = $request['method'];
    $_POST = $request['body'];
    // phpcs:ignore WordPress.Security.NonceVerification.Missing -- CLI fixture; the real action verifies the nonce.
    $_REQUEST = $_POST;
    $report = ['response' => 500];
    register_shutdown_function(static function () use (&$report): void {
        $report['sanitizer'] = has_filter(
            'sanitize_option_' . OptionsMedia::OPTION_NAME,
            [OptionsMedia::getInstance(), 'sanitizeOptions']
        ) !== false;
        // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- CLI-only structured result, not HTML.
        echo wp_json_encode($report), "\n";
    });
    add_filter('wp_die_handler', static function () use (&$report) {
        return static function ($message, $title, $arguments) use (&$report): never {
            $report['response'] = $arguments['response'] ?? 500;
            exit;
        };
    }, PHP_INT_MAX);
    add_filter('wp_redirect', static function ($location, $response) use (&$report) {
        $report = ['response' => $response, 'location' => $location];
        return $location;
    }, PHP_INT_MAX, 2);
    // Register the same Settings API sanitizer as admin-post.php without running unrelated admin initialization.
    OptionsMedia::getInstance()->addSettings();
    do_action('admin_post_ask_the_origin_toggle');
    exit;
}

require_once ABSPATH . 'wp-includes/class-wp-admin-bar.php';
require_once ABSPATH . 'wp-admin/includes/user.php';
if (is_multisite()) {
    require_once ABSPATH . 'wp-admin/includes/ms.php';
}

$originalUserId = get_current_user_id();
$originalOptions = get_option(OptionsMedia::OPTION_NAME, null);
$userIds = [];
$checks = 0;
$enabled = true;
$seed = [
    'enabled' => false,
    'url' => 'https://private-origin.example',
    'username' => 'private-origin-user',
    'password' => ['version' => 1, 'ciphertext' => 'unreadable-opaque-credential'],
    'extension' => ['preserve' => 'unchanged'],
];
$optionsFilter = static function () use (&$enabled, $seed): array {
    return array_replace($seed, ['enabled' => $enabled]);
};
$forced = static fn(array $options): array => array_replace($options, ['enabled' => true]);
$urlOnly = static fn(array $options): array => array_replace($options, ['url' => 'https://other-origin.example']);
// Fixture writes must preserve raw credentials; child requests register the real Settings API sanitizer.
$sanitize = [OptionsMedia::getInstance(), 'sanitizeOptions'];
$sanitizeHook = 'sanitize_option_' . OptionsMedia::OPTION_NAME;
$sanitizePriority = has_filter($sanitizeHook, $sanitize);
if ($sanitizePriority !== false) {
    remove_filter($sanitizeHook, $sanitize, $sanitizePriority);
}
$verify = static function (bool $condition, string $message) use (&$checks): void {
    if (!$condition) {
        throw new RuntimeException(esc_html($message));
    }
    ++$checks;
    WP_CLI::log('PASS: ' . $message);
};
$toolbar = static function (bool $withSiteName = true): WP_Admin_Bar {
    $adminBar = new WP_Admin_Bar();
    if ($withSiteName) {
        wp_admin_bar_site_menu($adminBar);
    }
    do_action('admin_bar_menu', $adminBar);
    return $adminBar;
};
$status = static fn(): ?object => $toolbar()->get_node('ask-the-origin');
$readSaved = static function (): array {
    // Child requests have their own object cache, so discard this process's stale option values.
    wp_cache_delete(OptionsMedia::OPTION_NAME, 'options');
    wp_cache_delete('alloptions', 'options');
    return get_option(OptionsMedia::OPTION_NAME, []);
};
$request = static function (array $payload) use ($verify): array {
    $command = 'eval-file ' . escapeshellarg(__FILE__) . ' request '
        . escapeshellarg(base64_encode(wp_json_encode($payload)))
        . ' --path=' . escapeshellarg(ABSPATH) . ' --url=' . escapeshellarg(home_url());
    $result = WP_CLI::runcommand($command, ['launch' => true, 'return' => 'all', 'exit_error' => false]);
    $report = json_decode(trim($result->stdout), true);
    $verify(
        $result->return_code === 0 && is_array($report) && $report['sanitizer'],
        'the isolated toggle request completed with its Settings API sanitizer restored'
    );
    return $report;
};
add_filter('pre_option_' . OptionsMedia::OPTION_NAME, $optionsFilter, PHP_INT_MAX);
try {
    foreach (['subscriber', 'author', 'editor', 'administrator'] as $role) {
        $userId = wp_insert_user([
            'user_login' => 'ato-toolbar-' . wp_generate_uuid4(),
            'user_pass' => wp_generate_password(32),
            'role' => $role,
        ]);
        if (is_wp_error($userId)) {
            throw new RuntimeException(esc_html($userId->get_error_message()));
        }
        $userIds[$role] = $userId;
    }
    wp_set_current_user(0);
    $verify($status() === null, 'anonymous visitors do not receive the enabled status');
    foreach (['subscriber', 'author', 'editor'] as $role) {
        wp_set_current_user($userIds[$role]);
        $verify($status() === null, $role . ' does not receive the enabled status without manage_options');
        $enabled = false;
        $verify($status() === null, $role . ' does not receive the disabled status either');
        $enabled = true;
    }
    $user = wp_get_current_user();
    $user->add_cap('manage_options');
    $verify($status() !== null, 'an editor explicitly granted manage_options receives the status');
    $user->remove_cap('manage_options');
    $verify($status() === null, 'removing manage_options hides the status again');
    wp_set_current_user($userIds['administrator']);
    foreach ([true, false] as $enabled) {
        $adminBar = new WP_Admin_Bar();
        wp_admin_bar_site_menu($adminBar);
        $originalSiteName = $adminBar->get_node('site-name');
        do_action('admin_bar_menu', $adminBar);
        $node = $adminBar->get_node('ask-the-origin');
        $siteName = get_bloginfo('name');
        $statusText = $enabled ? __('Ask the Origin: Enabled', 'ask-the-origin-remote-media-proxy')
            : __('Ask the Origin: Disabled', 'ask-the-origin-remote-media-proxy');
        $label = sprintf(
            /* translators: %s: Name of the current WordPress site. */
            $enabled ? __('Ask the Origin is enabled on %s.', 'ask-the-origin-remote-media-proxy')
                /* translators: %s: Name of the current WordPress site. */
                : __('Ask the Origin is disabled on %s.', 'ask-the-origin-remote-media-proxy'),
            $siteName !== '' ? $siteName : home_url()
        );
        $verify(
            $node !== null && empty($node->parent)
            && $node->icon === 'core-admin/media'
            && $node->title === '<span class="ab-icon dashicons-admin-media" aria-hidden="true"></span>'
                . '<span class="screen-reader-text">' . esc_html($label) . '</span>',
            'the independent Media icon has no visible text and an explicit screen-reader status label'
        );
        $verify(
            $node->meta['title'] === $statusText
            && $node->meta['menu_title'] === $label,
            'the tooltip is the plain status while the accessible menu name identifies the site and status'
        );
        $verify(
            $adminBar->get_node('site-name') == $originalSiteName,
            'the native site-name menu remains unchanged'
        );
        $statusRow = $adminBar->get_node('ask-the-origin-status');
        $verify(
            $statusRow !== null && $statusRow->parent === 'ask-the-origin'
            && str_contains($statusRow->title, $enabled ? 'is-enabled' : 'is-disabled')
            && wp_strip_all_tags((string) strstr($statusRow->title, '<span class="screen-reader-text">', true))
                === $statusText && $statusRow->meta['title'] === $statusText
            && !empty($statusRow->href),
            'the toggle names the plugin and state beside the colored indicator'
        );
        $verify(
            str_contains($statusRow->meta['html'], 'method="post"')
            && str_contains($statusRow->meta['html'], 'name="enabled" value="' . ($enabled ? '0' : '1') . '"')
            && str_contains($statusRow->meta['html'], 'name="site_id" value="' . get_current_blog_id() . '"')
            && str_contains($statusRow->meta['html'], 'name="_wpnonce"'),
            'the toggle uses a nonce-protected POST form bound to this site and an explicit desired state'
        );
        $settings = $adminBar->get_node('ask-the-origin-settings');
        $settingsUrl = esc_url(admin_url('options-media.php#ask_the_origin_enabled'));
        $verify(
            $settings !== null && $settings->parent === 'ask-the-origin'
            && $settings->title === esc_html__('Settings', 'ask-the-origin-remote-media-proxy')
            && $settings->href === $settingsUrl && $node->href === $settingsUrl,
            'the icon and Settings submenu link to this site\'s Status setting'
        );
        $verify(
            !str_contains(serialize($adminBar->get_nodes()), 'private-origin'),
            'the icon and dropdown do not expose source configuration'
        );
        $nodeCount = count($adminBar->get_nodes());
        do_action('admin_bar_menu', $adminBar);
        $verify(count($adminBar->get_nodes()) === $nodeCount, 'repeated hooks do not duplicate the menu');
        $verify($toolbar(false)->get_node('ask-the-origin') !== null, 'the icon needs no site-name menu');
        ob_start();
        $adminBar->render();
        $html = ob_get_clean();
        $iconLink = (string) strstr((string) strstr($html, "id='wp-admin-bar-ask-the-origin'"), '</a>', true);
        $usesSvgIcon = function_exists('_wp_admin_bar_icon');
        $verify(
            str_contains($iconLink, '<span class="ab-icon dashicons-admin-media" aria-hidden="true"></span>')
            && ($usesSvgIcon
                ? str_contains($iconLink, '<span class="ab-icon svg-icon" aria-hidden="true">'
                    . wp_get_icon('core-admin/media') . '</span>') && substr_count($iconLink, '<svg') === 1
                : !str_contains($iconLink, '<svg')),
            'WordPress renders its native Media SVG when supported and retains the legacy Dashicon fallback'
        );
        $verify(
            str_contains($html, "title='" . esc_attr($statusText) . "'")
            && str_contains($html, "aria-label='" . esc_attr($label) . "'"),
            'WordPress renders the status tooltip without a dot and keeps the detailed accessible dropdown label'
        );
        wp_enqueue_style('admin-bar');
        $previousStyles = wp_styles()->get_data('admin-bar', 'after') ?: [];
        do_action('admin_bar_init');
        $iconStyles = wp_styles()->get_data('admin-bar', 'after');
        $css = end($iconStyles);
        $verify(
            count($iconStyles) === count($previousStyles) + 1
            && str_contains($css, '#wp-admin-bar-ask-the-origin .ab-icon:first-child')
            && !preg_match('/\.ab-icon\s*\{\s*display:\s*flex/', $css)
            && str_contains($css, '.ask-the-origin-status-dot.is-enabled')
            && !preg_match(
                '/\b(?:color|background(?:-color)?)\s*:/i',
                strstr($css, '#wpadminbar #wp-admin-bar-ask-the-origin-status', true)
            ),
            'only the dropdown indicator is colored; the toolbar icon keeps its native colors'
        );
    }
    add_filter('ask_the_origin_options', $forced, PHP_INT_MAX);
    $lockedRow = $toolbar()->get_node('ask-the-origin-status');
    $verify(
        empty($lockedRow->href) && empty($lockedRow->meta['html'])
        && str_contains($lockedRow->title, 'configuration code'),
        'a forced enabled filter makes the status read-only instead of offering a broken toggle'
    );
    remove_filter('ask_the_origin_options', $forced, PHP_INT_MAX);
    add_filter('ask_the_origin_options', $urlOnly, PHP_INT_MAX);
    $verify(
        !empty($toolbar()->get_node('ask-the-origin-status')->href),
        'a filter that only controls the source URL does not lock the status toggle'
    );
    remove_filter('ask_the_origin_options', $urlOnly, PHP_INT_MAX);
    $enabled = true;
    $verify($status() !== null, 'the icon remains available after the effective setting is re-enabled');
    $user = wp_get_current_user();
    $user->add_cap('manage_options', false);
    $verify($status() === null, 'an administrator denied manage_options does not receive the status');
    do_action('admin_bar_init');
    $verify(
        wp_styles()->get_data('admin-bar', 'after') === $iconStyles,
        'users without manage_options add no icon styles'
    );
    $user->remove_cap('manage_options');
    $verify($status() !== null, 'restoring manage_options restores the administrator status');
    remove_filter('pre_option_' . OptionsMedia::OPTION_NAME, $optionsFilter, PHP_INT_MAX);
    update_option(OptionsMedia::OPTION_NAME, $seed);
    $body = [
        'site_id' => (string) get_current_blog_id(),
        'enabled' => '1',
        '_wpnonce' => wp_create_nonce('ask_the_origin_toggle_' . get_current_blog_id()),
        '_wp_http_referer' => admin_url('options-media.php'),
    ];
    $valid = ['user' => $userIds['administrator'], 'method' => 'POST', 'body' => $body];
    $invalid = [
        'GET requests' => [array_replace($valid, ['method' => 'GET']), 405],
        'unauthorized users' => [array_replace($valid, ['user' => $userIds['author']]), 403],
        'anonymous users' => [array_replace($valid, ['user' => 0]), 403],
        'missing nonces' => [array_replace($valid, ['body' => array_diff_key($body, ['_wpnonce' => true])]), 403],
        'invalid nonces' => [array_replace($valid, ['body' => array_replace($body, ['_wpnonce' => 'invalid'])]), 403],
        'invalid states' => [array_replace($valid, ['body' => array_replace($body, ['enabled' => 'yes'])]), 400],
        'another site' => [array_replace($valid, ['body' => array_replace($body, ['site_id' => '999999'])]), 400],
        'constant-controlled status' => [array_replace($valid, ['config' => 'constant']), 409],
        'filter-controlled status' => [array_replace($valid, ['config' => 'forced']), 409],
        'prevented saves' => [array_replace($valid, ['config' => 'blocked-save']), 409],
        'altered saves' => [array_replace($valid, ['config' => 'altered-save']), 409],
    ];
    foreach ($invalid as $description => [$payload, $response]) {
        $report = $request($payload);
        $verify(
            $report['response'] === $response && $readSaved() === $seed,
            $description . ' cannot change saved settings'
        );
    }
    $report = $request($valid);
    $enabledOptions = array_replace($seed, ['enabled' => true]);
    $verify(
        $report['response'] === 302 && $readSaved() === $enabledOptions,
        'an authorized POST enables the proxy without changing credentials or other fields'
    );
    $report = $request($valid);
    $verify(
        $report['response'] === 302 && $readSaved() === $enabledOptions,
        'repeating an explicit Enable request does not flip the state back'
    );
    $report = $request(array_replace($valid, ['body' => array_replace($body, ['enabled' => '0'])]));
    $verify(
        $report['response'] === 302 && $readSaved() === $seed,
        'an authorized POST disables the proxy and retains all other settings'
    );
    $report = $request(array_replace($valid, ['config' => 'url-only']));
    $verify(
        $report['response'] === 302 && $readSaved() === $enabledOptions,
        'source-URL configuration does not prevent a status-only update'
    );
    $verify(
        $report['location'] === admin_url('options-media.php'),
        'successful requests redirect safely back to Media settings'
    );
} finally {
    remove_filter('pre_option_' . OptionsMedia::OPTION_NAME, $optionsFilter, PHP_INT_MAX);
    remove_filter('ask_the_origin_options', $forced, PHP_INT_MAX);
    remove_filter('ask_the_origin_options', $urlOnly, PHP_INT_MAX);
    if ($originalOptions === null) {
        delete_option(OptionsMedia::OPTION_NAME);
    } else {
        update_option(OptionsMedia::OPTION_NAME, $originalOptions);
    }
    if ($sanitizePriority !== false) {
        add_filter($sanitizeHook, $sanitize, $sanitizePriority);
    }
    wp_set_current_user($originalUserId);
    $cleanupFailed = false;
    foreach ($userIds as $userId) {
        $removed = is_multisite() ? wpmu_delete_user($userId) : wp_delete_user($userId);
        $cleanupFailed = !$removed || $cleanupFailed;
    }
    if ($cleanupFailed) {
        throw new RuntimeException('Could not delete every temporary toolbar test user.');
    }
}
WP_CLI::success('Toolbar menu and toggle checks passed (' . $checks . ' checks).');
