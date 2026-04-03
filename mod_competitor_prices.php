<?php
/**
 * Backward-compatibility entrypoint for legacy module folder/name.
 * Allows existing installations of mod_competitor_prices to keep working.
 */

defined('_JEXEC') or die;

require __DIR__ . '/mod_grit_competitor_prices.php';
