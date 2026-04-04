<?php

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Plugin\CMSPlugin;

final class PlgSystemGrit_competitor_tab extends CMSPlugin
{
    /** @var int|null */
    private $debugStartTotalCount = null;
    /** @var int|null */
    private $debugStartProductCount = null;
    /** @var int|null */
    private $debugTrackedProductId = null;

    private function isDebugEnabled(): bool
    {
        return (bool) $this->params->get('debug_logging', 0);
    }

    private function initDebugLogger(): void
    {
        static $loggerInitialized = false;

        if ($loggerInitialized || !$this->isDebugEnabled()) {
            return;
        }

        Log::addLogger(
            ['text_file' => 'plg_system_grit_competitor_tab.php', 'text_file_path' => 'logs'],
            Log::ALL,
            ['plg_system_grit_competitor_tab']
        );

        $loggerInitialized = true;
    }

    private function debugLog(string $message, int $level = Log::INFO): void
    {
        if (!$this->isDebugEnabled()) {
            return;
        }

        $this->initDebugLogger();
        Log::add($message, $level, 'plg_system_grit_competitor_tab');
    }

    private function makeBackupKey(int $productId): string
    {
        return 'grit_cp_backup_' . $productId;
    }

    public function onAfterInitialise(): void
    {
        $app = Factory::getApplication();
        if (!$app->isClient('administrator')) {
            return;
        }

        $input = $app->input;
        if ($input->getCmd('option') !== 'com_jshopping') {
            return;
        }

        if ($this->isDebugEnabled()) {
            $this->initDebugLogger();
        }

        $productId = $input->getInt('product_id', $input->getInt('id'));
        $cid = $input->get('cid', [], 'array');
        if ($productId <= 0 && !empty($cid)) {
            $productId = (int) reset($cid);
        }

        $this->debugTrackedProductId = $productId > 0 ? $productId : null;

        $db = Factory::getDbo();
        if ($this->isDebugEnabled()) {
            try {
                $qTotal = $db->getQuery(true)
                    ->select('COUNT(*)')
                    ->from($db->quoteName('#__competitor_prices'));
                $db->setQuery($qTotal);
                $this->debugStartTotalCount = (int) $db->loadResult();

                if ($this->debugTrackedProductId) {
                    $qProduct = $db->getQuery(true)
                        ->select('COUNT(*)')
                        ->from($db->quoteName('#__competitor_prices'))
                        ->where($db->quoteName('product_id') . ' = ' . (int) $this->debugTrackedProductId);
                    $db->setQuery($qProduct);
                    $this->debugStartProductCount = (int) $db->loadResult();
                }
            } catch (Throwable $e) {
                $this->debugLog('Initial debug count query failed: ' . $e->getMessage(), Log::ERROR);
            }
        }

        $post = $input->post->getArray();
        $task = $input->getCmd('task');
        if ($this->isDebugEnabled()) {
            $method = $input->getMethod();
            $this->debugLog(
                'Request start: method=' . $method
                . ', task=' . $task
                . ', productId=' . $productId
                . ', cid=' . json_encode($cid)
                . ', post_keys=' . implode(',', array_keys($post))
                . ', start_total=' . (string) $this->debugStartTotalCount
                . ', start_product=' . (string) $this->debugStartProductCount
            );
        }

        if (in_array($task, ['save', 'apply'], true) || str_contains($task, 'save')) {
            if ($this->isDebugEnabled()) {
                $this->debugLog(
                    'Save-like request payload snapshot: id=' . ($post['id'] ?? '')
                    . ', product_id=' . ($post['product_id'] ?? '')
                    . ', action=' . ($post['action'] ?? '')
                    . ', price=' . ($post['price'] ?? '')
                    . ', grit_cp_id=' . ($post['grit_cp_id'] ?? '')
                    . ', grit_cp_product_id=' . ($post['grit_cp_product_id'] ?? '')
                );
            }

            if ($productId > 0) {
                try {
                    $queryRows = $db->getQuery(true)
                        ->select('*')
                        ->from($db->quoteName('#__competitor_prices'))
                        ->where($db->quoteName('product_id') . ' = ' . (int) $productId)
                        ->order($db->quoteName('id') . ' DESC');
                    $db->setQuery($queryRows);
                    $rows = $db->loadAssocList() ?: [];

                    $app->getSession()->set($this->makeBackupKey($productId), ['ts' => time(), 'rows' => $rows]);
                    $this->debugLog('Backup stored before save/apply: productId=' . $productId . ', rows=' . count($rows));
                } catch (Throwable $e) {
                    $this->debugLog('Backup creation failed: ' . $e->getMessage(), Log::ERROR);
                }
            }
        }
    }

    public function onAfterRender(): void
    {
        $app = Factory::getApplication();
        $this->initDebugLogger();

        if (!$app->isClient('administrator')) {
            return;
        }

        $input = $app->input;
        $option = $input->getCmd('option');
        $controller = $input->getCmd('controller');
        $view = $input->getCmd('view');
        $task = $input->getCmd('task');
        $productId = $input->getInt('product_id', $input->getInt('id'));
        $postProductId = $input->post->getInt('product_id', $input->post->getInt('id'));
        $cid = $input->get('cid', [], 'array');
        $jform = $input->post->get('jform', [], 'array');
        $jformProductId = isset($jform['product_id']) ? (int) $jform['product_id'] : (isset($jform['id']) ? (int) $jform['id'] : 0);

        if ($productId <= 0 && !empty($cid)) {
            $productId = (int) reset($cid);
        }
        if ($productId <= 0 && $postProductId > 0) {
            $productId = $postProductId;
        }
        if ($productId <= 0 && $jformProductId > 0) {
            $productId = $jformProductId;
        }

        if ($option !== 'com_jshopping') {
            return;
        }

        $isProductsController = in_array($controller, ['product', 'products'], true);
        $isProductView = ($view === 'product' || $view === 'products');
        $isEditTask = in_array($task, ['edit', 'apply', 'save'], true) || str_contains($task, 'product');
        $isProductEdit = (($isProductsController || $isProductView) && $isEditTask && $productId > 0);

        $this->debugLog('Detected com_jshopping page. controller=' . $controller . ', view=' . $view . ', task=' . $task . ', productId=' . $productId . ', postProductId=' . $postProductId . ', jformProductId=' . $jformProductId . ', cid=' . json_encode($cid) . ', isProductEdit=' . (int) $isProductEdit);

        $body = '';
        if (!$isProductEdit && ($isProductsController || $isProductView) && $isEditTask) {
            $body = $app->getBody();
            if (preg_match('/name="product_id"[^>]*value="(\d+)"/i', $body, $m) || preg_match('/name="id"[^>]*value="(\d+)"/i', $body, $m)) {
                $productId = (int) $m[1];
                $isProductEdit = ($productId > 0);
                $this->debugLog('Product ID recovered from HTML body (fallback): productId=' . $productId . ', isProductEdit=' . (int) $isProductEdit);
            }
        }

        if (!$isProductEdit) {
            return;
        }

        try {
            $session = $app->getSession();
            $backup = $session->get($this->makeBackupKey($productId), null);
            if (is_array($backup) && !empty($backup['rows']) && isset($backup['ts']) && (time() - (int) $backup['ts'] <= 300)) {
                $db = Factory::getDbo();
                $checkQuery = $db->getQuery(true)
                    ->select('COUNT(*)')
                    ->from($db->quoteName('#__competitor_prices'))
                    ->where($db->quoteName('product_id') . ' = ' . (int) $productId);
                $db->setQuery($checkQuery);
                $existingCount = (int) $db->loadResult();

                if ($existingCount === 0) {
                    $columnsInfo = $db->getTableColumns('#__competitor_prices', false);
                    foreach ($backup['rows'] as $row) {
                        $insertColumns = [];
                        $insertValues = [];
                        foreach ($row as $column => $value) {
                            if ($column === 'id' || !isset($columnsInfo[$column])) {
                                continue;
                            }
                            $insertColumns[] = $db->quoteName($column);
                            $insertValues[] = $db->quote($value);
                        }
                        if (!$insertColumns) {
                            continue;
                        }
                        $insertQuery = $db->getQuery(true)
                            ->insert($db->quoteName('#__competitor_prices'))
                            ->columns($insertColumns)
                            ->values(implode(',', $insertValues));
                        $db->setQuery($insertQuery);
                        $db->execute();
                    }
                    $this->debugLog('Backup restored after save/apply wipe: productId=' . $productId . ', rows=' . count($backup['rows']), Log::WARNING);
                }

                $session->clear($this->makeBackupKey($productId));
            }
        } catch (Throwable $e) {
            $this->debugLog('Backup restore check failed: ' . $e->getMessage(), Log::ERROR);
        }

        if ($body === '') {
            $body = $app->getBody();
        }

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
            . '<div id="grit-competitor-form" style="max-width:820px;">'
            . '<input type="hidden" name="grit_cp_id" value="">'
            . '<input type="hidden" name="grit_cp_product_id" value="' . (int) $productId . '">'
            . '<div class="control-group"><label>Название конкурента</label><input class="form-control" type="text" name="grit_cp_competitor_name" required></div>'
            . '<div class="control-group"><label>URL конкурента</label><input class="form-control" type="url" name="grit_cp_url"></div>'
            . '<div class="control-group"><label>Селектор цены</label><input class="form-control" type="text" name="grit_cp_selector"></div>'
            . '<div class="control-group"><label>Цена</label><input class="form-control" type="text" name="grit_cp_price"></div>'
            . '<div class="control-group" style="margin-top:10px;"><button id="grit-competitor-save" class="btn btn-success" type="button">Сохранить</button></div>'
            . '<div id="grit-competitor-result" style="margin-top:10px;"></div>'
            . '</div>'
            . '<div id="grit-competitor-list" style="margin-top:15px;"></div>';

        $script = '<script>(function(){'
            . 'var pid=' . (int) $productId . ';'
            . 'var ajaxUrl=' . json_encode($ajaxUrl) . ';'
            . 'var paneHtml=' . json_encode($paneHtml) . ';'
            . 'function esc(v){return String(v||"").replace(/[&<>\"\']/g,function(s){return ({"&":"&amp;","<":"&lt;",">":"&gt;","\"":"&quot;","\'":"&#039;"})[s];});}function val(it,n,u,i){if(!it){return "";}if(it[n]!==undefined){return it[n];}if(it[u]!==undefined){return it[u];}if(it[i]!==undefined){return it[i];}return "";}'
            . 'function bindActions(items,f){var listEl=document.getElementById("grit-competitor-list");if(!listEl||!f){return;} Array.prototype.forEach.call(listEl.querySelectorAll(".grit-edit"),function(btn){btn.addEventListener("click",function(){var id=this.getAttribute("data-id");var row=(items||[]).find(function(x){return String(val(x,"id","ID",0))===String(id);});if(!row){return;}f.querySelector("input[name=grit_cp_id]").value=val(row,"id","ID",0)||"";f.querySelector("input[name=grit_cp_competitor_name]").value=val(row,"competitor_name","COMPETITOR_NAME",1)||"";f.querySelector("input[name=grit_cp_url]").value=val(row,"url","URL",2)||"";f.querySelector("input[name=grit_cp_selector]").value=val(row,"selector","SELECTOR",3)||"";f.querySelector("input[name=grit_cp_price]").value=val(row,"price","PRICE",4)||"";window.scrollTo({top:listEl.offsetTop-120,behavior:"smooth"});});}); Array.prototype.forEach.call(listEl.querySelectorAll(".grit-del"),function(btn){btn.addEventListener("click",function(){var id=this.getAttribute("data-id");if(!confirm("Удалить запись?")){return;}var fd=new FormData();fd.append("action","delete");fd.append("id",id);fd.append("product_id",pid);fetch(ajaxUrl,{method:"POST",body:fd,credentials:"same-origin"}).then(function(r){return r.json();}).then(function(){loadList();}).catch(function(){});});});}'
            . 'function render(items){var listEl=document.getElementById("grit-competitor-list");var f=document.getElementById("grit-competitor-form");if(!listEl){return;} if(!items||!items.length){listEl.innerHTML="<div class=\\"alert alert-light\\">Записей пока нет</div>";return;} var h="<table class=\\"table table-sm\\"><thead><tr><th>ID</th><th>Конкурент</th><th>URL</th><th>Селектор</th><th>Цена</th><th>Обновлено</th><th>Действия</th></tr></thead><tbody>";items.forEach(function(it){h+="<tr><td>"+esc(val(it,"id","ID",0))+"</td><td>"+esc(val(it,"competitor_name","COMPETITOR_NAME",1))+"</td><td>"+esc(val(it,"url","URL",2))+"</td><td>"+esc(val(it,"selector","SELECTOR",3))+"</td><td>"+esc(val(it,"price","PRICE",4))+"</td><td>"+esc(val(it,"last_update","LAST_UPDATE",5))+"</td><td><button type=\\"button\\" class=\\"btn btn-xs btn-primary grit-edit\\" data-id=\\""+esc(val(it,"id","ID",0))+"\\">Ред.</button> <button type=\\"button\\" class=\\"btn btn-xs btn-danger grit-del\\" data-id=\\""+esc(val(it,"id","ID",0))+"\\">Удал.</button></td></tr>";});h+="</tbody></table>";listEl.innerHTML=h;bindActions(items,f);}'
            . 'function loadList(){fetch(ajaxUrl+"&action=list&product_id="+pid,{credentials:"same-origin"}).then(function(r){return r.json();}).then(function(d){var items=[];if(d&&d.success){if(d.data&&d.data.items){items=d.data.items;}else if(Array.isArray(d.data)){if(d.data.length&&d.data[0]&&d.data[0].items&&Array.isArray(d.data[0].items)){items=d.data[0].items;}else{items=d.data;}}}render(items);}).catch(function(){render([]);});}'
            . 'function init(){var tc=document.querySelector(".tab-content");if(!tc){return;} if(!document.getElementById("grit-competitor-tab-pane")){var p=document.createElement("div");p.className="tab-pane";p.id="grit-competitor-tab-pane";p.innerHTML=paneHtml;tc.appendChild(p);} var f=document.getElementById("grit-competitor-form");if(f&&!f.dataset.binded){f.dataset.binded="1";var mainForm=f.closest("form");if(mainForm&&!mainForm.dataset.gritCpBound){mainForm.dataset.gritCpBound="1";mainForm.addEventListener("submit",function(){Array.prototype.forEach.call(f.querySelectorAll("input[name^=grit_cp_]"),function(inp){if(!inp.dataset.origName){inp.dataset.origName=inp.getAttribute("name")||"";}inp.removeAttribute("name");});});}var saveBtn=document.getElementById("grit-competitor-save");if(saveBtn){saveBtn.addEventListener("click",function(){var fd=new FormData();fd.append("action","save");fd.append("id",f.querySelector("input[data-orig-name=grit_cp_id],input[name=grit_cp_id]").value||"");fd.append("product_id",f.querySelector("input[data-orig-name=grit_cp_product_id],input[name=grit_cp_product_id]").value||pid);fd.append("competitor_name",f.querySelector("input[data-orig-name=grit_cp_competitor_name],input[name=grit_cp_competitor_name]").value||"");fd.append("url",f.querySelector("input[data-orig-name=grit_cp_url],input[name=grit_cp_url]").value||"");fd.append("selector",f.querySelector("input[data-orig-name=grit_cp_selector],input[name=grit_cp_selector]").value||"");fd.append("price",f.querySelector("input[data-orig-name=grit_cp_price],input[name=grit_cp_price]").value||"");fetch(ajaxUrl,{method:"POST",body:fd,credentials:"same-origin"}).then(function(r){return r.json();}).then(function(d){var r=document.getElementById("grit-competitor-result");if(d&&d.success){r.innerHTML="<span style=\\"color:green\\">Сохранено</span>";Array.prototype.forEach.call(f.querySelectorAll("input[data-orig-name=grit_cp_competitor_name],input[name=grit_cp_competitor_name],input[data-orig-name=grit_cp_url],input[name=grit_cp_url],input[data-orig-name=grit_cp_selector],input[name=grit_cp_selector],input[data-orig-name=grit_cp_price],input[name=grit_cp_price]"),function(inp){inp.value="";});f.querySelector("input[data-orig-name=grit_cp_product_id],input[name=grit_cp_product_id]").value=pid;f.querySelector("input[data-orig-name=grit_cp_id],input[name=grit_cp_id]").value="";loadList();}else{r.innerHTML="<span style=\\"color:#a00\\">Ошибка сохранения</span>";}}).catch(function(){var r=document.getElementById("grit-competitor-result");r.innerHTML="<span style=\\"color:#a00\\">Ошибка запроса</span>";});});}} loadList();}'
            . 'if(document.readyState==="loading"){document.addEventListener("DOMContentLoaded",init);}else{init();}'
            . '})();</script>';

        if (stripos($body, '</body>') !== false) {
            $body = preg_replace('~</body>~i', $script . '</body>', $body, 1);
        } else {
            $body .= $script;
        }

        $app->setBody($body);

        $this->debugLog('Injected competitor editable tab. tabsInjected=' . (int) $tabsInjected . ', productId=' . $productId);

        if ($this->debugStartTotalCount !== null) {
            try {
                $db = Factory::getDbo();
                $qTotal = $db->getQuery(true)
                    ->select('COUNT(*)')
                    ->from($db->quoteName('#__competitor_prices'));
                $db->setQuery($qTotal);
                $endTotal = (int) $db->loadResult();

                $endProduct = null;
                if ($this->debugTrackedProductId) {
                    $qProduct = $db->getQuery(true)
                        ->select('COUNT(*)')
                        ->from($db->quoteName('#__competitor_prices'))
                        ->where($db->quoteName('product_id') . ' = ' . (int) $this->debugTrackedProductId);
                    $db->setQuery($qProduct);
                    $endProduct = (int) $db->loadResult();
                }

                $this->debugLog(
                    'Request end: productId=' . (int) $this->debugTrackedProductId
                    . ', end_total=' . $endTotal
                    . ', end_product=' . (string) $endProduct
                    . ', delta_total=' . ($endTotal - (int) $this->debugStartTotalCount)
                    . ', delta_product=' . (($endProduct === null || $this->debugStartProductCount === null) ? 'n/a' : (string) ($endProduct - $this->debugStartProductCount))
                );
            } catch (Throwable $e) {
                $this->debugLog('Final debug count query failed: ' . $e->getMessage(), Log::ERROR);
            }
        }
    }

    public function onAjaxGrit_competitor_tab()
    {
        $app = Factory::getApplication();
        $input = $app->input;
        $db = Factory::getDbo();

        $action = $input->getCmd('action', 'save');
        $productId = $input->getInt('product_id');
        $id = $input->getInt('id');
        $this->initDebugLogger();
        $this->debugLog('AJAX start: method=' . $input->getMethod() . ', action=' . $action . ', product_id=' . $productId . ', id=' . $id . ', raw=' . json_encode($input->post->getArray()));

        if ($action === 'list') {
            if ($productId <= 0) {
                $this->debugLog('AJAX list aborted: invalid product_id=' . $productId, Log::WARNING);
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

            $this->debugLog('AJAX list columns=' . implode(', ', array_keys($columnsInfo)) . '; select=' . implode(', ', $select));
            $this->debugLog('AJAX list SQL=' . (string) $query);

            try {
                $db->setQuery($query);
                $items = $db->loadAssocList() ?: [];
                $this->debugLog('AJAX list loaded rows=' . count($items) . ($items ? '; firstRow=' . json_encode($items[0]) : ''));
                return ['items' => $items];
            } catch (Throwable $e) {
                $this->debugLog('AJAX list DB error: ' . $e->getMessage(), Log::ERROR);
                throw $e;
            }
        }

        if ($action === 'delete') {
            if ($id <= 0) {
                $this->debugLog('AJAX delete aborted: invalid id=' . $id, Log::WARNING);
                throw new RuntimeException('Invalid id');
            }

            $query = $db->getQuery(true)
                ->delete($db->quoteName('#__competitor_prices'))
                ->where($db->quoteName('id') . ' = ' . (int) $id);

            $this->debugLog('AJAX delete SQL=' . (string) $query);

            try {
                $db->setQuery($query);
                $db->execute();
                $this->debugLog('AJAX delete done: id=' . $id);
            } catch (Throwable $e) {
                $this->debugLog('AJAX delete DB error: ' . $e->getMessage(), Log::ERROR);
                throw $e;
            }

            return ['deleted' => true];
        }

        $competitorName = trim($input->getString('competitor_name'));
        $url = trim($input->getString('url'));
        $selector = trim($input->getString('selector'));
        $price = trim($input->getString('price'));
        $this->debugLog('AJAX save payload: competitor_name=' . $competitorName . ', url=' . $url . ', selector=' . $selector . ', price=' . $price . ', product_id=' . $productId . ', id=' . $id);

        if ($productId <= 0 || $competitorName === '') {
            $this->debugLog('AJAX save aborted: invalid data product_id=' . $productId . ', competitor_name_len=' . strlen($competitorName), Log::WARNING);
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

        if ($id > 0) {
            $set = [];
            foreach ($columns as $idx => $column) {
                if ($column === 'product_id') {
                    continue;
                }
                $set[] = $db->quoteName($column) . ' = ' . $db->quote($values[$idx]);
            }

            $query = $db->getQuery(true)
                ->update($db->quoteName('#__competitor_prices'))
                ->set($set)
                ->where($db->quoteName('id') . ' = ' . (int) $id);
        } else {
            $query = $db->getQuery(true)
                ->insert($db->quoteName('#__competitor_prices'))
                ->columns(array_map([$db, 'quoteName'], $columns))
                ->values(implode(',', array_map([$db, 'quote'], $values)));
        }

        $this->debugLog('AJAX save columns=' . implode(', ', $columns));
        $this->debugLog('AJAX save SQL=' . (string) $query);

        try {
            $db->setQuery($query);
            $db->execute();
        } catch (Throwable $e) {
            $this->debugLog('AJAX save DB error: ' . $e->getMessage(), Log::ERROR);
            throw $e;
        }

        $savedId = $id ?: (int) $db->insertid();
        $this->debugLog('AJAX save done: saved_id=' . $savedId);
        return ['saved' => true, 'id' => $savedId];
    }
}
