<?php
/**
 * TabCalorie module for MajorDoMo.
 *
 * Imports food diary data from the unofficial internal API of the
 * "Таблиця калорійності" portal into local tables, exposes per-meal
 * aggregates on the TabCalorie object, and provides a responsive UI with
 * overview, history, food dictionary, diagnostics, CSV export and charts.
 *
 * Write-back to TK is DISABLED by default and always requires confirmation.
 * No official public API exists - polling is deliberately slow and serialized.
 *
 * @package tabcalorie
 * @author  mambursoft
 * @license MIT
 * @version 1.3.1
 */

require_once(dirname(__FILE__) . '/lib/Api.php');
require_once(dirname(__FILE__) . '/lib/Store.php');
require_once(dirname(__FILE__) . '/lib/Exporter.php');
require_once(dirname(__FILE__) . '/lib/Chart.php');

class tabcalorie extends module
{
    const VERSION = '1.3.1';

    function tabcalorie()
    {
        $this->name            = 'tabcalorie';
        // Plain string: the admin menu is rendered BEFORE module language files
        // are loaded, so a <#LANG_TC_TITLE#> placeholder would stay unresolved.
        $this->title           = 'TabCalorie';
        $this->module_category = '<#LANG_SECTION_APPLICATIONS#>';
        $this->checkInstalled();
    }

    function __construct()
    {
        $this->tabcalorie();
    }

    function saveParams($data = 0)
    {
        $p = array();
        if (isset($this->id))   $p['id']   = $this->id;
        if (isset($this->mode)) $p['mode'] = $this->mode;
        if (isset($this->tab))  $p['tab']  = $this->tab;
        return parent::saveParams($p);
    }

    function getParams()
    {
        global $id, $mode, $tab;
        if (isset($id))   $this->id   = $id;
        if (isset($mode)) $this->mode = $mode;
        if (isset($tab))  $this->tab  = $tab;
    }

    // ---------------------------------------------------------------- install

    function defaults()
    {
        return array(
            'BASE_URL'          => 'https://www.tablycjakalorijnosti.com.ua',
            'PROVIDER'          => 'ua',
            'EMAIL'             => '',
            'PASSWORD'          => '',
            'SYNC_ENABLED'      => 1,
            'SYNC_INTERVAL_MIN' => 60,
            'SYNC_DAYS'         => 2,
            'HOOK_ENABLED'      => 0,
            'HOOK_PROPERTY'     => '',
            'HOOK_DEBOUNCE_SEC' => 60,
            'RETENTION_DAYS'    => 1825,
            'LANGUAGE'          => 'uk',
            'GRAMS_PER_XE'      => 10,
            'CARBS_TARGET'      => 120,
            'CALORIES_TARGET'   => 1800,
            'PROTEIN_TARGET'    => 90,
            'FAT_TARGET'        => 60,
            'MEAL_NORM_SHARE'   => 25,
            'CSV_DELIMITER'     => ';',
            'ACTIVITY_ENABLED'  => 1,
            'LOG_RETENTION_DAYS' => 30,
            'LOG_MAX_ROWS'      => 2000,
            'WRITE_QUEUE_TTL_DAYS' => 7,
            'WRITEBACK_ENABLED' => 0,
            'WRITEBACK_CONFIRM' => 1,
            'NS_ENABLED'        => 0,
            'NS_URL'            => '',
            'NS_API_SECRET'     => '',
            'DEMO_MODE'         => 0,
            'LAST_SYNC'         => '',
            'LAST_ERROR'        => '',
            'CIRCUIT_UNTIL'     => 0,
            'FAIL_COUNT'        => 0,
        );
    }

    function configAll()
    {
        $cfg = $this->getConfig();
        if (!is_array($cfg)) {
            $cfg = array();
        }
        return array_merge($this->defaults(), $cfg);
    }

    function install($data = '')
    {
        // parent::install() already calls $this->dbInstall("").
        parent::install();

        $cfg = $this->configAll();
        $this->config = $cfg;
        $this->saveConfig();

        $has = SQLSelectOne("SELECT COUNT(*) AS N FROM information_schema.STATISTICS
                             WHERE TABLE_SCHEMA=DATABASE()
                             AND TABLE_NAME='tabcalorie_entries'
                             AND INDEX_NAME='UQ_TC_ENTRY'");
        if (!(int)$has['N']) {
            @SQLExec("CREATE UNIQUE INDEX UQ_TC_ENTRY ON tabcalorie_entries (ENTRY_KEY(64))");
        }
        $hasAct = SQLSelectOne("SELECT COUNT(*) AS N FROM information_schema.STATISTICS
                                WHERE TABLE_SCHEMA=DATABASE()
                                AND TABLE_NAME='tabcalorie_activities'
                                AND INDEX_NAME='UQ_TC_ACTIVITY'");
        if (!(int)$hasAct['N']) {
            @SQLExec("CREATE UNIQUE INDEX UQ_TC_ACTIVITY ON tabcalorie_activities (ACTIVITY_KEY(64))");
        }
        self::ensureObject();
    }

    function dbInstall($data = '')
    {
        $schema = <<<EOD
tabcalorie_entries: ID int(10) unsigned NOT NULL auto_increment
tabcalorie_entries: ENTRY_KEY varchar(64) NOT NULL DEFAULT ''
tabcalorie_entries: ENTRY_DATE date NOT NULL
tabcalorie_entries: SLOT_ID int(11) NOT NULL DEFAULT '0'
tabcalorie_entries: SLOT_TITLE varchar(255) NOT NULL DEFAULT ''
tabcalorie_entries: TITLE varchar(255) NOT NULL DEFAULT ''
tabcalorie_entries: FOOD_GUID varchar(64) NOT NULL DEFAULT ''
tabcalorie_entries: ITEM_ID varchar(64) NOT NULL DEFAULT ''
tabcalorie_entries: FOOD_URL varchar(255) NOT NULL DEFAULT ''
tabcalorie_entries: UNIT_RAW varchar(64) NOT NULL DEFAULT ''
tabcalorie_entries: QUANTITY float NOT NULL DEFAULT '0'
tabcalorie_entries: UNIT varchar(32) NOT NULL DEFAULT ''
tabcalorie_entries: ENERGY float NOT NULL DEFAULT '0'
tabcalorie_entries: PROTEIN float NOT NULL DEFAULT '0'
tabcalorie_entries: FAT float NOT NULL DEFAULT '0'
tabcalorie_entries: CARBOHYDRATE float NOT NULL DEFAULT '0'
tabcalorie_entries: FIBER float NOT NULL DEFAULT '0'
tabcalorie_entries: SUGAR float NOT NULL DEFAULT '0'
tabcalorie_entries: SOURCE varchar(16) NOT NULL DEFAULT 'tk'
tabcalorie_entries: SYNCED_AT datetime NOT NULL DEFAULT '0000-00-00 00:00:00'
tabcalorie_entries: KEY (ENTRY_DATE)
tabcalorie_entries: KEY (FOOD_GUID)
tabcalorie_foods: ID int(10) unsigned NOT NULL auto_increment
tabcalorie_foods: FOOD_GUID varchar(64) NOT NULL DEFAULT ''
tabcalorie_foods: FOOD_ID varchar(64) NOT NULL DEFAULT ''
tabcalorie_foods: TITLE varchar(255) NOT NULL DEFAULT ''
tabcalorie_foods: UNIT varchar(32) NOT NULL DEFAULT ''
tabcalorie_foods: FOOD_URL varchar(255) NOT NULL DEFAULT ''
tabcalorie_foods: UNIT_GUID varchar(64) NOT NULL DEFAULT '0000000000000001'
tabcalorie_foods: UNIT_OPTIONS text
tabcalorie_foods: DEFAULT_MULTIPLIER float NOT NULL DEFAULT '100'
tabcalorie_foods: DEFAULT_SLOT int(11) NOT NULL DEFAULT '0'
tabcalorie_foods: SERVICE_JSON text
tabcalorie_foods: ENERGY_100 float NOT NULL DEFAULT '0'
tabcalorie_foods: PROTEIN_100 float NOT NULL DEFAULT '0'
tabcalorie_foods: FAT_100 float NOT NULL DEFAULT '0'
tabcalorie_foods: CARB_100 float NOT NULL DEFAULT '0'
tabcalorie_foods: FIBER_100 float NOT NULL DEFAULT '0'
tabcalorie_foods: SUGAR_100 float NOT NULL DEFAULT '0'
tabcalorie_foods: GI float NOT NULL DEFAULT '0'
tabcalorie_foods: NOTES text
tabcalorie_foods: UPDATED datetime NOT NULL DEFAULT '0000-00-00 00:00:00'
tabcalorie_days: ID int(10) unsigned NOT NULL auto_increment
tabcalorie_days: DAY_DATE date NOT NULL
tabcalorie_days: ENERGY float NOT NULL DEFAULT '0'
tabcalorie_days: PROTEIN float NOT NULL DEFAULT '0'
tabcalorie_days: FAT float NOT NULL DEFAULT '0'
tabcalorie_days: CARBOHYDRATE float NOT NULL DEFAULT '0'
tabcalorie_days: FIBER float NOT NULL DEFAULT '0'
tabcalorie_days: SUGAR float NOT NULL DEFAULT '0'
tabcalorie_days: SLOT_COUNT int(11) NOT NULL DEFAULT '0'
tabcalorie_days: FOODSTUFF_COUNT int(11) NOT NULL DEFAULT '0'
tabcalorie_days: STEPS float NOT NULL DEFAULT '0'
tabcalorie_days: ACTIVITY_KCAL float NOT NULL DEFAULT '0'
tabcalorie_days: ACTIVITY_COUNT int(11) NOT NULL DEFAULT '0'
tabcalorie_days: NOTES text
tabcalorie_days: RAW_JSON longtext
tabcalorie_days: UPDATED datetime NOT NULL DEFAULT '0000-00-00 00:00:00'
tabcalorie_days: KEY (DAY_DATE)
tabcalorie_activities: ID int(10) unsigned NOT NULL auto_increment
tabcalorie_activities: ACTIVITY_KEY varchar(64) NOT NULL DEFAULT ''
tabcalorie_activities: ACTIVITY_DATE date NOT NULL
tabcalorie_activities: ACTIVITY_ID varchar(64) NOT NULL DEFAULT ''
tabcalorie_activities: TITLE varchar(255) NOT NULL DEFAULT ''
tabcalorie_activities: TYPE varchar(64) NOT NULL DEFAULT ''
tabcalorie_activities: ACTIVITY_TYPE varchar(64) NOT NULL DEFAULT ''
tabcalorie_activities: STEPS float NOT NULL DEFAULT '0'
tabcalorie_activities: ENERGY float NOT NULL DEFAULT '0'
tabcalorie_activities: UNIT varchar(32) NOT NULL DEFAULT 'kcal'
tabcalorie_activities: DURATION float NOT NULL DEFAULT '0'
tabcalorie_activities: RAW_JSON text
tabcalorie_activities: SYNCED_AT datetime NOT NULL DEFAULT '0000-00-00 00:00:00'
tabcalorie_activities: KEY (ACTIVITY_DATE)
tabcalorie_sync_log: ID int(10) unsigned NOT NULL auto_increment
tabcalorie_sync_log: RUN_AT datetime NOT NULL DEFAULT '0000-00-00 00:00:00'
tabcalorie_sync_log: KIND varchar(32) NOT NULL DEFAULT ''
tabcalorie_sync_log: STATUS varchar(16) NOT NULL DEFAULT ''
tabcalorie_sync_log: HTTP_CODE int(11) NOT NULL DEFAULT '0'
tabcalorie_sync_log: ROWS int(11) NOT NULL DEFAULT '0'
tabcalorie_sync_log: MESSAGE text
tabcalorie_write_queue: ID int(10) unsigned NOT NULL auto_increment
tabcalorie_write_queue: ENTRY_ID int(11) NOT NULL DEFAULT '0'
tabcalorie_write_queue: PAYLOAD text
tabcalorie_write_queue: STATUS varchar(16) NOT NULL DEFAULT 'draft'
tabcalorie_write_queue: CREATED_AT datetime NOT NULL DEFAULT '0000-00-00 00:00:00'
tabcalorie_write_queue: SENT_AT datetime NOT NULL DEFAULT '0000-00-00 00:00:00'
tabcalorie_write_queue: MESSAGE text
EOD;
        parent::dbInstall($schema);
    }

    function uninstall()
    {
        SQLExec("DROP TABLE IF EXISTS tabcalorie_entries");
        SQLExec("DROP TABLE IF EXISTS tabcalorie_foods");
        SQLExec("DROP TABLE IF EXISTS tabcalorie_days");
        SQLExec("DROP TABLE IF EXISTS tabcalorie_sync_log");
        SQLExec("DROP TABLE IF EXISTS tabcalorie_write_queue");
        SQLExec("DROP TABLE IF EXISTS tabcalorie_activities");
        parent::uninstall();
    }

    /** Idempotent creation of the STabCalorie class and TabCalorie object. */
    static function ensureObject()
    {
        if (!function_exists('addClass') || !function_exists('getObject')) {
            return false;
        }
        $classId = addClass('STabCalorie');
        if (!$classId) {
            return false;
        }
        $props = array('LastDate', 'Partial', 'FilledSlots', 'GroupMainCarbs',
            'GroupSnacksCarbs', 'GroupDayCarbs', 'TotalCarbs', 'TotalKcal',
            'TotalProtein', 'TotalFat', 'TotalFiber', 'TotalSugar', 'TotalXE',
            'GI', 'Notes', 'Updated', 'Status', 'Raw',
            'ActivitySteps', 'ActivityKcal', 'ActivityCount');
        for ($i = 1; $i <= 6; $i++) {
            $props[] = 'Slot' . $i . 'Carbs';
            $props[] = 'Slot' . $i . 'Kcal';
            $props[] = 'Slot' . $i . 'Protein';
            $props[] = 'Slot' . $i . 'Fat';
        }
        foreach ($props as $p) {
            $history = (strpos($p, 'Carbs') !== false || strpos($p, 'Total') === 0) ? 365 : 0;
            addClassProperty('STabCalorie', $p, $history);
        }
        addClassObject('STabCalorie', 'TabCalorie', 'tabcalorie');
        return true;
    }

    function checkInstalled()
    {
        parent::checkInstalled();

        $rec = SQLSelectOne("SELECT ID, CATEGORY, TITLE FROM project_modules WHERE NAME='tabcalorie'");
        if (!$rec['ID']) {
            // SQLInsert() takes the record BY REFERENCE in MajorDoMo.
            $newModule = array(
                'NAME' => 'tabcalorie', 'TITLE' => $this->title,
                'CATEGORY' => $this->module_category, 'DATA' => '',
            );
            SQLInsert('project_modules', $newModule);
        } elseif (strpos((string)$rec['TITLE'], '<#LANG_') !== false || trim((string)$rec['TITLE']) === '') {
            // Repair installations created before v1.2.1: the menu is rendered
            // before module dictionaries load, so the placeholder was empty.
            $rec['TITLE'] = $this->title;
            SQLUpdate('project_modules', $rec);
        }

        if (defined('ROOT')) {
            $flagDir = ROOT . 'cms/modules_installed/';
            if (!is_dir($flagDir)) {
                @mkdir($flagDir, 0775, true);
            }
            $flag = $flagDir . 'tabcalorie.installed';
            if (!file_exists($flag)) {
                @file_put_contents($flag, date('H:i d.m.Y'));
            }
            $icon = ROOT . 'img/modules/tabcalorie.png';
            if (!file_exists($icon)) {
                @mkdir(ROOT . 'img/modules/', 0775, true);
                $src = dirname(__FILE__) . '/../../img/modules/tabcalorie.png';
                if (file_exists($src)) {
                    @copy($src, $icon);
                }
            }
        }

        $hasPlugins = SQLSelectOne("SHOW TABLES LIKE 'plugins'");
        if ($hasPlugins) {
            $col = SQLSelectOne("SHOW COLUMNS FROM plugins LIKE 'MODULE_NAME'");
            $key = ($col && isset($col['Field'])) ? 'MODULE_NAME' : 'NAME';
            $p = SQLSelectOne("SELECT * FROM plugins WHERE " . $key . "='tabcalorie'");
            $data = array(
                $key => 'tabcalorie',
                'TITLE' => 'TabCalorie',
                'AUTHOR' => 'mambursoft',
                'SUPPORT_URL' => 'https://github.com/mambursoft/majordomo_TabCalorie',
                'REPOSITORY_URL' => 'https://github.com/mambursoft/majordomo_TabCalorie',
                'DESCRIPTION_EN' => 'Food diary importer with carbohydrate control for MajorDoMo. Read-only by default, unofficial API.',
                'DESCRIPTION_RU' => 'Импортер пищевого дневника с контролем углеводов для MajorDoMo. По умолчанию только чтение.',
                'CURRENT_VERSION' => self::VERSION,
                'LATEST_VERSION' => self::VERSION,
                'IS_INSTALLED' => 1,
                'LATEST_UPDATE' => date('Y-m-d H:i:s'),
            );
            if (!$p['ID']) {
                SQLInsert('plugins', $data);
            } else {
                $data['ID'] = $p['ID'];
                SQLUpdate('plugins', $data);
            }
        }

        // Keep object Status/Raw fresh on every module instantiation (page load).
        try {
            TabCalorieStore::refreshStatusObject($this->configAll());
        } catch (Exception $e) {
            // Never break the admin page because of a status refresh.
        }
    }

    // ---------------------------------------------------------------- render

    function run()
    {
        $out = array();
        if ($this->action == 'admin') {
            $this->admin($out);
        } else {
            $this->usual($out);
        }
        $out['ACTION'] = $this->action;
        $out['MODE']   = isset($this->mode) ? $this->mode : '';
        $this->data = $out;

        $template = DIR_MODULES . 'tabcalorie/templates/tabcalorie.html';
        if (!file_exists($template) && defined('ROOT')) {
            $template = ROOT . 'modules/tabcalorie/templates/tabcalorie.html';
        }
        $p = new parser($template, $this->data, $this);
        $this->result = $p->result;
    }

    function tr($key)
    {
        $const = 'LANG_' . $key;
        return defined($const) ? constant($const) : $key;
    }

    function e($value)
    {
        return htmlspecialchars((string)$value, ENT_QUOTES, 'UTF-8');
    }

    function basePath()
    {
        return defined('ROOTHTML') ? ROOTHTML : '/';
    }

    function url($mode, $extra = array())
    {
        $params = array_merge(array('action' => 'tabcalorie', 'mode' => $mode), $extra);
        $parts = array();
        foreach ($params as $k => $v) {
            $parts[] = rawurlencode($k) . '=' . rawurlencode($v);
        }
        return '?' . implode('&', $parts);
    }

    function csrfToken()
    {
        if (!isset($_SESSION)) {
            return '';
        }
        if (empty($_SESSION['tabcalorie_csrf'])) {
            $_SESSION['tabcalorie_csrf'] = bin2hex(random_bytes(16));
        }
        return $_SESSION['tabcalorie_csrf'];
    }

    function checkCsrf()
    {
        if (strtoupper(isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET') !== 'POST') {
            return true;
        }
        $token = isset($_POST['csrf']) ? (string)$_POST['csrf'] : '';
        return $token !== '' && isset($_SESSION['tabcalorie_csrf']) && hash_equals($_SESSION['tabcalorie_csrf'], $token);
    }

    /** Simple per-session throttle for actions that open a TK connection. */
    function rateLimited($key, $seconds)
    {
        if (!isset($_SESSION)) {
            return false;
        }
        $k = 'tc_last_' . $key;
        $last = isset($_SESSION[$k]) ? (int)$_SESSION[$k] : 0;
        if (time() - $last < $seconds) {
            return true;
        }
        $_SESSION[$k] = time();
        return false;
    }

    function json($data)
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: application/json; charset=utf-8');
        header('Cache-Control: no-store');
        echo json_encode($data, JSON_UNESCAPED_UNICODE);
        exit;
    }

    function configured()
    {
        $cfg = $this->configAll();
        return $cfg['EMAIL'] !== '' && $cfg['PASSWORD'] !== '';
    }

    // ---------------------------------------------------------------- actions

    function admin(&$out)
    {
        $this->getParams();
        $cfg = $this->configAll();
        $tab = isset($_GET['tab']) ? (string)$_GET['tab'] : '';
        if ($tab === '') {
            $tab = $this->configured() ? 'overview' : 'settings';
        }
        $message = '';
        $error = '';

        if (isset($_GET['mode'])) {
            $mode = (string)$_GET['mode'];
            if ($mode === 'chart_svg') {
                $this->json(array('ok' => true, 'svg' => TabCalorieChart::svg(7)));
            }
            if ($mode === 'diagnose_ajax') {
                $fresh = !$this->rateLimited('diag', 10);
                $this->json(array('ok' => true, 'steps' => $this->diagnose($fresh)));
            }
            if ($mode === 'food_search_ajax') {
                if ($this->rateLimited('food_search', 2)) {
                    $this->json(array('ok' => false, 'message' => 'too many requests', 'items' => array()));
                }
                $this->ajaxFoodSearch(isset($_GET['q']) ? (string)$_GET['q'] : '');
            }
            if ($mode === 'food_detail_ajax') {
                if ($this->rateLimited('food_detail', 2)) {
                    $this->json(array('ok' => false, 'message' => 'too many requests'));
                }
                $this->ajaxFoodDetail(isset($_GET['guid']) ? (string)$_GET['guid'] : '');
            }
        }
        if (strtoupper(isset($_SERVER['REQUEST_METHOD']) ? $_SERVER['REQUEST_METHOD'] : 'GET') === 'POST') {
            if (!$this->checkCsrf()) {
                $error = $this->tr('TC_CSRF_ERROR');
            } else {
                $postMode = isset($_POST['do']) ? (string)$_POST['do'] : '';
                if ($postMode === 'export') {
                    $this->doExport();
                } elseif ($postMode === 'save_settings') {
                    $cfg = $this->saveSettings($cfg);
                    $message = $this->tr('TC_SAVED');
                    setGlobal('cycle_tabcalorieControl', 'sync_now');
                } elseif ($postMode === 'sync_now') {
                    $r = $this->sync(null, true);
                    $message = $r['ok'] ? $this->tr('TC_SYNC_DONE') . ' (' . $r['rows'] . ')' : $r['message'];
                    if (!$r['ok']) {
                        $error = $r['message'];
                    }
                } elseif ($postMode === 'write_queue') {
                    $r = $this->queueWriteback(isset($_POST['id']) ? (int)$_POST['id'] : 0);
                    $message = $r['message'];
                    if (!$r['ok']) {
                        $error = $r['message'];
                    }
                } elseif ($postMode === 'write_confirm') {
                    $id = (int)(isset($_POST['id']) ? $_POST['id'] : 0);
                    $cfgNow = $this->configAll();
                    if (!(int)$cfgNow['WRITEBACK_CONFIRM']) {
                        $error = $this->tr('TC_WRITEBACK_OFF');
                    } else {
                        TabCalorieStore::setWriteStatus($id, 'confirmed', 'confirmed by user');
                        $message = $this->tr('TC_SAVED');
                    }
                } elseif ($postMode === 'write_send') {
                    $r = $this->sendWriteQueue();
                    $message = $r['message'];
                    if (!$r['ok']) {
                        $error = $r['message'];
                    }
                } elseif ($postMode === 'write_cancel') {
                    $id = (int)(isset($_POST['id']) ? $_POST['id'] : 0);
                    TabCalorieStore::setWriteStatus($id, 'cancelled', 'cancelled by user');
                    $message = $this->tr('TC_SAVED');
                } elseif ($postMode === 'diagnose_run') {
                    $steps = $this->diagnose(true);
                    $failed = 0;
                    foreach ($steps as $s) {
                        if (empty($s['ok'])) {
                            $failed++;
                        }
                    }
                    $message = $this->tr('TC_DIAGNOSTICS') . ': ' . (count($steps) - $failed) . '/' . count($steps);
                    if ($failed) {
                        $error = $this->tr('TC_CONNECTION_FAIL');
                    }
                } elseif ($postMode === 'food_save') {
                    $r = $this->saveFoodFromPost();
                    $message = $this->tr('TC_SAVED');
                } elseif ($postMode === 'food_create') {
                    TabCalorieStore::saveFoodRecord($_POST);
                    $message = $this->tr('TC_SAVED');
                } elseif ($postMode === 'food_delete') {
                    TabCalorieStore::deleteFoodRecord(isset($_POST['id']) ? $_POST['id'] : 0);
                    $message = $this->tr('TC_SAVED');
                } elseif ($postMode === 'ns_test') {
                    $r = $this->testNightscout();
                    $message = $r['message'];
                    if (!$r['ok']) {
                        $error = $r['message'];
                    }
                }
            }
        }

        // Diagnostics may have cleared a stale error - reload before rendering
        // so the status badge matches the object on this very page load.
        $cfg = $this->configAll();
        $out['CONTENT'] = $this->render($tab, $cfg, $message, $error, true);
    }

    function usual(&$out)
    {
        $cfg = $this->configAll();
        $out['CONTENT'] = $this->render('overview', $cfg, '', '', false);
    }

    function render($tab, $cfg, $message, $error, $isAdmin)
    {
        $tabs = array('overview', 'activity', 'history', 'foods', 'chart', 'export', 'writeback', 'diagnostics', 'settings', 'instructions');
        if (!in_array($tab, $tabs, true)) {
            $tab = 'overview';
        }
        $html = $this->css();
        $html .= '<div class="tc-wrap">';
        $html .= '<div class="tc-head"><div class="tc-title"><img src="' . $this->e($this->basePath()) .
            'img/modules/tabcalorie.png" alt="" width="36" height="36">' .
            '<div><h2>' . $this->e($this->tr('TC_TITLE')) . '</h2>' .
            '<small>' . $this->e($this->tr('TC_SUBTITLE')) . '</small></div></div>' .
            '<div class="tc-status">' . $this->statusBadge($cfg) . '</div></div>';
        if ($message !== '') {
            $html .= '<div class="tc-alert tc-ok">' . $this->e($message) . '</div>';
        }
        if ($error !== '') {
            $html .= '<div class="tc-alert tc-err">' . $this->e($error) . '</div>';
        }
        $html .= '<ul class="tc-tabs">';
        foreach ($tabs as $t) {
            if ($t === 'settings' && !$isAdmin) {
                continue;
            }
            if ($t === 'writeback' && !$isAdmin) {
                continue;
            }
            $label = $this->tr('TC_TAB_' . strtoupper($t));
            $html .= '<li class="' . ($tab === $t ? 'active' : '') . '"><a href="' .
                $this->e($this->url('', array('tab' => $t))) . '">' . $this->e($label) . '</a></li>';
        }
        $html .= '</ul><div class="tc-body">';

        switch ($tab) {
            case 'overview':    $html .= $this->tabOverview($cfg); break;
            case 'activity':    $html .= $this->tabActivity($cfg); break;
            case 'history':     $html .= $this->tabHistory($cfg); break;
            case 'foods':       $html .= $this->tabFoods($isAdmin); break;
            case 'chart':       $html .= $this->tabChart(); break;
            case 'export':      $html .= $this->tabExport($cfg); break;
            case 'writeback':   $html .= $this->tabWriteback($cfg); break;
            case 'diagnostics': $html .= $this->tabDiagnostics(); break;
            case 'settings':    $html .= $this->tabSettings($cfg); break;
            case 'instructions':$html .= $this->tabInstructions(); break;
        }
        $html .= '</div></div>' . $this->js();
        return $html;
    }

    function statusBadge($cfg)
    {
        $last = $cfg['LAST_SYNC'] !== '' ? $cfg['LAST_SYNC'] : '—';
        $err = $cfg['LAST_ERROR'] !== '' ? $cfg['LAST_ERROR'] : '';
        $cls = $err !== '' ? 'tc-err' : 'tc-ok';
        return '<span class="tc-badge ' . $cls . '">' . $this->e($this->tr('TC_LAST_SYNC')) . ': ' .
            $this->e($last) . '</span>' . ($err !== '' ? '<div class="tc-small">' . $this->e(mb_substr($err, 0, 120)) . '</div>' : '');
    }

    /** Small progress bar. Returns empty string when the norm is not configured. */
    function bar($value, $norm, $unit = '', $showText = true)
    {
        if ((float)$norm <= 0) {
            return '';
        }
        $value = (float)$value;
        $pct = $value / (float)$norm * 100;
        $over = $pct > 100;
        $w = max(0, min(100, round($pct)));
        $html = '<div class="tc-bar' . ($over ? ' tc-bar-over' : '') . '"><span style="width:' . $w . '%"></span></div>';
        if ($showText) {
            $html .= '<span class="tc-bar-val">' . $value . ' / ' . $norm . ' ' . $this->e($unit) .
                ($over ? ' ⚠' : '') . '</span>';
        }
        return $html;
    }

    /** Demo preview built entirely in memory - never reads/writes real tables. */
    function demoOverview()
    {
        $date = date('Y-m-d');
        $diary = TabCalorieDemo::diary($date);
        $rows = TabCalorieStore::parseDiary($diary, $date);
        $slots = array();
        for ($i = 1; $i <= 6; $i++) {
            $slots[$i] = array('id' => $i, 'title_key' => TabCalorieStore::slotKey($i),
                'carbs' => 0.0, 'kcal' => 0.0, 'protein' => 0.0, 'fat' => 0.0,
                'fiber' => 0.0, 'sugar' => 0.0, 'count' => 0, 'present' => false);
        }
        foreach ($rows as $r) {
            $id = (int)$r['SLOT_ID'];
            if (!isset($slots[$id])) {
                continue;
            }
            $slots[$id]['carbs'] += $r['CARBOHYDRATE'];
            $slots[$id]['kcal'] += $r['ENERGY'];
            $slots[$id]['protein'] += $r['PROTEIN'];
            $slots[$id]['fat'] += $r['FAT'];
            $slots[$id]['count']++;
            $slots[$id]['present'] = true;
        }
        $main = $slots[1]['carbs'] + $slots[3]['carbs'] + $slots[5]['carbs'];
        $snacks = $slots[2]['carbs'] + $slots[4]['carbs'] + $slots[6]['carbs'];
        $dishes = array();
        foreach ($rows as $r) {
            $dishes[(int)$r['SLOT_ID']][] = array(
                'TITLE' => $r['TITLE'],
                'QUANTITY' => $r['QUANTITY'],
                'UNIT' => $r['UNIT'],
                'ENERGY' => $r['ENERGY'],
                'CARBOHYDRATE' => $r['CARBOHYDRATE'],
            );
        }
        return array(
            'date' => $date, 'partial' => true, 'slot_count' => 2,
            'dishes' => $dishes,
            'slots' => $slots,
            'groups' => array('main' => $main, 'snacks' => $snacks, 'day' => $main + $snacks),
            'total_carbs' => $main + $snacks,
            'total_kcal' => $slots[1]['kcal'] + $slots[3]['kcal'],
            'protein' => $slots[1]['protein'] + $slots[3]['protein'],
            'fat' => $slots[1]['fat'] + $slots[3]['fat'],
            'fiber' => 0, 'sugar' => 0, 'notes' => '',
            'steps' => 0, 'activity_kcal' => 0, 'activity_count' => 0,
        );
    }

    function tabOverview($cfg)
    {
        $ov = (int)$cfg['DEMO_MODE'] === 1 ? $this->demoOverview() : TabCalorieStore::overview();
        $xe = TabCalorieStore::round1($ov['total_carbs'] / max(1, (float)$cfg['GRAMS_PER_XE']));
        $share = (int)$cfg['MEAL_NORM_SHARE'];
        $targets = array(
            'carbs' => (float)$cfg['CARBS_TARGET'],
            'kcal' => (float)$cfg['CALORIES_TARGET'],
            'protein' => (float)$cfg['PROTEIN_TARGET'],
            'fat' => (float)$cfg['FAT_TARGET'],
        );
        $hasNorms = false;
        foreach ($targets as $t) {
            if ($t > 0) {
                $hasNorms = true;
            }
        }

        $html = '<div class="tc-row">';
        $html .= '<div class="tc-card"><div class="tc-card-title">' . $this->e($this->tr('TC_LAST_FILLED_DAY')) . '</div>' .
            '<div class="tc-big">' . $this->e($ov['date']) . '</div>' .
            '<div class="tc-small">' . ($ov['partial'] ? $this->e($this->tr('TC_PARTIAL_DAY')) : $this->e($this->tr('TC_FULL_DAY'))) .
            ' · ' . (int)$ov['slot_count'] . '/6</div></div>';

        $cards = array(
            array('TC_TOTAL_CARBS', $ov['total_carbs'], $targets['carbs'], 'г',
                $xe . ' ' . $this->e($this->tr('TC_XE'))),
            array('TC_TOTAL_KCAL', $ov['total_kcal'], $targets['kcal'], $this->tr('TC_KCAL'), ''),
            array('TC_PROTEIN', $ov['protein'], $targets['protein'], 'г', ''),
            array('TC_FAT', $ov['fat'], $targets['fat'], 'г', ''),
        );
        foreach ($cards as $c) {
            $html .= '<div class="tc-card"><div class="tc-card-title">' . $this->e($this->tr($c[0])) . '</div>' .
                '<div class="tc-big">' . $c[1] . ' <small>' . $this->e($c[3]) . '</small></div>' .
                $this->bar($c[1], $c[2], $c[3]) .
                ($c[4] !== '' ? '<div class="tc-small">' . $c[4] . '</div>' : '') . '</div>';
        }
        $html .= '</div>';

        if (!$hasNorms) {
            $html .= '<div class="tc-alert tc-err">' . $this->e($this->tr('TC_NORMS_HINT')) . '</div>';
        }

        $html .= '<div class="tc-panel"><div class="tc-panel-head">' . $this->e($this->tr('TC_MEALS')) .
            ($hasNorms ? ' <small>(' . $this->e($this->tr('TC_PER_MEAL_NORMS')) . ')</small>' : '') .
            '</div><div class="tc-table-wrap"><table class="tc-table tc-meals"><thead><tr><th>' .
            $this->e($this->tr('TC_MEAL')) . '</th><th>' . $this->e($this->tr('TC_CARBS')) .
            '</th><th>' . $this->e($this->tr('TC_KCAL')) . '</th><th>' . $this->e($this->tr('TC_PROTEIN')) .
            '</th><th>' . $this->e($this->tr('TC_FAT')) . '</th></tr></thead><tbody>';
        for ($i = 1; $i <= 6; $i++) {
            $s = $ov['slots'][$i];
            $cell = function ($value, $key, $unit) use ($targets, $share, $i) {
                return $value . ' ' . $unit . $this->bar($value,
                    TabCalorieStore::mealNorm($targets[$key], $i, $share), $unit);
            };
            $html .= '<tr class="' . ($s['present'] ? '' : 'tc-muted') . '"><td>' .
                $this->e($this->tr($s['title_key'])) . '</td><td>' .
                $cell($s['carbs'], 'carbs', 'г') . '</td><td>' .
                $cell($s['kcal'], 'kcal', $this->tr('TC_KCAL')) . '</td><td>' .
                $cell($s['protein'], 'protein', 'г') . '</td><td>' .
                $cell($s['fat'], 'fat', 'г') . '</td></tr>';
        }
        $html .= '</tbody><tfoot><tr><th>' . $this->e($this->tr('TC_MAIN_MEALS')) . '</th><td>' .
            $ov['groups']['main'] . ' г</td><td></td><td></td><td></td></tr><tr><th>' . $this->e($this->tr('TC_SNACKS')) .
            '</th><td>' . $ov['groups']['snacks'] . ' г</td><td></td><td></td><td></td></tr><tr><th>' .
            $this->e($this->tr('TC_TOTAL')) . '</th><td>' . $ov['groups']['day'] . ' г</td><td>' .
            $ov['total_kcal'] . '</td><td>' . $ov['protein'] . '</td><td>' . $ov['fat'] . '</td></tr></tfoot></table></div></div>';
        // Full dish list for the last filled day, grouped by meal.
        $dishes = isset($ov['dishes']) ? $ov['dishes'] : TabCalorieStore::dayDishes($ov['date']);
        $html .= '<div class="tc-panel"><div class="tc-panel-head">' .
            $this->e($this->tr('TC_DISHES_BY_MEAL')) . ' — ' . $this->e($ov['date']) .
            '</div><div class="tc-table-wrap"><table class="tc-table"><thead><tr><th>' .
            $this->e($this->tr('TC_FOOD')) . '</th><th>' . $this->e($this->tr('TC_QTY')) .
            '</th><th>' . $this->e($this->tr('TC_KCAL')) . '</th><th>' .
            $this->e($this->tr('TC_CARBS')) . '</th></tr></thead><tbody>';
        $hasDishes = false;
        for ($i = 1; $i <= 6; $i++) {
            if (empty($dishes[$i])) {
                continue;
            }
            $hasDishes = true;
            $html .= '<tr class="tc-group"><th colspan="4">' .
                $this->e($this->tr(TabCalorieStore::slotKey($i))) . '</th></tr>';
            foreach ($dishes[$i] as $d) {
                $qty = TabCalorieStore::num($d['QUANTITY']);
                $amount = $qty > 0 ? TabCalorieStore::round1($qty) . ' ' . $this->e($d['UNIT']) : $this->e($d['UNIT']);
                $html .= '<tr><td>' . $this->e($d['TITLE']) . '</td><td>' . $amount . '</td><td>' .
                    TabCalorieStore::round1($d['ENERGY']) . '</td><td>' .
                    TabCalorieStore::round1($d['CARBOHYDRATE']) . ' г</td></tr>';
            }
        }
        if (!$hasDishes) {
            $html .= '<tr><td colspan="4" class="tc-muted">' . $this->e($this->tr('TC_NO_DATA')) . '</td></tr>';
        }
        $html .= '</tbody></table></div></div>';

        if ($ov['notes'] !== '') {
            $html .= '<div class="tc-panel"><div class="tc-panel-head">' . $this->e($this->tr('TC_NOTES')) .
                '</div><div class="tc-panel-body">' . nl2br($this->e($ov['notes'])) . '</div></div>';
        }
        if ($ov['activity_count'] > 0) {
            $html .= '<div class="tc-alert tc-ok">' . $this->e($this->tr('TC_STEPS')) . ': ' .
                $ov['steps'] . ' · ' . $this->e($this->tr('TC_ACTIVITY_KCAL')) . ': ' .
                $ov['activity_kcal'] . ' <a href="' . $this->e($this->url('', array('tab' => 'activity'))) . '">' .
                $this->e($this->tr('TC_TAB_ACTIVITY')) . ' →</a></div>';
        }
        return $html;
    }

    /** Activity imported from the TK diary (Health Connect -> TK -> plugin). */
    function tabActivity($cfg = array())
    {
        $ov = (int)(isset($cfg['DEMO_MODE']) ? $cfg['DEMO_MODE'] : 0) === 1
            ? $this->demoOverview() : TabCalorieStore::overview();
        if (isset($cfg['ACTIVITY_ENABLED']) && (int)$cfg['ACTIVITY_ENABLED'] !== 1) {
            return '<div class="tc-alert tc-err">' . $this->e($this->tr('TC_ACTIVITY_OFF')) . '</div>';
        }
        $rows = TabCalorieStore::activityRows($ov['date']);
        $since = date('Y-m-d', time() - 6 * 86400);
        $week = TabCalorieStore::dayRows($since, date('Y-m-d'));
        $wSteps = 0.0;
        $wKcal = 0.0;
        foreach ($week as $d) {
            $wSteps += TabCalorieStore::num($d['STEPS']);
            $wKcal += TabCalorieStore::num($d['ACTIVITY_KCAL']);
        }
        $html = '<div class="tc-row">';
        $html .= '<div class="tc-card"><div class="tc-card-title">' . $this->e($this->tr('TC_LAST_FILLED_DAY')) .
            '</div><div class="tc-big">' . $this->e($ov['date']) . '</div></div>';
        $html .= '<div class="tc-card"><div class="tc-card-title">' . $this->e($this->tr('TC_STEPS')) .
            '</div><div class="tc-big">' . $ov['steps'] . '</div><div class="tc-small">' .
            $this->e($this->tr('TC_WEEK')) . ': ' . TabCalorieStore::round1($wSteps) . '</div></div>';
        $html .= '<div class="tc-card"><div class="tc-card-title">' . $this->e($this->tr('TC_ACTIVITY_KCAL')) .
            '</div><div class="tc-big">' . $ov['activity_kcal'] . '</div><div class="tc-small">' .
            $this->e($this->tr('TC_WEEK')) . ': ' . TabCalorieStore::round1($wKcal) .
            ' · ' . $this->e($this->tr('TC_ACTIVITIES')) . ': ' . $ov['activity_count'] . '</div></div>';
        $html .= '</div>';

        $html .= '<div class="tc-panel"><div class="tc-panel-head">' . $this->e($this->tr('TC_ACTIVITY_DAY')) .
            ' — ' . $this->e($ov['date']) . '</div><div class="tc-table-wrap"><table class="tc-table"><thead><tr><th>' .
            $this->e($this->tr('TC_FOOD')) . '</th><th>' . $this->e($this->tr('TC_TYPE')) .
            '</th><th>' . $this->e($this->tr('TC_STEPS')) . '</th><th>' . $this->e($this->tr('TC_ACTIVITY_KCAL')) .
            '</th><th>' . $this->e($this->tr('TC_DURATION')) . '</th></tr></thead><tbody>';
        foreach ($rows as $a) {
            $html .= '<tr><td>' . $this->e($a['TITLE']) . '</td><td>' . $this->e($a['TYPE']) .
                '</td><td>' . $a['STEPS'] . '</td><td>' . $a['ENERGY'] . ' ' . $this->e($a['UNIT']) .
                '</td><td>' . $a['DURATION'] . '</td></tr>';
        }
        if (!$rows) {
            $html .= '<tr><td colspan="5" class="tc-muted">' . $this->e($this->tr('TC_NO_ACTIVITY')) . '</td></tr>';
        }
        $html .= '</tbody></table></div></div>';

        $html .= '<div class="tc-panel"><div class="tc-panel-head">' . $this->e($this->tr('TC_WEEK')) .
            ' (7)</div><div class="tc-table-wrap"><table class="tc-table"><thead><tr><th>' .
            $this->e($this->tr('TC_DATE')) . '</th><th>' . $this->e($this->tr('TC_STEPS')) .
            '</th><th>' . $this->e($this->tr('TC_ACTIVITY_KCAL')) . '</th><th>' .
            $this->e($this->tr('TC_ACTIVITIES')) . '</th></tr></thead><tbody>';
        foreach (array_reverse($week) as $d) {
            $html .= '<tr><td>' . $this->e(TabCalorieExporter::dateUa($d['DAY_DATE'])) . '</td><td>' .
                $d['STEPS'] . '</td><td>' . $d['ACTIVITY_KCAL'] . '</td><td>' .
                (int)$d['ACTIVITY_COUNT'] . '</td></tr>';
        }
        $html .= '</tbody></table></div></div>';
        $html .= '<div class="tc-alert tc-ok">' . $this->e($this->tr('TC_ACTIVITY_HINT')) . '</div>';
        return $html;
    }

    function tabHistory($cfg = array())
    {
        $rows = TabCalorieStore::dayRows(date('Y-m-d', time() - 30 * 86400), date('Y-m-d'));
        $html = '<div class="tc-panel"><div class="tc-panel-head">' . $this->e($this->tr('TC_HISTORY')) .
            ' (30)</div><div class="tc-table-wrap"><table class="tc-table"><thead><tr><th>' .
            $this->e($this->tr('TC_DATE')) . '</th><th>' . $this->e($this->tr('TC_CARBS')) .
            '</th><th>' . $this->e($this->tr('TC_XE')) . '</th><th>' . $this->e($this->tr('TC_KCAL')) .
            '</th><th>' . $this->e($this->tr('TC_FIBER')) . '</th></tr></thead><tbody>';
        foreach (array_reverse($rows) as $r) {
            $html .= '<tr><td>' . $this->e(TabCalorieExporter::dateUa($r['DAY_DATE'])) . '</td><td>' .
                $r['CARBOHYDRATE'] . ' г</td><td>' . TabCalorieStore::round1($r['CARBOHYDRATE'] / 10) .
                '</td><td>' . $r['ENERGY'] . '</td><td>' . $r['FIBER'] . '</td></tr>';
        }
        if (!$rows) {
            $html .= '<tr><td colspan="5" class="tc-muted">' . $this->e($this->tr('TC_NO_DATA')) . '</td></tr>';
        }
        $html .= '</tbody></table></div></div>';

        if (!empty($cfg['WRITEBACK_ENABLED'])) {
            $entries = SQLSelect("SELECT ID, ENTRY_DATE, SLOT_ID, TITLE, QUANTITY, UNIT
                                  FROM tabcalorie_entries ORDER BY ENTRY_DATE DESC, ID DESC LIMIT 20");
            $html .= '<div class="tc-panel"><div class="tc-panel-head">' .
                $this->e($this->tr('TC_WRITEBACK_QUEUE')) . '</div><div class="tc-table-wrap">' .
                '<table class="tc-table"><thead><tr><th>' . $this->e($this->tr('TC_DATE')) .
                '</th><th>' . $this->e($this->tr('TC_MEAL')) . '</th><th>' .
                $this->e($this->tr('TC_FOOD')) . '</th><th></th></tr></thead><tbody>';
            foreach ($entries as $e) {
                $html .= '<tr><td>' . $this->e(TabCalorieExporter::dateUa($e['ENTRY_DATE'])) . '</td><td>#' .
                    (int)$e['SLOT_ID'] . '</td><td>' . $this->e($e['TITLE']) . '</td><td>' .
                    '<form method="post" class="tc-inline"><input type="hidden" name="csrf" value="' .
                    $this->e($this->csrfToken()) . '"><input type="hidden" name="do" value="write_queue">' .
                    '<input type="hidden" name="id" value="' . (int)$e['ID'] . '">' .
                    '<button type="submit" class="tc-btn tc-btn-sm">' .
                    $this->e($this->tr('TC_QUEUE_WRITE')) . '</button></form></td></tr>';
            }
            $html .= '</tbody></table></div></div>';
        }
        return $html;
    }

    function tabFoods($isAdmin)
    {
        $rows = SQLSelect("SELECT * FROM tabcalorie_foods ORDER BY TITLE LIMIT 200");
        $editId = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;
        $edit = $editId ? TabCalorieStore::foodRecord($editId) : array();
        $html = '';

        if ($isAdmin) {
            $html .= '<div class="tc-panel"><div class="tc-panel-head">' . $this->e($this->tr('TC_FOOD_FORM')) .
                '</div><div class="tc-panel-body">';
            $html .= '<div class="tc-search"><input type="text" id="tc-food-q" placeholder="' .
                $this->e($this->tr('TC_FOOD_SEARCH')) . '" style="min-width:260px"> ' .
                '<button type="button" class="tc-btn" id="tc-food-search">' .
                $this->e($this->tr('TC_FOOD_SEARCH_BTN')) . '</button>' .
                '<div id="tc-food-results" class="tc-results"></div></div>';
            $html .= '<form method="post" id="tc-food-form" class="tc-form tc-form-grid">' .
                '<input type="hidden" name="csrf" value="' . $this->e($this->csrfToken()) . '">' .
                '<input type="hidden" name="do" value="food_create">' .
                '<input type="hidden" name="ID" value="' . (int)(isset($edit['ID']) ? $edit['ID'] : 0) . '">';
            $v = function ($key, $default = '') use ($edit) {
                return isset($edit[$key]) ? $edit[$key] : $default;
            };
            $fields = array(
                array('TITLE', $this->tr('TC_FOOD'), 'text'),
                array('FOOD_ID', $this->tr('TC_FOOD_ID'), 'text'),
                array('FOOD_GUID', $this->tr('TC_FOOD_GUID'), 'text'),
                array('FOOD_URL', $this->tr('TC_FOOD_URL'), 'text'),
                array('UNIT', $this->tr('TC_UNIT'), 'text'),
                array('UNIT_GUID', $this->tr('TC_UNIT_GUID'), 'text'),
                array('DEFAULT_MULTIPLIER', $this->tr('TC_DEFAULT_GRAMS'), 'number'),
                array('DEFAULT_SLOT', $this->tr('TC_DEFAULT_SLOT'), 'number'),
                array('ENERGY_100', $this->tr('TC_KCAL') . '/100', 'number'),
                array('PROTEIN_100', $this->tr('TC_PROTEIN') . '/100', 'number'),
                array('FAT_100', $this->tr('TC_FAT') . '/100', 'number'),
                array('CARB_100', $this->tr('TC_CARBS') . '/100', 'number'),
                array('FIBER_100', $this->tr('TC_FIBER') . '/100', 'number'),
                array('SUGAR_100', $this->tr('TC_SUGAR') . '/100', 'number'),
                array('GI', $this->tr('TC_GI'), 'number'),
                array('NOTES', $this->tr('TC_NOTES'), 'text'),
            );
            foreach ($fields as $f) {
                $html .= '<label class="tc-field"><span>' . $this->e($f[1]) . '</span><input type="' .
                    $f[2] . '" name="' . $f[0] . '" value="' . $this->e($v($f[0], $f[2] === 'number' ? 0 : '')) .
                    '"></label>';
            }
            $html .= '<label class="tc-field"><span>' . $this->e($this->tr('TC_UNIT_OPTIONS')) .
                '</span><input type="text" name="UNIT_OPTIONS" value="' . $this->e($v('UNIT_OPTIONS')) .
                '" placeholder=\'[{"id":"0000000000000001","title":"1 g","multiplier":1}]\'></label>';
            $html .= '<div class="tc-actions"><button type="submit" class="tc-btn tc-btn-primary">' .
                $this->e($this->tr('TC_SAVE')) . '</button> <a class="tc-btn" href="' .
                $this->e($this->url('', array('tab' => 'foods'))) . '">' .
                $this->e($this->tr('TC_CANCEL')) . '</a></div></form></div></div>';
            $html .= $this->foodsJs();
        }

        $html .= '<div class="tc-panel"><div class="tc-panel-head">' . $this->e($this->tr('TC_FOODS')) .
            '</div><div class="tc-table-wrap"><table class="tc-table"><thead><tr><th>' .
            $this->e($this->tr('TC_FOOD')) . '</th><th>ID / URL</th><th>' . $this->e($this->tr('TC_KCAL')) .
            '</th><th>' . $this->e($this->tr('TC_PROTEIN')) . '</th><th>' . $this->e($this->tr('TC_FAT')) .
            '</th><th>' . $this->e($this->tr('TC_CARBS')) . '</th><th>' . $this->e($this->tr('TC_GI')) .
            '</th><th>' . $this->e($this->tr('TC_NOTES')) . '</th>' . ($isAdmin ? '<th></th>' : '') .
            '</tr></thead><tbody>';
        foreach ($rows as $r) {
            $html .= '<tr><td>' . $this->e($r['TITLE']) . '<div class="tc-small">/100 ' .
                $this->e($r['UNIT']) . '</div></td><td class="tc-small">' . $this->e($r['FOOD_ID']) .
                '<br>' . $this->e($r['FOOD_URL']) . '</td><td>' . $r['ENERGY_100'] . '</td><td>' .
                $r['PROTEIN_100'] . '</td><td>' . $r['FAT_100'] . '</td><td>' . $r['CARB_100'] .
                '</td><td>' . $r['GI'] . '</td><td>' . $this->e($r['NOTES']) . '</td>';
            if ($isAdmin) {
                $html .= '<td><a class="tc-btn tc-btn-sm" href="' .
                    $this->e($this->url('', array('tab' => 'foods', 'edit' => $r['ID']))) . '">' .
                    $this->e($this->tr('TC_EDIT')) . '</a>' .
                    '<form method="post" class="tc-inline"><input type="hidden" name="csrf" value="' .
                    $this->e($this->csrfToken()) . '"><input type="hidden" name="do" value="food_delete">' .
                    '<input type="hidden" name="id" value="' . (int)$r['ID'] . '">' .
                    '<button type="submit" class="tc-btn tc-btn-sm" onclick="return confirm(\'' .
                    $this->e($this->tr('TC_CONFIRM_DELETE')) . '\');">' .
                    $this->e($this->tr('TC_DELETE')) . '</button></form></td>';
            }
            $html .= '</tr>';
        }
        if (!$rows) {
            $html .= '<tr><td colspan="' . ($isAdmin ? '10' : '9') . '" class="tc-muted">' .
                $this->e($this->tr('TC_NO_DATA')) . '</td></tr>';
        }
        $html .= '</tbody></table></div></div>';
        return $html;
    }

    /** Autocomplete → service fields of the chosen food (stored locally). */
    function foodsJs()
    {
        $searchUrl = $this->url('food_search_ajax');
        $detailUrl = $this->url('food_detail_ajax');
        $flags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT;
        return '<script>
(function(){
  var sUrl=' . json_encode($searchUrl, $flags) . ', dUrl=' . json_encode($detailUrl, $flags) . ';
  document.addEventListener("DOMContentLoaded",function(){
    var q=document.getElementById("tc-food-q"), btn=document.getElementById("tc-food-search");
    var out=document.getElementById("tc-food-results"), form=document.getElementById("tc-food-form");
    if(!q||!btn||!out||!form)return;
    function clear(el){while(el.firstChild)el.removeChild(el.firstChild);}
    function text(el,cls,value){var s=document.createElement("span");if(cls)s.className=cls;s.textContent=value||"";el.appendChild(s);}
    function set(name,value){var el=form.querySelector("[name="+name+"]");if(el)el.value=value;}
    function fill(item){
      set("TITLE",item.title||"");set("FOOD_ID",item.id||"");set("FOOD_GUID",item.id||"");
      set("FOOD_URL",item.url||"");set("UNIT",item.unit||"g");
      clear(out);text(out,"tc-small",item.title||"");
      fetch(dUrl+"&guid="+encodeURIComponent(item.id)).then(function(r){return r.json();}).then(function(j){
        if(!j.ok||!j.form)return;
        var f=j.form;
        var units=(f.unitOptions||[]).map(function(u){return {id:u.id,title:u.title,multiplier:u.multiplier};});
        if(units.length)set("UNIT_OPTIONS",JSON.stringify(units));
        if(f.unitGuid)set("UNIT_GUID",f.unitGuid);
        if(f.multiplier)set("DEFAULT_MULTIPLIER",f.multiplier);
      }).catch(function(){});
    }
    btn.onclick=function(){
      clear(out);text(out,"tc-small","...");
      fetch(sUrl+"&q="+encodeURIComponent(q.value)).then(function(r){return r.json();}).then(function(j){
        clear(out);
        if(!j.ok){text(out,"tc-err",j.message||"error");return;}
        if(!(j.items||[]).length){text(out,"tc-small","-");return;}
        (j.items||[]).forEach(function(it){
          var a=document.createElement("button");a.type="button";a.className="tc-btn tc-btn-sm";
          a.textContent=it.title||"";a.style.margin="2px";a.onclick=function(){fill(it);};
          out.appendChild(a);
        });
      }).catch(function(){clear(out);text(out,"tc-err","error");});
    };
  });
})();
</script>';
    }

    function tabChart()
    {
        $url = $this->basePath() . TabCalorieChart::standardUrl(7);
        $html = '<div class="tc-panel"><div class="tc-panel-head">' . $this->e($this->tr('TC_CHART')) . '</div>' .
            '<div class="tc-panel-body">' .
            '<a class="tc-btn tc-btn-primary" href="' . $this->e($url) . '" target="_blank" rel="noopener">' .
            $this->e($this->tr('TC_CREATE_CHART')) . '</a> ' .
            '<span class="tc-small">' . $this->e($this->tr('TC_CHART_HINT')) . '</span>' .
            '<div class="tc-chart">' . TabCalorieChart::svg(14) . '</div></div></div>';
        return $html;
    }

    function tabExport($cfg)
    {
        $html = '<div class="tc-panel"><div class="tc-panel-head">' . $this->e($this->tr('TC_EXPORT')) . '</div>' .
            '<div class="tc-panel-body"><form method="post" class="tc-form">';
        $html .= '<input type="hidden" name="csrf" value="' . $this->e($this->csrfToken()) . '">' .
            '<input type="hidden" name="do" value="export">';
        $html .= '<label>' . $this->e($this->tr('TC_PERIOD')) . ' <select name="period">';
        foreach (array('day', 'week', 'month', 'year') as $p) {
            $html .= '<option value="' . $p . '">' . $this->e($this->tr('TC_PERIOD_' . strtoupper($p))) . '</option>';
        }
        $html .= '</select></label> <label>' . $this->e($this->tr('TC_VIEW')) . ' <select name="kind">' .
            '<option value="daily">' . $this->e($this->tr('TC_DAILY')) . '</option>' .
            '<option value="detailed">' . $this->e($this->tr('TC_DETAILED')) . '</option></select></label> ';
        $html .= '<button type="submit" class="tc-btn tc-btn-primary">' . $this->e($this->tr('TC_DOWNLOAD_CSV')) . '</button>' .
            '<div class="tc-small">' . $this->e($this->tr('TC_CSV_HINT')) . ' (BOM, ' .
            $this->e($cfg['CSV_DELIMITER']) . ')</div></form></div></div>';
        return $html;
    }

    function tabWriteback($cfg)
    {
        $enabled = (int)$cfg['WRITEBACK_ENABLED'] === 1;
        $html = '<div class="tc-alert ' . ($enabled ? 'tc-ok' : 'tc-err') . '">' .
            $this->e($enabled ? $this->tr('TC_WRITEBACK_ON') : $this->tr('TC_WRITEBACK_OFF')) . '</div>';
        $allRows = TabCalorieStore::writeQueue(50);
        $rows = array();
        foreach ($allRows as $r) {
            $p = json_decode($r['PAYLOAD'], true);
            if (is_array($p) && isset($p['kind']) && $p['kind'] === 'ns') {
                continue;
            }
            $rows[] = $r;
        }
        $html .= '<div class="tc-panel"><div class="tc-panel-head">' . $this->e($this->tr('TC_WRITEBACK_QUEUE')) .
            '</div><div class="tc-table-wrap"><table class="tc-table"><thead><tr><th>ID</th><th>' .
            $this->e($this->tr('TC_FOOD')) . '</th><th>' . $this->e($this->tr('TC_DATE')) .
            '</th><th>' . $this->e($this->tr('TC_STATUS')) . '</th><th></th></tr></thead><tbody>';
        foreach ($rows as $r) {
            $p = json_decode($r['PAYLOAD'], true);
            $p = is_array($p) ? $p : array();
            $html .= '<tr><td>' . (int)$r['ID'] . '</td><td>' . $this->e(isset($p['title']) ? $p['title'] : '') .
                '</td><td>' . $this->e(isset($p['date']) ? $p['date'] : '') . '</td><td>' .
                $this->e($r['STATUS']) . '</td><td>';
            if ($enabled && in_array($r['STATUS'], array('draft', 'confirmed'), true)) {
                $hidden = '<input type="hidden" name="csrf" value="' . $this->e($this->csrfToken()) .
                    '"><input type="hidden" name="id" value="' . (int)$r['ID'] . '">';
                if ($r['STATUS'] === 'draft') {
                    $html .= '<form method="post" class="tc-inline">' . $hidden .
                        '<input type="hidden" name="do" value="write_confirm">' .
                        '<button type="submit" class="tc-btn tc-btn-sm">' .
                        $this->e($this->tr('TC_CONFIRM_SEND')) . '</button></form>';
                } else {
                    $html .= '<form method="post" class="tc-inline">' . $hidden .
                        '<input type="hidden" name="do" value="write_send">' .
                        '<button type="submit" class="tc-btn tc-btn-sm">' .
                        $this->e($this->tr('TC_SEND')) . '</button></form>';
                }
                $html .= '<form method="post" class="tc-inline">' . $hidden .
                    '<input type="hidden" name="do" value="write_cancel">' .
                    '<button type="submit" class="tc-btn tc-btn-sm">' .
                    $this->e($this->tr('TC_CANCEL')) . '</button></form>';
            }
            $html .= '</td></tr>';
        }
        if (!$rows) {
            $html .= '<tr><td colspan="5" class="tc-muted">' . $this->e($this->tr('TC_NO_DATA')) . '</td></tr>';
        }
        $html .= '</tbody></table></div></div>';
        return $html;
    }

    function tabDiagnostics()
    {
        $steps = $this->diagnose();
        $html = '<div class="tc-panel"><div class="tc-panel-head">' . $this->e($this->tr('TC_DIAGNOSTICS')) .
            '</div><div class="tc-panel-body"><table class="tc-table"><thead><tr><th>' .
            $this->e($this->tr('TC_CHECK')) . '</th><th>' . $this->e($this->tr('TC_RESULT')) .
            '</th><th>' . $this->e($this->tr('TC_DETAILS')) . '</th></tr></thead><tbody>';
        foreach ($steps as $s) {
            $html .= '<tr><td>' . $this->e($s['step']) . '</td><td>' .
                ($s['ok'] ? '<span class="tc-badge tc-ok">OK</span>' : '<span class="tc-badge tc-err">FAIL</span>') .
                '</td><td>' . $this->e($s['message']) . '</td></tr>';
        }
        $html .= '</tbody></table><div class="tc-small">' . $this->e($this->tr('TC_DIAG_HINT')) . '</div>';
        $html .= '<form method="post" class="tc-inline" style="margin-top:10px">' .
            '<input type="hidden" name="csrf" value="' . $this->e($this->csrfToken()) . '">' .
            '<input type="hidden" name="do" value="diagnose_run">' .
            '<button type="submit" class="tc-btn tc-btn-primary">' .
            $this->e($this->tr('TC_CHECK_CONNECTION')) . '</button></form></div></div>';
        return $html;
    }

    function tabSettings($cfg)
    {
        $html = '<div class="tc-panel"><div class="tc-panel-head">' . $this->e($this->tr('TC_SETTINGS')) .
            '</div><div class="tc-panel-body"><form method="post" class="tc-form tc-form-grid">' .
            '<input type="hidden" name="csrf" value="' . $this->e($this->csrfToken()) . '">' .
            '<input type="hidden" name="do" value="save_settings">';
        $fields = array(
            array('select', 'PROVIDER', $this->tr('TC_PROVIDER'), array('ua' => 'tablycjakalorijnosti.com.ua', 'cz' => 'kaloricketabulky.cz', 'custom' => $this->tr('TC_CUSTOM'))),
            array('text', 'BASE_URL', $this->tr('TC_BASE_URL')),
            array('text', 'EMAIL', $this->tr('TC_EMAIL')),
            array('password', 'PASSWORD', $this->tr('TC_PASSWORD')),
            array('number', 'SYNC_INTERVAL_MIN', $this->tr('TC_INTERVAL')),
            array('number', 'SYNC_DAYS', $this->tr('TC_SYNC_DAYS')),
            array('number', 'RETENTION_DAYS', $this->tr('TC_RETENTION')),
            array('number', 'GRAMS_PER_XE', $this->tr('TC_G_PER_XE')),
            array('number', 'CARBS_TARGET', $this->tr('TC_TARGET')),
            array('number', 'CALORIES_TARGET', $this->tr('TC_CALORIES_TARGET')),
            array('number', 'PROTEIN_TARGET', $this->tr('TC_PROTEIN_TARGET')),
            array('number', 'FAT_TARGET', $this->tr('TC_FAT_TARGET')),
            array('number', 'MEAL_NORM_SHARE', $this->tr('TC_MEAL_NORM_SHARE')),
            array('text', 'HOOK_PROPERTY', $this->tr('TC_HOOK')),
            array('number', 'HOOK_DEBOUNCE_SEC', $this->tr('TC_HOOK_DEBOUNCE')),
            array('select', 'LANGUAGE', $this->tr('TC_LANGUAGE'), array('uk' => 'Українська', 'en' => 'English', 'ru' => 'Русский')),
            array('select', 'CSV_DELIMITER', $this->tr('TC_CSV_DELIMITER'), array(';' => ';', ',' => ',', "\t" => 'TAB')),
            array('number', 'LOG_RETENTION_DAYS', $this->tr('TC_LOG_RETENTION')),
            array('number', 'LOG_MAX_ROWS', $this->tr('TC_LOG_MAX_ROWS')),
            array('number', 'WRITE_QUEUE_TTL_DAYS', $this->tr('TC_QUEUE_TTL')),
        );
        foreach ($fields as $f) {
            list($type, $key, $label) = $f;
            $val = isset($cfg[$key]) ? $cfg[$key] : '';
            if ($key === 'PASSWORD') {
                $val = '';
            }
            $html .= '<label class="tc-field"><span>' . $this->e($label) . '</span>';
            if ($type === 'select') {
                $html .= '<select name="' . $key . '">';
                foreach ($f[3] as $ov => $ol) {
                    $html .= '<option value="' . $this->e($ov) . '"' . ((string)$cfg[$key] === (string)$ov ? ' selected' : '') . '>' .
                        $this->e($ol) . '</option>';
                }
                $html .= '</select>';
            } else {
                $ph = $key === 'PASSWORD' ? '******' : '';
                $html .= '<input type="' . $type . '" name="' . $key . '" value="' . $this->e($val) .
                    '" placeholder="' . $this->e($ph) . '">';
            }
            $html .= '</label>';
        }
        $checks = array(
            'SYNC_ENABLED' => $this->tr('TC_SYNC_ENABLED'),
            'HOOK_ENABLED' => $this->tr('TC_HOOK_ENABLED'),
            'WRITEBACK_ENABLED' => $this->tr('TC_WRITEBACK_ENABLED'),
            'WRITEBACK_CONFIRM' => $this->tr('TC_WRITEBACK_CONFIRM'),
            'NS_ENABLED' => $this->tr('TC_NS_ENABLED'),
            'DEMO_MODE' => $this->tr('TC_DEMO_MODE'),
            'ACTIVITY_ENABLED' => $this->tr('TC_ACTIVITY_ENABLED'),
        );
        foreach ($checks as $key => $label) {
            $html .= '<label class="tc-check"><input type="checkbox" name="' . $key . '" value="1"' .
                ((int)$cfg[$key] === 1 ? ' checked' : '') . '> ' . $this->e($label) . '</label>';
        }
        $html .= '<label class="tc-field"><span>Nightscout URL</span><input type="url" name="NS_URL" value="' .
            $this->e($cfg['NS_URL']) . '" placeholder="https://ns.example.org"></label>';
        $html .= '<label class="tc-field"><span>Nightscout API secret</span><input type="password" name="NS_API_SECRET" value="" placeholder="******"></label>';
        $html .= '<div class="tc-actions"><button type="submit" class="tc-btn tc-btn-primary">' .
            $this->e($this->tr('TC_SAVE')) . '</button></div></form>';
        $html .= '<div class="tc-actions"><form method="post" class="tc-inline"><input type="hidden" name="csrf" value="' .
            $this->e($this->csrfToken()) . '"><input type="hidden" name="do" value="sync_now">' .
            '<button type="submit" class="tc-btn">' . $this->e($this->tr('TC_SYNC_NOW')) . '</button></form>' .
            '<form method="post" class="tc-inline"><input type="hidden" name="csrf" value="' .
            $this->e($this->csrfToken()) . '"><input type="hidden" name="do" value="ns_test">' .
            '<button type="submit" class="tc-btn">' . $this->e($this->tr('TC_NS_TEST')) . '</button></form></div>';
        $html .= '</div></div>';
        return $html;
    }

    function tabInstructions()
    {
        $html = '<div class="tc-panel"><div class="tc-panel-head">' . $this->e($this->tr('TC_INSTRUCTIONS')) .
            '</div><div class="tc-panel-body"><ol class="tc-steps">';
        foreach (array('TC_STEP1', 'TC_STEP2', 'TC_STEP3', 'TC_STEP4', 'TC_STEP5', 'TC_STEP6') as $k) {
            $html .= '<li>' . $this->e($this->tr($k)) . '</li>';
        }
        $html .= '</ol><div class="tc-alert tc-err">' . $this->e($this->tr('TC_DISCLAIMER')) . '</div></div></div>';
        return $html;
    }

    // ---------------------------------------------------------------- actions

    function saveSettings($cfg)
    {
        $oldCfg = $cfg;
        $textKeys = array('BASE_URL', 'EMAIL', 'HOOK_PROPERTY', 'NS_URL');
        foreach ($textKeys as $k) {
            if (isset($_POST[$k])) {
                $cfg[$k] = mb_substr(trim(strip_tags((string)$_POST[$k])), 0, 255);
            }
        }
        if (isset($_POST['PASSWORD']) && trim((string)$_POST['PASSWORD']) !== '') {
            $cfg['PASSWORD'] = (string)$_POST['PASSWORD'];
        }
        if (isset($_POST['NS_API_SECRET']) && trim((string)$_POST['NS_API_SECRET']) !== '') {
            $cfg['NS_API_SECRET'] = (string)$_POST['NS_API_SECRET'];
        }
        if (isset($_POST['PROVIDER'])) {
            $p = (string)$_POST['PROVIDER'];
            $cfg['PROVIDER'] = in_array($p, array('ua', 'cz', 'custom'), true) ? $p : 'ua';
            $urls = array('ua' => 'https://www.tablycjakalorijnosti.com.ua',
                'cz' => 'https://www.kaloricketabulky.cz');
            if ($p !== 'custom' && isset($urls[$p])) {
                $cfg['BASE_URL'] = $urls[$p];
            }
        }
        if ($cfg['NS_URL'] !== '' && !TabCalorieStore::validRemoteUrl($cfg['NS_URL'])) {
            $cfg['NS_URL'] = ''; // SSRF guard
        }
        if ($cfg['PROVIDER'] === 'custom' && !TabCalorieStore::validRemoteUrl($cfg['BASE_URL'])) {
            $cfg['BASE_URL'] = $this->defaults()['BASE_URL'];
            $cfg['PROVIDER'] = 'ua';
        }
        $numKeys = array('SYNC_INTERVAL_MIN' => array(15, 10080, 60), 'SYNC_DAYS' => array(1, 90, 2),
            'RETENTION_DAYS' => array(30, 3650, 1825), 'GRAMS_PER_XE' => array(5, 20, 10),
            'CARBS_TARGET' => array(0, 1000, 120), 'CALORIES_TARGET' => array(0, 6000, 1800),
            'PROTEIN_TARGET' => array(0, 400, 90), 'FAT_TARGET' => array(0, 300, 60),
            'MEAL_NORM_SHARE' => array(10, 33, 25), 'HOOK_DEBOUNCE_SEC' => array(0, 86400, 60),
            'LOG_RETENTION_DAYS' => array(1, 365, 30), 'LOG_MAX_ROWS' => array(100, 50000, 2000),
            'WRITE_QUEUE_TTL_DAYS' => array(1, 365, 7));
        foreach ($numKeys as $k => $rule) {
            if (isset($_POST[$k])) {
                $v = (int)$_POST[$k];
                $cfg[$k] = max($rule[0], min($rule[1], $v));
            }
        }
        if (isset($_POST['LANGUAGE'])) {
            $cfg['LANGUAGE'] = in_array($_POST['LANGUAGE'], array('uk', 'en', 'ru'), true) ? $_POST['LANGUAGE'] : 'uk';
        }
        if (isset($_POST['CSV_DELIMITER'])) {
            $cfg['CSV_DELIMITER'] = in_array($_POST['CSV_DELIMITER'], array(';', ',', "\t"), true) ? $_POST['CSV_DELIMITER'] : ';';
        }
        foreach (array('SYNC_ENABLED', 'HOOK_ENABLED', 'WRITEBACK_ENABLED', 'WRITEBACK_CONFIRM',
            'NS_ENABLED', 'DEMO_MODE', 'ACTIVITY_ENABLED') as $k) {
            $cfg[$k] = (isset($_POST[$k]) && $_POST[$k] === '1') ? 1 : 0;
        }
        if ($cfg['WRITEBACK_ENABLED'] && !$cfg['WRITEBACK_CONFIRM']) {
            $cfg['WRITEBACK_CONFIRM'] = 1; // confirmation is mandatory
        }
        if ($cfg['DEMO_MODE']) {
            $cfg['WRITEBACK_ENABLED'] = 0;
            $cfg['NS_ENABLED'] = 0; // never send demo data anywhere
        }
        // Connection details changed: a previous "login failed" state is no
        // longer meaningful. Reset it instead of waiting for the next sync.
        foreach (array('BASE_URL', 'PROVIDER', 'EMAIL', 'PASSWORD') as $k) {
            if ((string)$oldCfg[$k] !== (string)$cfg[$k]) {
                $cfg['LAST_ERROR'] = '';
                $cfg['CIRCUIT_UNTIL'] = 0;
                $cfg['FAIL_COUNT'] = 0;
                TabCalorieStore::log('settings', 'ok', 0, 0, 'connection settings changed, error reset');
                break;
            }
        }
        $this->config = $cfg;
        $this->saveConfig();
        TabCalorieStore::refreshStatusObject($cfg);
        if ($cfg['SYNC_ENABLED']) {
            setGlobal('cycle_tabcalorieControl', 'sync_now');
        }
        TabCalorieStore::log('settings', 'ok', 0, 0, 'settings saved');
        return $cfg;
    }

    function saveFoodFromPost()
    {
        $guid = isset($_POST['guid']) ? (string)$_POST['guid'] : '';
        if ($guid === '') {
            return false;
        }
        $rec = SQLSelectOne("SELECT * FROM tabcalorie_foods WHERE FOOD_GUID='" . DBSafe($guid) . "'");
        if (!$rec['ID']) {
            return false;
        }
        $rec['GI'] = max(0, min(200, (float)(isset($_POST['gi']) ? $_POST['gi'] : 0)));
        $rec['NOTES'] = mb_substr(strip_tags((string)(isset($_POST['notes']) ? $_POST['notes'] : '')), 0, 500);
        SQLUpdate('tabcalorie_foods', $rec);
        $ov = TabCalorieStore::overview();
        $cfg = $this->configAll();
        TabCalorieStore::updateObject($ov, (int)$cfg['GRAMS_PER_XE'], $cfg);
        return true;
    }

    function doExport()
    {
        $cfg = $this->configAll();
        $period = isset($_REQUEST['period']) ? (string)$_REQUEST['period'] : 'week';
        $kind = isset($_REQUEST['kind']) ? (string)$_REQUEST['kind'] : 'daily';
        list($from, $to) = TabCalorieExporter::periodRange($period, TabCalorieStore::lastFilledDate());
        if ($kind === 'detailed') {
            $csv = TabCalorieExporter::detailedCsv($from, $to, $cfg['CSV_DELIMITER']);
        } else {
            $csv = TabCalorieExporter::dailyCsv($from, $to, $cfg['CSV_DELIMITER'], $cfg['GRAMS_PER_XE']);
        }
        TabCalorieExporter::download('tabcalorie_' . $period . '_' . $from . '_' . $to . '.csv', $csv);
    }

    /** AJAX: food autocomplete via the TK search endpoint (read-only). */
    function ajaxFoodSearch($query)
    {
        $query = trim(mb_substr($query, 0, 80));
        if ($query === '') {
            $this->json(array('ok' => false, 'items' => array()));
        }
        $cfg = $this->configAll();
        if ($cfg['DEMO_MODE']) {
            $this->json(array('ok' => true, 'items' => array(
                array('id' => 'demo-oat', 'title' => 'Вівсянка (демо)', 'url' => 'vivsianka', 'unit' => 'g'),
            )));
        }
        $api = new TabCalorieApi($cfg);
        try {
            $api->login();
            $items = $api->searchFood($query);
            $out = array();
            foreach (array_slice($items, 0, 15) as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $out[] = array(
                    'id' => isset($item['id']) ? $item['id'] : '',
                    'title' => isset($item['title']) ? $item['title'] : '',
                    'url' => isset($item['url']) ? $item['url'] : '',
                    'unit' => isset($item['unit']) ? $item['unit'] : 'g',
                );
            }
            $this->json(array('ok' => true, 'items' => $out));
        } catch (Exception $e) {
            $this->json(array('ok' => false, 'message' => $api->getLastError(), 'items' => array()));
        }
    }

    /** AJAX: full food form (unit options) from TK, for service data storage. */
    function ajaxFoodDetail($guid)
    {
        $guid = trim(mb_substr($guid, 0, 64));
        if ($guid === '') {
            $this->json(array('ok' => false));
        }
        $cfg = $this->configAll();
        $api = new TabCalorieApi($cfg);
        try {
            if (!$cfg['DEMO_MODE']) {
                $api->login();
            }
            $form = $api->getFoodForm($guid);
            $this->json(array('ok' => true, 'form' => $form));
        } catch (Exception $e) {
            $this->json(array('ok' => false, 'message' => $api->getLastError()));
        }
    }

    /** Diagnostics are cached for 5 minutes to avoid repeated logins. */
    function diagnose($force = false)
    {
        $cfg = $this->configAll();
        if ((int)$cfg['DEMO_MODE'] === 1) {
            return array(array('step' => 'demo', 'ok' => true,
                'message' => 'offline demo data, no network requests'));
        }
        $cached = getGlobal('TabCalorie.DiagResult');
        $cachedAt = (int)getGlobal('TabCalorie.DiagTs');
        if (!$force && $cached && time() - $cachedAt < 300) {
            $decoded = json_decode($cached, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }
        $api = new TabCalorieApi($cfg);
        $steps = $api->diagnose();
        setGlobal('TabCalorie.DiagResult', json_encode($steps, JSON_UNESCAPED_UNICODE));
        setGlobal('TabCalorie.DiagTs', time());
        $allOk = true;
        foreach ($steps as $s) {
            if (empty($s['ok'])) {
                $allOk = false;
            }
        }
        // A successful check proves the previous error is stale. Clear it so
        // the object Status and the badge agree with the diagnostics result.
        if ($allOk && ($cfg['LAST_ERROR'] !== '' || (int)$cfg['CIRCUIT_UNTIL'] > 0)) {
            $cfg['LAST_ERROR'] = '';
            $cfg['CIRCUIT_UNTIL'] = 0;
            $cfg['FAIL_COUNT'] = 0;
            $this->config = $cfg;
            $this->saveConfig();
            TabCalorieStore::log('diagnostics', 'ok', 0, 0, 'stale integration error cleared');
            TabCalorieStore::refreshStatusObject($cfg);
        }
        return $steps;
    }

    function testNightscout()
    {
        $cfg = $this->configAll();
        if (!$cfg['NS_ENABLED']) {
            return array('ok' => false, 'message' => 'Nightscout disabled');
        }
        $api = new TabCalorieApi($cfg);
        try {
            $api->pushNightscout(gmdate('c'), 0, 'TabCalorie connection test');
            TabCalorieStore::log('ns_test', 'ok', 0, 0, 'Nightscout OK');
            return array('ok' => true, 'message' => 'Nightscout OK');
        } catch (Exception $e) {
            TabCalorieStore::log('ns_test', 'fail', 0, 0, $api->getLastError());
            return array('ok' => false, 'message' => $api->getLastError());
        }
    }

    /** Queue a write-back item. The caller must still confirm it explicitly. */
    function queueWriteback($entryId)
    {
        $cfg = $this->configAll();
        if (!$cfg['WRITEBACK_ENABLED']) {
            return array('ok' => false, 'message' => 'Write-back disabled');
        }
        $entry = SQLSelectOne("SELECT * FROM tabcalorie_entries WHERE ID=" . (int)$entryId);
        if (!$entry['ID']) {
            return array('ok' => false, 'message' => 'Entry not found');
        }
        $food = SQLSelectOne("SELECT * FROM tabcalorie_foods WHERE FOOD_GUID='" .
            DBSafe($entry['FOOD_GUID']) . "'");
        $payload = array(
            'guid' => $food['FOOD_ID'] ? $food['FOOD_ID'] : $entry['FOOD_GUID'],
            'title' => $entry['TITLE'],
            'url' => $food['FOOD_URL'] ? $food['FOOD_URL'] :
                (isset($entry['FOOD_URL']) && $entry['FOOD_URL'] ? $entry['FOOD_URL'] :
                    strtolower(preg_replace('/[^a-z0-9]+/i', '-', $entry['TITLE']))),
            'date' => $entry['ENTRY_DATE'],
            'multiplier' => (string)($food['DEFAULT_MULTIPLIER'] > 0
                ? $food['DEFAULT_MULTIPLIER'] : TabCalorieStore::round1($entry['QUANTITY'])),
            'diaryTimeGuid' => (string)($food['DEFAULT_SLOT'] > 0
                ? $food['DEFAULT_SLOT'] : (int)$entry['SLOT_ID']),
            'unitGuid' => $food['UNIT_GUID'] ? $food['UNIT_GUID'] : '0000000000000001',
            'unitOptions' => $food['UNIT_OPTIONS'] ? $food['UNIT_OPTIONS'] : '',
        );
        TabCalorieStore::queueWrite($entry['ID'], $payload);
        return array('ok' => true, 'message' => 'Queued, confirmation required');
    }

    /** Send only explicitly confirmed items. Serialized and rate-limited. */
    function sendWriteQueue()
    {
        $cfg = $this->configAll();
        if (!$cfg['WRITEBACK_ENABLED']) {
            return array('ok' => false, 'message' => $this->tr('TC_WRITEBACK_OFF'));
        }
        if (!(int)$cfg['WRITEBACK_CONFIRM']) {
            return array('ok' => false, 'message' => 'confirmation is required');
        }
        $lockFile = ROOT . 'cms/tabcalorie_write.lock';
        if (is_link($lockFile)) {
            return array('ok' => false, 'message' => 'unsafe lock path');
        }
        $fp = @fopen($lockFile, 'c+');
        if (!$fp || !@flock($fp, LOCK_EX | LOCK_NB)) {
            return array('ok' => false, 'message' => 'write-back already running');
        }
        $id = (int)(isset($_POST['id']) ? $_POST['id'] : 0);
        $sql = "SELECT * FROM tabcalorie_write_queue WHERE STATUS='confirmed'";
        if ($id > 0) {
            $sql .= ' AND ID=' . $id;
        }
        $sql .= ' ORDER BY ID LIMIT 20';
        $items = SQLSelect($sql);
        if (!$items) {
            @flock($fp, LOCK_UN);
            fclose($fp);
            return array('ok' => true, 'message' => $this->tr('TC_NO_DATA'));
        }
        $api = new TabCalorieApi($cfg);
        try {
            $api->login();
        } catch (Exception $e) {
            @flock($fp, LOCK_UN);
            fclose($fp);
            return array('ok' => false, 'message' => $api->getLastError());
        }
        $sent = 0;
        $failed = 0;
        foreach ($items as $item) {
            // Atomic claim: no second request can pick this item up.
            TabCalorieStore::setWriteStatus($item['ID'], 'sending', 'claimed');
            $p = json_decode($item['PAYLOAD'], true);
            if (!is_array($p)) {
                TabCalorieStore::setWriteStatus($item['ID'], 'failed', 'bad payload');
                $failed++;
                continue;
            }
            try {
                $api->addFood($p);
                TabCalorieStore::setWriteStatus($item['ID'], 'sent', 'confirmed by user');
                $sent++;
                usleep(500000); // respectful pacing
            } catch (Exception $e) {
                TabCalorieStore::setWriteStatus($item['ID'], 'failed', $api->getLastError());
                $failed++;
            }
        }
        @flock($fp, LOCK_UN);
        fclose($fp);
        TabCalorieStore::log('writeback', $failed ? 'partial' : 'ok', 0, $sent, 'sent=' . $sent . ' failed=' . $failed);
        return array('ok' => ($sent > 0), 'message' => 'sent=' . $sent . ' failed=' . $failed);
    }

    // ---------------------------------------------------------------- sync

    /**
     * Import diary days. Single-flight locked, slow, idempotent.
     * @return array ok/rows/message/date
     */
    function sync($days = null, $force = false)
    {
        $cfg = $this->configAll();
        if ((int)$cfg['DEMO_MODE'] === 1) {
            // Demo mode never touches real tables and never opens a connection.
            return array('ok' => true, 'rows' => 0,
                'message' => 'demo mode: sync disabled', 'date' => date('Y-m-d'));
        }
        $days = $days ?: max(1, (int)$cfg['SYNC_DAYS']);
        $lockFile = ROOT . 'cms/tabcalorie_sync.lock';
        if (is_link($lockFile) || !is_dir(ROOT . 'cms')) {
            return array('ok' => false, 'rows' => 0, 'message' => 'unsafe lock path', 'date' => '');
        }
        $fp = @fopen($lockFile, 'c+');
        if (!$fp || !@flock($fp, LOCK_EX | LOCK_NB)) {
            return array('ok' => false, 'rows' => 0, 'message' => 'sync already running', 'date' => '');
        }
        ftruncate($fp, 0);
        fwrite($fp, (string)getmypid());
        fflush($fp);

        if (!$force && (int)$cfg['CIRCUIT_UNTIL'] > time()) {
            @flock($fp, LOCK_UN);
            fclose($fp);
            return array('ok' => false, 'rows' => 0, 'message' => 'circuit breaker open until ' . date('H:i', $cfg['CIRCUIT_UNTIL']), 'date' => '');
        }

        $api = new TabCalorieApi($cfg);
        $rows = 0;
        $errors = 0;
        try {
            $api->login();
        } catch (Exception $e) {
            $errors++;
            TabCalorieStore::log('sync', 'fail', $api->getHttpCode(), 0, $api->getLastError());
            $this->setFailure($cfg, $errors);
            TabCalorieStore::refreshStatusObject($this->configAll());
            @flock($fp, LOCK_UN);
            fclose($fp);
            return array('ok' => false, 'rows' => 0, 'message' => $api->getLastError(), 'date' => '');
        }

        for ($i = $days - 1; $i >= 0; $i--) {
            $date = date('Y-m-d', time() - $i * 86400);
            try {
                $diary = $api->getDiary($date);
                $parsed = TabCalorieStore::parseDiary($diary, $date);
                $rows += TabCalorieStore::saveEntries($parsed);
                if ((int)$cfg['ACTIVITY_ENABLED'] === 1) {
                    TabCalorieStore::saveActivities(TabCalorieStore::parseActivities($diary, $date));
                }
                TabCalorieStore::saveDay($date, $diary, $cfg);
                TabCalorieStore::log('sync_day', 'ok', $api->getHttpCode(), count($parsed), $date);
            } catch (Exception $e) {
                $errors++;
                TabCalorieStore::log('sync_day', 'fail', $api->getHttpCode(), 0, $date . ': ' . $api->getLastError());
            }
            if ($i > 0) {
                usleep(400000); // ~400 ms between days
            }
        }

        $cfg['LAST_SYNC'] = date('Y-m-d H:i:s');
        $cfg['LAST_ERROR'] = $errors ? $api->getLastError() : '';
        $cfg['FAIL_COUNT'] = $errors
            ? ((int)$cfg['FAIL_COUNT']) + $errors
            : 0;
        $cfg['CIRCUIT_UNTIL'] = $cfg['FAIL_COUNT'] > 0
            ? time() + TabCalorieStore::backoffSeconds($cfg['FAIL_COUNT'])
            : 0;
        $this->config = $cfg;
        $this->saveConfig();

        TabCalorieStore::rebuildFoods();
        $ov = TabCalorieStore::overview();
        TabCalorieStore::updateObject($ov, (int)$cfg['GRAMS_PER_XE'], $cfg);
        TabCalorieStore::cleanup((int)$cfg['RETENTION_DAYS'], $cfg);

        if ((int)$cfg['NS_ENABLED'] === 1) {
            $this->pushNightscoutDays($api, $ov, $cfg);
        }
        TabCalorieStore::log('sync', $errors ? 'partial' : 'ok', 0, $rows, 'days=' . $days . ' errors=' . $errors);

        @flock($fp, LOCK_UN);
        fclose($fp);
        return array('ok' => true, 'rows' => $rows, 'message' => 'days=' . $days . ' errors=' . $errors, 'date' => $ov['date']);
    }

    private function setFailure($cfg, $errors)
    {
        $cfg['LAST_ERROR'] = 'login failed';
        $cfg['FAIL_COUNT'] = ((int)$cfg['FAIL_COUNT']) + max(1, (int)$errors);
        $cfg['CIRCUIT_UNTIL'] = time() + TabCalorieStore::backoffSeconds($cfg['FAIL_COUNT']);
        $this->config = $cfg;
        $this->saveConfig();
    }

    /** Basic Nightscout push: one treatment per non-empty meal slot, de-duplicated. */
    private function pushNightscoutDays($api, $ov, $cfg)
    {
        $hours = array(1 => '08:00:00', 2 => '10:00:00', 3 => '13:00:00',
            4 => '16:00:00', 5 => '19:00:00', 6 => '22:00:00');
        foreach ($ov['slots'] as $id => $slot) {
            if (!$slot['present'] || $slot['carbs'] <= 0) {
                continue;
            }
            if (TabCalorieStore::nsSent($ov['date'], $id)) {
                continue;
            }
            $created = date('c', strtotime($ov['date'] . ' ' . $hours[$id]));
            try {
                $api->pushNightscout($created, $slot['carbs'], $this->tr($slot['title_key']));
                TabCalorieStore::markNsSent($ov['date'], $id, 'ok');
                usleep(300000);
            } catch (Exception $e) {
                TabCalorieStore::log('ns', 'fail', 0, 0, $api->getLastError());
            }
        }
    }

    /** Called from the cycle when a monitored property changes. */
    function checkHook()
    {
        $cfg = $this->configAll();
        if ((int)$cfg['HOOK_ENABLED'] !== 1 || trim($cfg['HOOK_PROPERTY']) === '') {
            return false;
        }
        $value = getGlobal($cfg['HOOK_PROPERTY']);
        $last = getGlobal('TabCalorie.HookValue');
        if ((string)$value === (string)$last) {
            return false;
        }
        $debounce = max(0, (int)$cfg['HOOK_DEBOUNCE_SEC']);
        if (time() - (int)getGlobal('TabCalorie.LastHookTs') < $debounce) {
            return false;
        }
        setGlobal('TabCalorie.HookValue', $value);
        setGlobal('TabCalorie.LastHookTs', time());
        TabCalorieStore::log('hook', 'ok', 0, 0, 'property changed: ' . mb_substr($cfg['HOOK_PROPERTY'], 0, 120));
        return $this->sync(max(1, (int)$cfg['SYNC_DAYS']), true);
    }

    // ---------------------------------------------------------------- css/js

    function css()
    {
        return '<style>
.tc-wrap{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Roboto,Arial,sans-serif;color:#26323f;max-width:1180px;margin:0 auto;padding:12px}
.tc-head{display:flex;flex-wrap:wrap;justify-content:space-between;align-items:center;gap:12px;margin-bottom:12px}
.tc-title{display:flex;align-items:center;gap:12px}.tc-title h2{margin:0;font-size:22px}.tc-title small{color:#7a869a}
.tc-badge{display:inline-block;padding:4px 10px;border-radius:12px;font-size:12px}.tc-ok{background:#e7f6ee;color:#1d7a4d}.tc-err{background:#fdecec;color:#b42318}
.tc-alert{padding:10px 14px;border-radius:6px;margin:8px 0;font-size:13px}
.tc-tabs{list-style:none;display:flex;flex-wrap:wrap;gap:4px;padding:0;margin:0 0 14px;border-bottom:1px solid #e6e9ee}
.tc-tabs li a{display:block;padding:8px 12px;text-decoration:none;color:#5b6876;font-size:13px;border-bottom:2px solid transparent}
.tc-tabs li.active a{color:#1d7a4d;border-color:#2fa36b;font-weight:600}
.tc-row{display:flex;flex-wrap:wrap;gap:12px}.tc-card{flex:1 1 240px;background:#fff;border:1px solid #e6e9ee;border-radius:8px;padding:14px;min-width:220px}
.tc-card-title{font-size:12px;color:#7a869a;text-transform:uppercase;letter-spacing:.04em}.tc-big{font-size:30px;font-weight:700;margin:6px 0}.tc-big small{font-size:14px;font-weight:400;color:#7a869a}
.tc-small{font-size:11px;color:#7a869a}.tc-progress{height:6px;background:#eef1f5;border-radius:4px;overflow:hidden}.tc-progress span{display:block;height:100%;background:#2fa36b}
.tc-bar{height:5px;min-width:70px;background:#eef1f5;border-radius:4px;overflow:hidden;margin-top:4px}.tc-bar span{display:block;height:100%;background:#2fa36b;transition:width .25s}
.tc-bar-over span{background:#e2583e}.tc-bar-val{display:block;font-size:10px;color:#7a869a;margin-top:2px;white-space:nowrap}
.tc-meals td{min-width:120px;vertical-align:top}.tc-meals td:first-child{min-width:130px}
.tc-search{margin-bottom:12px}.tc-results{margin-top:6px;max-height:110px;overflow:auto}
.tc-group th{background:#f0f4f8;color:#2fa36b;font-size:12px;text-transform:uppercase;padding:6px 10px}
.tc-panel{background:#fff;border:1px solid #e6e9ee;border-radius:8px;margin-top:12px;overflow:hidden}
.tc-panel-head{padding:10px 14px;font-weight:600;background:#f7f9fb;border-bottom:1px solid #e6e9ee}.tc-panel-body{padding:14px}
.tc-table-wrap{overflow-x:auto}.tc-table{width:100%;border-collapse:collapse;font-size:13px}.tc-table th,.tc-table td{padding:8px 10px;text-align:left;border-bottom:1px solid #eef1f5;white-space:nowrap}
.tc-table thead th{font-size:11px;color:#7a869a;text-transform:uppercase}.tc-table tfoot th,.tc-table tfoot td{font-weight:700;background:#f7f9fb}.tc-muted{color:#a8b1bd}
.tc-btn{display:inline-block;padding:8px 14px;border:1px solid #d3dae2;border-radius:6px;background:#fff;color:#26323f;text-decoration:none;font-size:13px;cursor:pointer}
.tc-btn-primary{background:#2fa36b;border-color:#2fa36b;color:#fff}.tc-btn-sm{padding:4px 8px;font-size:11px}.tc-inline{display:inline;margin:0 4px 0 0}
.tc-form{margin:0}.tc-form-grid{display:flex;flex-wrap:wrap;gap:10px}.tc-field{flex:1 1 220px;display:flex;flex-direction:column;font-size:12px;color:#5b6876;gap:4px}
.tc-field input,.tc-field select,.tc-form input,.tc-form select{padding:7px 9px;border:1px solid #d3dae2;border-radius:5px;font-size:13px;min-width:120px}
.tc-check{font-size:12px;display:flex;align-items:center;gap:5px}.tc-actions{margin-top:12px;display:flex;flex-wrap:wrap;gap:8px}
.tc-chart{margin-top:14px}.tc-chart-svg{width:100%;height:auto;max-height:280px}.tc-steps{line-height:1.7;font-size:13px}
@media(max-width:640px){.tc-wrap{padding:6px}.tc-title h2{font-size:18px}.tc-big{font-size:24px}.tc-tabs li a{padding:7px 8px;font-size:12px}.tc-field{flex:1 1 100%}.tc-card{min-width:100%}}
</style>';
    }

    function js()
    {
        return '<script>
(function(){
  function tcReady(fn){if(document.readyState!=="loading"){fn();}else{document.addEventListener("DOMContentLoaded",fn);}}
  tcReady(function(){
    document.querySelectorAll(".tc-tabs a").forEach(function(a){
      if(a.getAttribute("href")===""||a.getAttribute("href")==="#"){a.onclick=function(e){e.preventDefault();};}
    });
  });
})();
</script>';
    }
}
