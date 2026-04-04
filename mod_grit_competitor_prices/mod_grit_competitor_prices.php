<?php
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Log\Log;
use Joomla\Database\ParameterType;

$app = Factory::getApplication();
$input = $app->input;

$option = $input->getCmd('option');
$view = $input->getCmd('view');
$controller = $input->getCmd('controller');
$task = $input->getCmd('task');
$product_id = $input->getInt('product_id', $input->getInt('id'));

$tableName = $params->get('source_table', '#__competitor_prices');
$productIdField = $params->get('source_product_id_field', 'product_id');
$competitorNameField = $params->get('source_name_field', 'competitor_name');
$priceField = $params->get('source_price_field', 'price');
$lastUpdateField = $params->get('source_last_update_field', 'last_update');

$debugMode = (bool) $params->get('debug_mode', 0);

if ($debugMode) {
    Log::addLogger(
        [
            'text_file' => 'mod_grit_competitor_prices.php',
            'text_file_path' => 'logs',
        ],
        Log::ALL,
        ['mod_grit_competitor_prices']
    );

    Log::add(
        'Start module execution. table=' . $tableName . ', productIdField=' . $productIdField . ', product_id=' . $product_id,
        Log::INFO,
        'mod_grit_competitor_prices'
    );
}

$isJshopping = ($option === 'com_jshopping');
$isProductPage = ($controller === 'product' || $view === 'product' || str_starts_with($task, 'product'));

if (!$isJshopping || !$isProductPage || !$product_id) {
    if ($debugMode) {
        Log::add('Skip: not product page or product_id missing. option=' . $option . ', view=' . $view . ', controller=' . $controller . ', task=' . $task . ', product_id=' . $product_id, Log::WARNING, 'mod_grit_competitor_prices');
    }

    return;
}

$db = Factory::getDbo();

$queryProd = $db->getQuery(true)
    ->select($db->quoteName('product_price'))
    ->from($db->quoteName('#__jshopping_products'))
    ->where($db->quoteName('product_id') . ' = :id')
    ->bind(':id', $product_id, ParameterType::INTEGER);

$db->setQuery($queryProd);

try {
    $myPrice = (float) $db->loadResult();
} catch (RuntimeException $e) {
    if ($debugMode) {
        Log::add('Error loading product price: ' . $e->getMessage(), Log::ERROR, 'mod_grit_competitor_prices');
    }

    return;
}

$query = $db->getQuery(true)
    ->select($db->quoteName($competitorNameField, 'competitor_name'))
    ->select($db->quoteName($priceField, 'price'))
    ->select($db->quoteName($lastUpdateField, 'last_update'))
    ->from($db->quoteName($tableName))
    ->where($db->quoteName($productIdField) . ' = :id')
    ->bind(':id', $product_id, ParameterType::INTEGER);

$db->setQuery($query);

try {
    $analogs = $db->loadObjectList();
} catch (RuntimeException $e) {
    if ($debugMode) {
        Log::add('Error loading competitor rows: ' . $e->getMessage(), Log::ERROR, 'mod_grit_competitor_prices');
    }

    return;
}

if (empty($analogs)) {
    if ($debugMode) {
        Log::add('No competitor rows found. table=' . $tableName . ', productIdField=' . $productIdField . ', product_id=' . $product_id, Log::INFO, 'mod_grit_competitor_prices');
    }

    return;
}

$hideLowerThanMyPrice = (bool) $params->get('hide_lower_than_my_price', 0);

foreach ($analogs as $key => $item) {
    if (!isset($item->price)) {
        unset($analogs[$key]);
        continue;
    }

    $competitorPrice = (float) $item->price;

    if ($competitorPrice <= 0) {
        unset($analogs[$key]);
        continue;
    }

    if ($hideLowerThanMyPrice && $competitorPrice < $myPrice) {
        unset($analogs[$key]);
        continue;
    }

    $item->price = $competitorPrice;
}

if (empty($analogs)) {
    if ($debugMode) {
        Log::add('All competitor rows were filtered out. myPrice=' . $myPrice, Log::INFO, 'mod_grit_competitor_prices');
    }

    return;
}

if ($debugMode) {
    Log::add('Render module with rows=' . count($analogs) . ', myPrice=' . $myPrice . ', hideLowerThanMyPrice=' . (int) $hideLowerThanMyPrice, Log::INFO, 'mod_grit_competitor_prices');
}

require JModuleHelper::getLayoutPath($module->module, $params->get('layout', 'default'));
