<?php

namespace Grit\Component\CompetitorPrices\Administrator\Model;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\AdminModel;

class PriceModel extends AdminModel
{
    public function getTable($type = 'Price', $prefix = 'Administrator', $config = [])
    {
        return parent::getTable($type, $prefix, $config);
    }

    public function getForm($data = [], $loadData = true)
    {
        $form = $this->loadForm(
            'com_grit_competitor_prices.price',
            'price',
            ['control' => 'jform', 'load_data' => $loadData]
        );

        if (empty($form)) {
            return false;
        }

        return $form;
    }

    protected function loadFormData()
    {
        $data = $this->getItem();

        if (!isset($data->last_update) || empty($data->last_update)) {
            $data->last_update = date('Y-m-d H:i:s');
        }

        $input = Factory::getApplication()->input;
        $prefillProductId = $input->getInt('product_id');

        if (empty($data->product_id) && $prefillProductId > 0) {
            $data->product_id = $prefillProductId;
        }

        return $data;
    }
}
