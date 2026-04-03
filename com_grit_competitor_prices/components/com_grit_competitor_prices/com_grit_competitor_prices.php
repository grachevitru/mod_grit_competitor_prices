<?php

defined('_JEXEC') or die;

use Joomla\CMS\Factory;

$app = Factory::getApplication();
$component = $app->bootComponent('com_grit_competitor_prices');
$component->getDispatcher($app)->dispatch();
