<?php

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\CMSPlugin;

final class PlgSystemGritCompetitorTab extends CMSPlugin
{
    public function onAfterRender(): void
    {
        $app = Factory::getApplication();

        if (!$app->isClient('administrator')) {
            return;
        }

        $input = $app->input;
        $option = $input->getCmd('option');
        $controller = $input->getCmd('controller');
        $view = $input->getCmd('view');
        $task = $input->getCmd('task');
        $productId = $input->getInt('product_id', $input->getInt('id'));

        if ($option !== 'com_jshopping') {
            return;
        }

        $isProductEdit = ($controller === 'product' || $view === 'product' || str_contains($task, 'product'));

        if (!$isProductEdit || !$productId) {
            return;
        }

        $body = $app->getBody();

        $componentLink = 'index.php?option=com_grit_competitor_prices&view=prices&filter_search=' . (int) $productId;
        $addLink = 'index.php?option=com_grit_competitor_prices&task=price.add&product_id=' . (int) $productId;

        $html = '<script>(function(){\n'
            . 'var productId=' . (int) $productId . ';\n'
            . 'var nav=document.querySelector("ul.nav-tabs, .nav-tabs");\n'
            . 'var tabContent=document.querySelector(".tab-content");\n'
            . 'if(!nav||!tabContent){return;}\n'
            . 'if(document.getElementById("grit-competitor-tab-link")){return;}\n'
            . 'var li=document.createElement("li"); li.className="nav-item";\n'
            . 'var a=document.createElement("a"); a.className="nav-link"; a.id="grit-competitor-tab-link"; a.setAttribute("data-bs-toggle","tab"); a.href="#grit-competitor-tab-pane"; a.innerText="Цены конкурентов";\n'
            . 'li.appendChild(a); nav.appendChild(li);\n'
            . 'var pane=document.createElement("div"); pane.className="tab-pane fade p-3"; pane.id="grit-competitor-tab-pane";\n'
            . 'pane.innerHTML=' . json_encode('<div class="alert alert-info">Управление ценами конкурентов для товара ID: ' . (int) $productId . '</div><p><a class="btn btn-primary" target="_blank" href="' . $componentLink . '">Открыть список цен</a> <a class="btn btn-success" target="_blank" href="' . $addLink . '">Добавить цену конкурента</a></p><iframe src="' . $componentLink . '" style="width:100%;height:700px;border:1px solid #ddd;border-radius:6px;"></iframe>') . ';\n'
            . 'tabContent.appendChild(pane);\n'
            . '})();</script>';

        $app->setBody(str_replace('</body>', $html . '</body>', $body));
    }
}
