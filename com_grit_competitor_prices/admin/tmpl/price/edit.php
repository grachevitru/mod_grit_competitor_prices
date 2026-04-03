<?php

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;

HTMLHelper::_('behavior.formvalidator');
HTMLHelper::_('behavior.keepalive');
?>
<form action="index.php?option=com_grit_competitor_prices&amp;layout=edit&amp;id=<?php echo (int) ($this->item->id ?? 0); ?>"
      method="post" name="adminForm" id="price-form" class="form-validate">
    <div class="row">
        <div class="col-lg-8">
            <?php echo $this->form->renderField('product_id'); ?>
            <?php echo $this->form->renderField('competitor_name'); ?>
            <?php echo $this->form->renderField('price'); ?>
            <?php echo $this->form->renderField('last_update'); ?>
        </div>
    </div>

    <input type="hidden" name="task" value="" />
    <?php echo HTMLHelper::_('form.token'); ?>
</form>
