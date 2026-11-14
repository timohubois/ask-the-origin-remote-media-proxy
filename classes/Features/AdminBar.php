<?php

namespace AskTheOrigin\Features;

use WP_Admin_Bar;

defined('ABSPATH') || exit;

/** Show a native Media toolbar menu with a site-scoped proxy status and toggle. */
final class AdminBar
{
    /** @var string Authenticated POST action for changing this site's saved status. */
    private const string TOGGLE_ACTION = 'ask_the_origin_toggle';

    /** Register the menu, native toolbar assets and authenticated status action. */
    public function __construct()
    {
        add_action('admin_bar_init', [$this, 'addAssets']);
        add_action('admin_bar_menu', [$this, 'addStatus'], 100);
        add_action('admin_post_' . self::TOGGLE_ACTION, [$this, 'handleToggle']);
    }

    /**
     * Keep the toolbar icon native and add color only to the dropdown's status indicator.
     *
     * @return void
     */
    public function addAssets(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        wp_add_inline_style('admin-bar', '
            #wpadminbar #wp-toolbar #wp-admin-bar-ask-the-origin > .ab-item,
            #wpadminbar #wp-toolbar #wp-admin-bar-ask-the-origin .ab-icon:first-child {
                display: flex;
                align-items: center;
                justify-content: center;
            }
            #wpadminbar #wp-toolbar #wp-admin-bar-ask-the-origin .ab-icon:first-child {
                float: none;
                margin: 0;
                padding: 0;
            }
            #wpadminbar #wp-admin-bar-ask-the-origin-status > .ab-item {
                display: flex;
                align-items: center;
                gap: 6px;
            }
            #wpadminbar #wp-admin-bar-ask-the-origin-status .ask-the-origin-status-dot {
                display: block;
                flex: 0 0 6px;
                width: 6px;
                height: 6px;
                border-radius: 50%;
                background-color: #a7aaad;
            }
            #wpadminbar #wp-admin-bar-ask-the-origin-status .ask-the-origin-status-dot.is-enabled {
                background-color: #46b450;
            }
            @media screen and (max-width: 782px) {
                #wpadminbar li#wp-admin-bar-ask-the-origin {
                    display: block;
                }
            }
        ');
        // Keep a native link as the no-JavaScript fallback; normal clicks submit the hidden POST form.
        wp_add_inline_script('admin-bar', '
            document.addEventListener("click", function (event) {
                const link = event.target instanceof Element
                    ? event.target.closest("#wp-admin-bar-ask-the-origin-status > a") : null;
                const form = document.getElementById("ask-the-origin-toggle");
                if (!link || !form || event.button !== 0
                    || event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) {
                    return;
                }
                event.preventDefault();
                form.submit();
            });
        ');
    }

    /**
     * Show the icon in both states, identifying the current site rather than the network as a whole.
     *
     * @param WP_Admin_Bar $adminBar WordPress toolbar receiving the menu.
     * @return void
     */
    public function addStatus(WP_Admin_Bar $adminBar): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }
        $optionsMedia = OptionsMedia::getInstance();
        $enabled = $optionsMedia->getOptions()['enabled'];
        $canToggle = $optionsMedia->canToggleEnabled();
        $siteName = get_bloginfo('name');
        $label = sprintf(
            /* translators: %s: Name of the current WordPress site. */
            $enabled ? __('Ask the Origin is enabled on %s.', 'ask-the-origin-remote-media-proxy')
                /* translators: %s: Name of the current WordPress site. */
                : __('Ask the Origin is disabled on %s.', 'ask-the-origin-remote-media-proxy'),
            $siteName !== '' ? $siteName : home_url()
        );
        $status = $enabled ? __('Ask the Origin: Enabled', 'ask-the-origin-remote-media-proxy')
            : __('Ask the Origin: Disabled', 'ask-the-origin-remote-media-proxy');
        $settingsUrl = admin_url('options-media.php#ask_the_origin_enabled');
        $adminBar->add_node([
            'id' => 'ask-the-origin',
            // WordPress 7.2+ renders the SVG and hides the empty Dashicon; older versions keep the fallback.
            'icon' => 'core-admin/media',
            'title' => '<span class="ab-icon dashicons-admin-media" aria-hidden="true"></span>'
                . '<span class="screen-reader-text">' . esc_html($label) . '</span>',
            'href' => esc_url($settingsUrl),
            'meta' => [
                'title' => $status,
                'menu_title' => $label,
            ],
        ]);
        $actionLabel = $canToggle ? sprintf(
            /* translators: %s: Name of the current WordPress site. */
            $enabled ? __('Disable Ask the Origin on %s.', 'ask-the-origin-remote-media-proxy')
                /* translators: %s: Name of the current WordPress site. */
                : __('Enable Ask the Origin on %s.', 'ask-the-origin-remote-media-proxy'),
            $siteName !== '' ? $siteName : home_url()
        ) : __('This status is controlled by configuration code.', 'ask-the-origin-remote-media-proxy');
        $adminBar->add_node([
            'id' => 'ask-the-origin-status',
            'parent' => 'ask-the-origin',
            'title' => '<span class="ask-the-origin-status-dot '
                . ($enabled ? 'is-enabled' : 'is-disabled') . '" aria-hidden="true"></span>' . esc_html($status)
                . '<span class="screen-reader-text"> — ' . esc_html($actionLabel) . '</span>',
            'href' => $canToggle ? esc_url($settingsUrl) : false,
            'meta' => [
                'title' => $status,
                'html' => $canToggle ? $this->toggleForm(!$enabled) : '',
            ],
        ]);
        $adminBar->add_node([
            'id' => 'ask-the-origin-settings',
            'parent' => 'ask-the-origin',
            'title' => esc_html__('Settings', 'ask-the-origin-remote-media-proxy'),
            'href' => esc_url($settingsUrl),
        ]);
    }

    /**
     * Render a hidden, site-bound POST form without nesting interactive elements inside the menu link.
     *
     * @param boolean $enabled Explicit desired state, making repeat submissions idempotent.
     * @return string Escaped form markup appended after the status menu item.
     */
    private function toggleForm(bool $enabled): string
    {
        return '<form id="ask-the-origin-toggle" method="post" action="' . esc_url(admin_url('admin-post.php'))
            . '" hidden><input type="hidden" name="action" value="' . esc_attr(self::TOGGLE_ACTION) . '">'
            . '<input type="hidden" name="site_id" value="' . esc_attr((string) get_current_blog_id()) . '">'
            . '<input type="hidden" name="enabled" value="' . esc_attr($enabled ? '1' : '0') . '">'
            . wp_nonce_field(self::TOGGLE_ACTION . '_' . get_current_blog_id(), 'ask_the_origin_nonce', true, false)
            . '</form>';
    }

    /**
     * Change only the current site's saved status after checking method, capability, nonce and payload.
     *
     * @return void
     */
    public function handleToggle(): void
    {
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- Exact method check without coercion or output.
        if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
            wp_die(esc_html__('Use POST to change the proxy status.', 'ask-the-origin-remote-media-proxy'), '', [
                'response' => 405,
            ]);
        }
        if (!current_user_can('manage_options')) {
            wp_die(esc_html__('You cannot manage this site\'s settings.', 'ask-the-origin-remote-media-proxy'), '', [
                'response' => 403,
            ]);
        }
        check_admin_referer(self::TOGGLE_ACTION . '_' . get_current_blog_id(), 'ask_the_origin_nonce');
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Exact allowlist below.
        $enabled = isset($_POST['enabled']) && is_string($_POST['enabled']) ? wp_unslash($_POST['enabled']) : null;
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- Exact current-site match below.
        $siteId = isset($_POST['site_id']) && is_string($_POST['site_id']) ? wp_unslash($_POST['site_id']) : null;
        if (!in_array($enabled, ['0', '1'], true) || $siteId !== (string) get_current_blog_id()) {
            wp_die(esc_html__('Invalid proxy status request.', 'ask-the-origin-remote-media-proxy'), '', [
                'response' => 400,
            ]);
        }
        if (!OptionsMedia::getInstance()->setEnabled($enabled === '1')) {
            wp_die(esc_html__('Could not change the proxy status.', 'ask-the-origin-remote-media-proxy'), '', [
                'response' => 409,
            ]);
        }
        wp_safe_redirect(wp_get_referer() ?: admin_url('options-media.php#ask_the_origin_enabled'));
        exit;
    }
}
