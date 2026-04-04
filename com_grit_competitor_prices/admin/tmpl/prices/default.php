<?php

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

HTMLHelper::_('bootstrap.tooltip');
HTMLHelper::_('behavior.multiselect');
?>
<form action="index.php?option=com_grit_competitor_prices&amp;view=prices" method="post" name="adminForm" id="adminForm">
    <div class="js-stools clearfix mb-3">
        <div class="clearfix">
            <input type="text" name="filter_search" id="filter_search" placeholder="<?php echo Text::_('JSEARCH_FILTER'); ?>"
                   value="<?php echo $this->escape($this->state->get('filter.search')); ?>" />
            <button type="submit" class="btn btn-primary btn-sm"><?php echo Text::_('JSEARCH_FILTER_SUBMIT'); ?></button>
            <button type="button" class="btn btn-secondary btn-sm" onclick="document.getElementById('filter_search').value='';this.form.submit();">
                <?php echo Text::_('JSEARCH_FILTER_CLEAR'); ?>
            </button>
        </div>
    </div>

    <table class="table table-striped" id="priceList">
        <thead>
            <tr>
                <th width="1%" class="text-center"><?php echo HTMLHelper::_('grid.checkall'); ?></th>
                <th><?php echo Text::_('COM_GRIT_COMPETITOR_PRICES_FIELD_PRODUCT_ID_LABEL'); ?></th>
                <th><?php echo Text::_('COM_GRIT_COMPETITOR_PRICES_FIELD_COMPETITOR_NAME_LABEL'); ?></th>
                <th><?php echo Text::_('COM_GRIT_COMPETITOR_PRICES_FIELD_COMPETITOR_URL_LABEL'); ?></th>
                <th><?php echo Text::_('COM_GRIT_COMPETITOR_PRICES_FIELD_PRICE_LABEL'); ?></th>
                <th><?php echo Text::_('COM_GRIT_COMPETITOR_PRICES_FIELD_LAST_UPDATE_LABEL'); ?></th>
                <th width="1%">ID</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($this->items as $i => $item) : ?>
                <tr>
                    <td class="text-center"><?php echo HTMLHelper::_('grid.id', $i, $item->id); ?></td>
                    <td><?php echo (int) $item->product_id; ?></td>
                    <td>
                        <a href="index.php?option=com_grit_competitor_prices&amp;task=price.edit&amp;id=<?php echo (int) $item->id; ?>">
                            <?php echo $this->escape($item->competitor_name); ?>
                        </a>
                    </td>
                    <td>
                        <?php if (!empty($item->competitor_url)) : ?>
                            <a href="<?php echo $this->escape($item->competitor_url); ?>" target="_blank" rel="noopener noreferrer">
                                <?php echo $this->escape($item->competitor_url); ?>
                            </a>
                        <?php endif; ?>
                    </td>
                    <td><?php echo $this->escape($item->price); ?></td>
                    <td><?php echo $this->escape($item->last_update); ?></td>
                    <td><?php echo (int) $item->id; ?></td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <?php echo $this->pagination->getListFooter(); ?>

    <input type="hidden" name="task" value="" />
    <?php echo HTMLHelper::_('form.token'); ?>
</form>
