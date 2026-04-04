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
        $componentLink = 'index.php?option=com_grit_competitor_prices&view=prices&filter_search=' . (int) $productId;
        $addLink = 'index.php?option=com_grit_competitor_prices&task=price.add&product_id=' . (int) $productId;

        $tabLinkHtml = '<li class="nav-item">'
            . '<a class="nav-link" id="grit-competitor-tab-link" data-toggle="tab" data-bs-toggle="tab" href="#grit-competitor-tab-pane">Цены конкурентов</a>'
            . '</li>';

        $tabPaneHtml = '<div class="tab-pane" id="grit-competitor-tab-pane">'
            . '<div class="alert alert-info" style="margin-top:10px;">Управление ценами конкурентов для товара ID: ' . (int) $productId . '</div>'
            . '<p>'
            . '<a class="btn btn-primary btn-sm" target="_blank" href="' . $componentLink . '">Открыть список цен</a> '
            . '<a class="btn btn-success btn-sm" target="_blank" href="' . $addLink . '">Добавить цену конкурента</a>'
            . '</p>'
            . '<iframe src="' . $componentLink . '" style="width:100%;height:700px;border:1px solid #ddd;border-radius:6px;"></iframe>'
            . '</div>';

        if (strpos($body, 'grit-competitor-tab-link') !== false) {
            return;
        }

        $tabsInjected = false;

        $body = preg_replace_callback(
            '~<ul[^>]*class="[^"]*nav-tabs[^"]*"[^>]*>(.*?)</ul>~is',
            static function ($matches) use ($tabLinkHtml, &$tabsInjected) {
                if (strpos($matches[0], 'grit-competitor-tab-link') !== false) {
                    return $matches[0];
                }

                $tabsInjected = true;

                return str_replace('</ul>', $tabLinkHtml . '</ul>', $matches[0]);
            },
            $body,
            1
        );

        if ($tabsInjected) {
            $body = preg_replace_callback(
                '~<div[^>]*class="[^"]*tab-content[^"]*"[^>]*>(.*?)</div>~is',
                static function ($matches) use ($tabPaneHtml) {
                    if (strpos($matches[0], 'grit-competitor-tab-pane') !== false) {
                        return $matches[0];
                    }

                    return str_replace('</div>', $tabPaneHtml . '</div>', $matches[0]);
                },
                $body,
                1
            );
        }

        if (!$tabsInjected) {
            $fallback = '<div id="grit-competitor-server-fallback" class="alert alert-info mt-3" style="margin:15px;">'
                . '<strong>Цены конкурентов</strong> (товар ID: ' . (int) $productId . ')<br>'
                . '<a class="btn btn-primary btn-sm mt-2" target="_blank" href="' . $componentLink . '">Открыть список цен</a> '
                . '<a class="btn btn-success btn-sm mt-2" target="_blank" href="' . $addLink . '">Добавить цену конкурента</a>'
                . '</div>';

            $body = preg_replace('~</body>~i', $fallback . '</body>', $body, 1) ?: $body . $fallback;
        }

        $app->setBody($body);

        if ($debugLogging) {
            Log::add('Injected competitor tab. tabsInjected=' . (int) $tabsInjected . ', productId=' . $productId, Log::INFO, 'plg_system_grit_competitor_tab');
        }
    }
}
