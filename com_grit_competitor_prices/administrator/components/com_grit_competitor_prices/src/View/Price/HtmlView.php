<?php

namespace Grit\Component\CompetitorPrices\Administrator\View\Price;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Toolbar\ToolbarHelper;

class HtmlView extends BaseHtmlView
{
    protected $form;
    protected $item;

    public function display($tpl = null)
    {
        $this->form = $this->get('Form');
        $this->item = $this->get('Item');

        if (count($errors = $this->get('Errors'))) {
            throw new \RuntimeException(implode("\n", $errors), 500);
        }

        $this->addToolbar();

        parent::display($tpl);
    }

    protected function addToolbar(): void
    {
        Factory::getApplication()->input->set('hidemainmenu', true);

        $isNew = ((int) ($this->item->id ?? 0) === 0);

        ToolbarHelper::title(
            $isNew ? Text::_('COM_GRIT_COMPETITOR_PRICES_NEW_PRICE') : Text::_('COM_GRIT_COMPETITOR_PRICES_EDIT_PRICE'),
            'pencil-2'
        );

        ToolbarHelper::apply('price.apply');
        ToolbarHelper::save('price.save');
        ToolbarHelper::save2new('price.save2new');
        ToolbarHelper::cancel('price.cancel', $isNew ? 'JTOOLBAR_CANCEL' : 'JTOOLBAR_CLOSE');
    }
}
