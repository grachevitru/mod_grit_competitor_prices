<?php

namespace Grit\Component\CompetitorPrices\Administrator\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\MVC\Controller\AdminController;

class PricesController extends AdminController
{
    public function getModel($name = 'Price', $prefix = 'Administrator', $config = ['ignore_request' => true])
    {
        return parent::getModel($name, $prefix, $config);
    }
}
