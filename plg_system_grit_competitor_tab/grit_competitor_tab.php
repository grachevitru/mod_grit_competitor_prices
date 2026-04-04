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

        $pricesLink = 'index.php?option=com_grit_competitor_prices&view=prices&filter_search=' . (int) $productId;

        $tabLinkHtml = '<li class="nav-item">'
            . '<a class="nav-link" id="grit-competitor-tab-link" href="' . $pricesLink . '" target="_blank" rel="noopener noreferrer">Цены конкурентов</a>'
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

        $app->setBody($body);

        if ($debugLogging) {
            Log::add('Injected competitor tab link only. tabsInjected=' . (int) $tabsInjected . ', productId=' . $productId, Log::INFO, 'plg_system_grit_competitor_tab');
        }
    }
}
