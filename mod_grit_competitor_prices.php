<?php
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\Database\ParameterType;

$app = Factory::getApplication();
$input = $app->input;

$option = $input->getCmd('option');
$view = $input->getCmd('view');
$controller = $input->getCmd('controller');
$product_id = $input->getInt('product_id');

$isJshopping = ($option === 'com_jshopping');
$isProductPage = ($controller === 'product' || $view === 'product');

if (!$isJshopping || !$isProductPage || !$product_id) {
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
    return;
}

$query = $db->getQuery(true)
    ->select('*')
    ->from($db->quoteName('#__competitor_prices'))
    ->where($db->quoteName('product_id') . ' = :id')
    ->bind(':id', $product_id, ParameterType::INTEGER);

$db->setQuery($query);

try {
    $analogs = $db->loadObjectList();
} catch (RuntimeException $e) {
    return;
}

if (empty($analogs)) {
    return;
}

$hideHigherThanMyPrice = (bool) $params->get('hide_higher_than_my_price', 0);

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

    if ($hideHigherThanMyPrice && $competitorPrice > $myPrice) {
        unset($analogs[$key]);
        continue;
    }

    $item->price = $competitorPrice;
}

if (empty($analogs)) {
    return;
}

require JModuleHelper::getLayoutPath($module->module, $params->get('layout', 'default'));
