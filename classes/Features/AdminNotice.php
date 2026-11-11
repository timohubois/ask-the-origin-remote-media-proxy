<?php

namespace AskTheOrigin\Features;

defined('ABSPATH') || exit;

/** Show the enabled proxy status on the Media settings screen. */
final class AdminNotice
{
    /** Register the administrator-facing status notice. */
    public function __construct()
    {
        add_action('admin_notices', [$this, 'notice']);
    }

    /**
     * Render the enabled status on Media settings without exposing credentials.
     *
     * @return void
     */
    public function notice(): void
    {
        if (!current_user_can('manage_options') || get_current_screen()?->id !== 'options-media') {
            return;
        }
        $optionsMedia = OptionsMedia::getInstance();
        $options = $optionsMedia->getOptions();
        if (!$options['enabled']) {
            return;
        }
        $message = esc_html__('Ask the Origin is enabled.', 'ask-the-origin-remote-media-proxy');
        if (is_string($options['url']) && $optionsMedia->isValidUrl($options['url'])) {
            $message .= ' ' . sprintf(
                /* translators: %s: Validated remote site base URL wrapped in a code element. */
                esc_html__('This site loads missing media from %s.', 'ask-the-origin-remote-media-proxy'),
                '<code>' . esc_html($options['url']) . '</code>'
            );
        }
        wp_admin_notice($message, [
            'type' => 'info',
            'dismissible' => false,
            'id' => 'ask-the-origin-enabled',
        ]);
    }
}
