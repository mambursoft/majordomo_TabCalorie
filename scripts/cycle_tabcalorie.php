<?php
/**
 * TabCalorie background cycle for MajorDoMo.
 *
 * Responsibilities:
 *  - periodic slow sync of the last N days,
 *  - forced sync on a monitored object property change (hook),
 *  - retention cleanup is performed by sync(),
 *  - control flags: cycle_tabcalorieControl = restart | sync_now.
 *
 * Safety: one flock guard, no parallel requests, interval >= 15 minutes,
 * circuit breaker and single-flight sync lock live in the module class.
 *
 * @package tabcalorie
 * @author  mambursoft
 * @license MIT
 */

chdir(dirname(__FILE__) . '/../');

include_once('./config.php');
include_once('./lib/loader.php');
include_once('./lib/threads.php');
require_once(dirname(__FILE__) . '/../modules/tabcalorie/tabcalorie.class.php');

set_time_limit(0);

$lockFile = ROOT . 'cms/cycle_tabcalorie.lock';
if (is_link($lockFile)) {
    echo date('H:i:s') . " [TabCalorie] Unsafe lock path. Exiting.\n";
    exit(0);
}
$lockFp = @fopen($lockFile, 'c+');
if ($lockFp) {
    if (!@flock($lockFp, LOCK_EX | LOCK_NB)) {
        echo date('H:i:s') . " [TabCalorie] Another cycle instance is running. Exiting.\n";
        exit(0);
    }
    ftruncate($lockFp, 0);
    fwrite($lockFp, (string)getmypid() . "\n");
    fflush($lockFp);
}

$ctrlVar = 'cycle_tabcalorieControl';
setGlobal($ctrlVar, '', 1);

$module = new tabcalorie();
echo date('H:i:s') . " [TabCalorie] cycle started\n";

while (true) {
    $didSync = false;
    $ctrl = (string)getGlobal($ctrlVar);
    if ($ctrl !== '') {
        setGlobal($ctrlVar, '', 1);
        if ($ctrl === 'sync_now') {
            $module->sync(null, true);
            $didSync = true;
        }
    }

    $cfg = $module->configAll();
    if (!$didSync && (int)$cfg['SYNC_ENABLED'] === 1) {
        $intervalMin = max(15, (int)$cfg['SYNC_INTERVAL_MIN']);
        $last = (int)getGlobal('TabCalorie.LastRunTs');
        if (time() - $last >= $intervalMin * 60) {
            $result = $module->sync((int)$cfg['SYNC_DAYS'], false);
            setGlobal('TabCalorie.LastRunTs', time());
            echo date('H:i:s') . ' [TabCalorie] sync rows=' . $result['rows'] .
                ' ok=' . ($result['ok'] ? '1' : '0') . "\n";
        }
    }

    $module->checkHook();
    setGlobal('cycle_tabcalorieRun', date('Y-m-d H:i:s'));
    setGlobal('cycle_tabcalorieRunTs', time());
    sleep(30);
}
