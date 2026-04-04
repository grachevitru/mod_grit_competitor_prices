<?php

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Plugin\CMSPlugin;

final class PlgSystemGrit_competitor_tab extends CMSPlugin
{
    public function onAfterRender(): void
    {
        $app = Factory::getApplication();

        $debugLogging = (bool) $this->params->get('debug_logging', 0);

        if ($debugLogging) {
            Log::addLogger(['text_file' => 'plg_system_grit_competitor_tab.php', 'text_file_path' => 'logs'], Log::ALL, ['plg_system_grit_competitor_tab']);
        }

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

        if ($debugLogging) {
            Log::add('Detected com_jshopping admin page. controller=' . $controller . ', view=' . $view . ', task=' . $task . ', productId=' . $productId, Log::INFO, 'plg_system_grit_competitor_tab');
        }

        $isProductEdit = ($controller === 'product' || $view === 'product' || str_contains($task, 'product'));

        if (!$isProductEdit || !$productId) {
            if ($debugLogging) {
                Log::add('Skip injection: not product edit page or missing product id', Log::INFO, 'plg_system_grit_competitor_tab');
            }
            return;
        }

        $body = $app->getBody();

        $componentLink = 'index.php?option=com_grit_competitor_prices&view=prices&filter_search=' . (int) $productId;
        $addLink = 'index.php?option=com_grit_competitor_prices&task=price.add&product_id=' . (int) $productId;

        $html = '<script>(function(){\n'
            . 'var productId=' . (int) $productId . ';\n'
            . 'var nav=document.querySelector("ul.nav-tabs, .nav-tabs");\n'
            . 'var tabContent=document.querySelector(".tab-content");\n'
            . 'var fallback=document.createElement("div"); fallback.className="alert alert-info mt-3"; fallback.id="grit-competitor-fallback";\n'
            . 'fallback.innerHTML=' . json_encode('<strong>Цены конкурентов</strong> (товар ID: ' . (int) $productId . ')<br><a class="btn btn-primary btn-sm mt-2" target="_blank" href="' . $componentLink . '">Открыть список цен</a> <a class="btn btn-success btn-sm mt-2" target="_blank" href="' . $addLink . '">Добавить цену конкурента</a>') . ';\n'
            . 'if(nav&&tabContent&&!document.getElementById("grit-competitor-tab-link")){\n'
            . ' var li=document.createElement("li"); li.className="nav-item";\n'
            . ' var a=document.createElement("a"); a.className="nav-link"; a.id="grit-competitor-tab-link"; a.setAttribute("data-bs-toggle","tab"); a.href="#grit-competitor-tab-pane"; a.innerText="Цены конкурентов";\n'
            . ' li.appendChild(a); nav.appendChild(li);\n'
            . ' var pane=document.createElement("div"); pane.className="tab-pane fade p-3"; pane.id="grit-competitor-tab-pane";\n'
            . ' pane.innerHTML=' . json_encode('<div class="alert alert-info">Управление ценами конкурентов для товара ID: ' . (int) $productId . '</div><p><a class="btn btn-primary" target="_blank" href="' . $componentLink . '">Открыть список цен</a> <a class="btn btn-success" target="_blank" href="' . $addLink . '">Добавить цену конкурента</a></p><iframe src="' . $componentLink . '" style="width:100%;height:700px;border:1px solid #ddd;border-radius:6px;"></iframe>') . ';\n'
            . ' tabContent.appendChild(pane);\n'
            . '} else {\n'
            . ' var target=document.querySelector("form#adminForm, .j-main-container, #content") || document.body;\n'
            . ' if(!document.getElementById("grit-competitor-fallback")){ target.appendChild(fallback); }\n'
            . '}\n'
            . '})();</script>';

        $app->setBody(str_replace('</body>', $html . '</body>', $body));
        if ($debugLogging) {
            Log::add('Injected competitor prices tab/fallback for productId=' . $productId, Log::INFO, 'plg_system_grit_competitor_tab');
        }
    }
}
