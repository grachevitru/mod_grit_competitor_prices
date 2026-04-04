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

        $isProductsController = in_array($controller, ['product', 'products'], true);
        $isProductView = ($view === 'product' || $view === 'products');
        $isEditTask = in_array($task, ['edit', 'apply', 'save'], true) || str_contains($task, 'product');
        $isProductEdit = (($isProductsController || $isProductView) && $isEditTask && $productId > 0);

        if ($debugLogging) {
            Log::add('Detected com_jshopping page. controller=' . $controller . ', view=' . $view . ', task=' . $task . ', productId=' . $productId . ', isProductEdit=' . (int) $isProductEdit, Log::INFO, 'plg_system_grit_competitor_tab');
        }

        if (!$isProductEdit) {
            return;
        }

        $body = $app->getBody();

        if (strpos($body, 'grit-competitor-tab-link') !== false) {
            return;
        }

        $tabLinkHtml = '<li class="nav-item">'
            . '<a class="nav-link" id="grit-competitor-tab-link" data-toggle="tab" data-bs-toggle="tab" href="#grit-competitor-tab-pane">Цены конкурентов</a>'
            . '</li>';

        $tabsInjected = false;

        $body = preg_replace_callback(
            '~<ul[^>]*class="[^"]*nav-tabs[^"]*"[^>]*>(.*?)</ul>~is',
            static function ($matches) use ($tabLinkHtml, &$tabsInjected) {
                $tabsInjected = true;
                return str_replace('</ul>', $tabLinkHtml . '</ul>', $matches[0]);
            },
            $body,
            1
        );

        $ajaxUrl = 'index.php?option=com_ajax&plugin=grit_competitor_tab&format=json';
        $paneHtml = '<div class="alert alert-info" style="margin-top:10px;">Добавление цены конкурента для товара ID: ' . (int) $productId . '</div>'
            . '<form id="grit-competitor-form" style="max-width:820px;">'
            . '<input type="hidden" name="product_id" value="' . (int) $productId . '">'
            . '<div class="control-group"><label>Название конкурента</label><input class="form-control" type="text" name="competitor_name" required></div>'
            . '<div class="control-group"><label>URL конкурента</label><input class="form-control" type="url" name="url"></div>'
            . '<div class="control-group"><label>Селектор цены</label><input class="form-control" type="text" name="selector"></div>'
            . '<div class="control-group"><label>Цена</label><input class="form-control" type="text" name="price"></div>'
            . '<div class="control-group" style="margin-top:10px;"><button class="btn btn-success" type="submit">Сохранить</button></div>'
            . '<div id="grit-competitor-result" style="margin-top:10px;"></div>'
            . '</form>'
            . '<div id="grit-competitor-list" style="margin-top:15px;"></div>';

        $script = '<script>(function(){'
            . 'var pid=' . (int) $productId . ';'
            . 'var ajaxUrl=' . json_encode($ajaxUrl) . ';'
            . 'var paneHtml=' . json_encode($paneHtml) . ';'
            . 'function esc(v){return String(v||"").replace(/[&<>\"\']/g,function(s){return ({"&":"&amp;","<":"&lt;",">":"&gt;","\"":"&quot;","\'":"&#039;"})[s];});}'
            . 'function render(items){var el=document.getElementById("grit-competitor-list");if(!el){return;} if(!items||!items.length){el.innerHTML="<div class=\"alert alert-light\">Записей пока нет</div>";return;} var h="<table class=\"table table-sm\"><thead><tr><th>ID</th><th>Конкурент</th><th>URL</th><th>Селектор</th><th>Цена</th><th>Обновлено</th></tr></thead><tbody>";items.forEach(function(it){h+="<tr><td>"+esc(it.id)+"</td><td>"+esc(it.competitor_name)+"</td><td>"+esc(it.url)+"</td><td>"+esc(it.selector)+"</td><td>"+esc(it.price)+"</td><td>"+esc(it.last_update)+"</td></tr>";});h+="</tbody></table>";el.innerHTML=h;}'
            . 'function loadList(){fetch(ajaxUrl+"&action=list&product_id="+pid,{credentials:"same-origin"}).then(function(r){return r.json();}).then(function(d){if(d&&d.success&&d.data&&d.data.items){render(d.data.items);}else{render([]);}}).catch(function(){render([]);});}'
            . 'function init(){var tc=document.querySelector(".tab-content");if(!tc){return;} if(!document.getElementById("grit-competitor-tab-pane")){var p=document.createElement("div");p.className="tab-pane";p.id="grit-competitor-tab-pane";p.innerHTML=paneHtml;tc.appendChild(p);} var f=document.getElementById("grit-competitor-form");if(f&&!f.dataset.binded){f.dataset.binded="1";f.addEventListener("submit",function(e){e.preventDefault();var fd=new FormData(f);fd.append("action","save");fetch(ajaxUrl,{method:"POST",body:fd,credentials:"same-origin"}).then(function(r){return r.json();}).then(function(d){var r=document.getElementById("grit-competitor-result");if(d&&d.success){r.innerHTML="<span style=\\"color:green\\">Сохранено</span>";f.reset();f.querySelector("input[name=product_id]").value=pid;loadList();}else{r.innerHTML="<span style=\\"color:#a00\\">Ошибка сохранения</span>";}}).catch(function(){var r=document.getElementById("grit-competitor-result");r.innerHTML="<span style=\\"color:#a00\\">Ошибка запроса</span>";});});} loadList();}'
            . 'if(document.readyState==="loading"){document.addEventListener("DOMContentLoaded",init);}else{init();}'
            . '})();</script>';

        if (stripos($body, '</body>') !== false) {
            $body = preg_replace('~</body>~i', $script . '</body>', $body, 1);
        } else {
            $body .= $script;
        }

        $app->setBody($body);

        if ($debugLogging) {
            Log::add('Injected competitor editable tab. tabsInjected=' . (int) $tabsInjected . ', productId=' . $productId, Log::INFO, 'plg_system_grit_competitor_tab');
        }
    }

    public function onAjaxGrit_competitor_tab()
    {
        $input = Factory::getApplication()->input;
        $db = Factory::getDbo();

        $action = $input->getCmd('action', 'save');
        $productId = $input->getInt('product_id');

        if ($action === 'list') {
            if ($productId <= 0) {
                return ['items' => []];
            }

            $columnsInfo = $db->getTableColumns('#__competitor_prices', false);
            $select = ['id', 'product_id', 'competitor_name'];
            $select[] = isset($columnsInfo['url']) ? 'url' : (isset($columnsInfo['competitor_url']) ? 'competitor_url AS url' : "'' AS url");
            $select[] = isset($columnsInfo['selector']) ? 'selector' : "'' AS selector";
            $select[] = isset($columnsInfo['price']) ? 'price' : "'' AS price";
            $select[] = isset($columnsInfo['last_update']) ? 'last_update' : "'' AS last_update";

            $query = $db->getQuery(true)
                ->select($select)
                ->from($db->quoteName('#__competitor_prices'))
                ->where($db->quoteName('product_id') . ' = ' . (int) $productId)
                ->order($db->quoteName('id') . ' DESC');

            $db->setQuery($query);
            return ['items' => $db->loadAssocList() ?: []];
        }

        $competitorName = trim($input->getString('competitor_name'));
        $url = trim($input->getString('url'));
        $selector = trim($input->getString('selector'));
        $price = trim($input->getString('price'));

        if ($productId <= 0 || $competitorName === '') {
            throw new RuntimeException('Invalid data');
        }

        $columnsInfo = $db->getTableColumns('#__competitor_prices', false);
        $columns = ['product_id', 'competitor_name'];
        $values = [$productId, $competitorName];

        if (isset($columnsInfo['url'])) {
            $columns[] = 'url';
            $values[] = $url;
        } elseif (isset($columnsInfo['competitor_url'])) {
            $columns[] = 'competitor_url';
            $values[] = $url;
        }

        if (isset($columnsInfo['selector'])) {
            $columns[] = 'selector';
            $values[] = $selector;
        }

        if (isset($columnsInfo['price'])) {
            $columns[] = 'price';
            $values[] = $price;
        }

        if (isset($columnsInfo['last_update'])) {
            $columns[] = 'last_update';
            $values[] = date('Y-m-d H:i:s');
        }

        $query = $db->getQuery(true)
            ->insert($db->quoteName('#__competitor_prices'))
            ->columns(array_map([$db, 'quoteName'], $columns))
            ->values(implode(',', array_map([$db, 'quote'], $values)));

        $db->setQuery($query);
        $db->execute();

        return ['saved' => true];
    }
}
