<?php

namespace Grit\Component\CompetitorPrices\Administrator\Table;

defined('_JEXEC') or die;

use Joomla\CMS\Table\Table;
use Joomla\Database\DatabaseDriver;

class PriceTable extends Table
{
    public function __construct(DatabaseDriver $db)
    {
        parent::__construct('#__competitor_prices', 'id', $db);
    }
}
