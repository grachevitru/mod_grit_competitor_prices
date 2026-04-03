<?php

namespace Grit\Component\CompetitorPrices\Administrator\Model;

defined('_JEXEC') or die;

use Joomla\CMS\MVC\Model\ListModel;

class PricesModel extends ListModel
{
    protected function getListQuery()
    {
        $db = $this->getDatabase();
        $query = $db->getQuery(true)
            ->select('cp.*')
            ->from($db->quoteName('#__competitor_prices', 'cp'));

        $search = $this->getState('filter.search');

        if (!empty($search)) {
            if (stripos($search, 'id:') === 0) {
                $id = (int) substr($search, 3);
                $query->where('cp.id = ' . $id);
            } else {
                $search = $db->quote('%' . $db->escape($search, true) . '%', false);
                $query->where('(cp.competitor_name LIKE ' . $search . ' OR cp.product_id LIKE ' . $search . ')');
            }
        }

        $orderCol = $this->state->get('list.ordering', 'cp.last_update');
        $orderDirn = $this->state->get('list.direction', 'DESC');
        $query->order($db->escape($orderCol . ' ' . $orderDirn));

        return $query;
    }

    protected function populateState($ordering = 'cp.last_update', $direction = 'DESC')
    {
        parent::populateState($ordering, $direction);
    }

    protected function getStoreId($id = '')
    {
        $id .= ':' . $this->getState('filter.search');

        return parent::getStoreId($id);
    }
}
