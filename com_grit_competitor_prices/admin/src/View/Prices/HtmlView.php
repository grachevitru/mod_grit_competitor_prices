<?php

namespace Grit\Component\CompetitorPrices\Administrator\View\Prices;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;

class HtmlView extends BaseHtmlView
{
    protected $items;
    protected $pagination;
    protected $state;

    public function display($tpl = null)
    {
        $this->items = $this->get('Items');
        $this->pagination = $this->get('Pagination');
        $this->state = $this->get('State');

        if (count($errors = $this->get('Errors'))) {
            throw new \RuntimeException(implode("\n", $errors), 500);
        }

        $this->addToolbar();

        parent::display($tpl);
    }

    protected function addToolbar(): void
    {
        $user = Factory::getApplication()->getIdentity();

        ToolbarHelper::title(Text::_('COM_GRIT_COMPETITOR_PRICES_MANAGER_PRICES'), 'list');

        if ($user->authorise('core.create', 'com_grit_competitor_prices')) {
            ToolbarHelper::addNew('price.add');
        }

        if ($user->authorise('core.edit', 'com_grit_competitor_prices')) {
            ToolbarHelper::editList('price.edit');
        }

        if ($user->authorise('core.delete', 'com_grit_competitor_prices')) {
            ToolbarHelper::deleteList('', 'prices.delete');
        }

        ToolbarHelper::preferences('com_grit_competitor_prices');
    }
}
