<?php

/** Remove owned routing and settings, never uploads or attachment data. */

namespace AskTheOrigin;

use AskTheOrigin\Features\ApacheRouting;
use AskTheOrigin\Features\OptionsMedia;

defined('WP_UNINSTALL_PLUGIN') || exit;

require_once __DIR__ . '/classes/Features/OptionsMedia.php';
require_once __DIR__ . '/classes/Features/ApacheRouting.php';

if (is_multisite()) {
    $askTheOriginOffset = 0;
    do {
        $askTheOriginSites = get_sites(['fields' => 'ids', 'number' => 100, 'offset' => $askTheOriginOffset]);
        foreach ($askTheOriginSites as $askTheOriginSiteId) {
            switch_to_blog((int) $askTheOriginSiteId);
            try {
                ApacheRouting::removeOrFail();
                OptionsMedia::deleteOptions();
            } finally {
                restore_current_blog();
            }
        }
        $askTheOriginOffset += 100;
    } while (count($askTheOriginSites) === 100);
} else {
    ApacheRouting::removeOrFail();
    OptionsMedia::deleteOptions();
}
