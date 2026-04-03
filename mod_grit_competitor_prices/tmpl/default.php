<?php
defined('_JEXEC') or die;
?>

<div class="product-analogs-module">
    <?php if ($module->showtitle) : ?>
        <h3 class="module-title"><?php echo $module->title; ?></h3>
    <?php else : ?>
        <h2 class="ttlanalog">Цены на аналоги</h2>
    <?php endif; ?>

    <ul>
        <?php foreach ($analogs as $row) : ?>
            <li>
                <span class="analog-name"><?php echo htmlspecialchars($row->competitor_name); ?></span>
                <span class="analog-price"><?php echo htmlspecialchars($row->price); ?> ₽</span>

                <?php if (!empty($row->last_update)) : ?>
                    <span class="analog-date" style="font-size: 13px;">
                        Дата <?php echo date('d.m.y', strtotime($row->last_update)); ?>
                    </span>
                <?php endif; ?>
            </li>
        <?php endforeach; ?>
    </ul>
</div>
