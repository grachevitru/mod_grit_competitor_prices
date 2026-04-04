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

        $tabsInjected = false;
        $paneInjected = false;

        $dom = new DOMDocument('1.0', 'UTF-8');
        libxml_use_internal_errors(true);
        $loaded = $dom->loadHTML('<?xml encoding="utf-8" ?>' . $body, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        if ($loaded) {
            $xpath = new DOMXPath($dom);
            $tabList = $xpath->query("//ul[contains(concat(' ', normalize-space(@class), ' '), ' nav-tabs ')]")->item(0);
            $tabContent = $xpath->query("//div[contains(concat(' ', normalize-space(@class), ' '), ' tab-content ')]")->item(0);

            if ($tabList && !$xpath->query("//*[@id='grit-competitor-tab-link']")->length) {
                $li = $dom->createElement('li');
                $li->setAttribute('class', 'nav-item');

                $a = $dom->createElement('a', 'Цены конкурентов');
                $a->setAttribute('class', 'nav-link');
                $a->setAttribute('id', 'grit-competitor-tab-link');
                $a->setAttribute('data-toggle', 'tab');
                $a->setAttribute('data-bs-toggle', 'tab');
                $a->setAttribute('href', '#grit-competitor-tab-pane');

                $li->appendChild($a);
                $tabList->appendChild($li);
                $tabsInjected = true;
            }

            if ($tabContent && !$xpath->query("//*[@id='grit-competitor-tab-pane']")->length) {
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
                    . '<script>(function(){var f=document.getElementById("grit-competitor-form");if(!f){return;}f.addEventListener("submit",function(e){e.preventDefault();var fd=new FormData(f);fetch("' . $ajaxUrl . '",{method:"POST",body:fd,credentials:"same-origin"}).then(r=>r.json()).then(function(d){var el=document.getElementById("grit-competitor-result");if(d&&d.success){el.innerHTML="<span style=\"color:green\">Сохранено</span>";f.reset();f.querySelector("input[name=product_id]").value="' . (int) $productId . '";}else{el.innerHTML="<span style=\"color:#a00\">Ошибка сохранения</span>";}}).catch(function(){var el=document.getElementById("grit-competitor-result");el.innerHTML="<span style=\"color:#a00\">Ошибка запроса</span>";});});})();</script>';

                $pane = $dom->createElement('div');
                $pane->setAttribute('class', 'tab-pane');
                $pane->setAttribute('id', 'grit-competitor-tab-pane');

                $fragment = $dom->createDocumentFragment();
                $fragment->appendXML($paneHtml);
                $pane->appendChild($fragment);

                $tabContent->appendChild($pane);
                $paneInjected = true;
            }

            $body = $dom->saveHTML();
            $body = preg_replace('/^<\?xml[^>]+>\s*/', '', $body);
        }
        $app->setBody($body);

        if ($debugLogging) {
            Log::add('Injected competitor editable tab. tabsInjected=' . (int) $tabsInjected . ', paneInjected=' . (int) $paneInjected . ', productId=' . $productId, Log::INFO, 'plg_system_grit_competitor_tab');
        }
    }

    public function onAjaxGrit_competitor_tab()
    {
        $app = Factory::getApplication();
        $input = $app->input;
        $db = Factory::getDbo();

        $productId = $input->getInt('product_id');
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
