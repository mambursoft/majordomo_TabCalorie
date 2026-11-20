<?php
/**
 * Minimal MajorDoMo stubs for unit tests. No real database is used.
 */

$GLOBALS['tc_test_day'] = array(
    'ID' => 1, 'DAY_DATE' => '2026-11-19', 'ENERGY' => 560, 'PROTEIN' => 17.5,
    'FAT' => 5.1, 'CARBOHYDRATE' => 100, 'FIBER' => 7.3, 'SUGAR' => 8.2,
    'SLOT_COUNT' => 2, 'FOODSTUFF_COUNT' => 2, 'NOTES' => 'додати корицю',
);
$GLOBALS['tc_test_groups'] = array(
    array('SLOT_ID' => 1, 'C' => 40, 'E' => 300, 'P' => 12.5, 'F' => 4.2, 'FI' => 6.1, 'S' => 8, 'N' => 1),
    array('SLOT_ID' => 3, 'C' => 60, 'E' => 260, 'P' => 5, 'F' => 0.9, 'FI' => 1.2, 'S' => 0.2, 'N' => 1),
);
$GLOBALS['tc_test_day']['STEPS'] = 3200;
$GLOBALS['tc_test_day']['ACTIVITY_KCAL'] = 440;
$GLOBALS['tc_test_day']['ACTIVITY_COUNT'] = 2;
$GLOBALS['tc_test_day']['RAW_JSON'] = '{"code":0,"data":{"times":[]}}';
$GLOBALS['tc_sql_exec'] = array();

function DBSafe($v) { return addslashes((string)$v); }

function SQLSelectOne($sql)
{
    if (strpos($sql, 'MAX(ENTRY_DATE)') !== false) {
        return array('D' => '2026-11-19');
    }
    if (strpos($sql, 'FROM tabcalorie_days') !== false && strpos($sql, 'DAY_DATE=') !== false) {
        return $GLOBALS['tc_test_day'];
    }
    if (strpos($sql, "SELECT * FROM tabcalorie_foods WHERE FOOD_GUID=") !== false) {
        return array('ID' => 0);
    }
    if (strpos($sql, "FROM tabcalorie_write_queue WHERE STATUS='ns_sent'") !== false) {
        return array('ID' => 0);
    }
    if (strpos($sql, 'SUM(STEPS) AS S') !== false && strpos($sql, 'tabcalorie_activities') !== false) {
        return array('S' => 3200, 'E' => 440, 'N' => 2);
    }
    if (strpos($sql, 'SELECT RAW_JSON FROM tabcalorie_days') !== false) {
        return $GLOBALS['tc_test_day'];
    }
    if (strpos($sql, 'COUNT(*) AS N FROM tabcalorie_sync_log') !== false) {
        return array('N' => 2500);
    }
    return array();
}

function SQLSelect($sql)
{
    if (strpos($sql, 'GROUP BY SLOT_ID') !== false) {
        return $GLOBALS['tc_test_groups'];
    }
    if (strpos($sql, 'LEFT JOIN tabcalorie_foods') !== false) {
        return array();
    }
    if (strpos($sql, 'SUM(STEPS) AS S') !== false) {
        return array('S' => 3200, 'E' => 440, 'N' => 2);
    }
    if (strpos($sql, 'FROM tabcalorie_activities') !== false) {
        return array();
    }
    if (strpos($sql, 'SELECT ID, SLOT_ID, SLOT_TITLE, TITLE, QUANTITY') !== false) {
        return array(
            array('ID' => 1, 'SLOT_ID' => 1, 'TITLE' => 'Вівсянка', 'QUANTITY' => 150,
                'UNIT' => 'г', 'UNIT_RAW' => '150 x 1 g', 'ENERGY' => 300, 'CARBOHYDRATE' => 40),
            array('ID' => 2, 'SLOT_ID' => 3, 'TITLE' => 'Рис', 'QUANTITY' => 200,
                'UNIT' => 'г', 'UNIT_RAW' => '200 x 1 g', 'ENERGY' => 260, 'CARBOHYDRATE' => 60),
        );
    }
    if (strpos($sql, 'FROM tabcalorie_days') !== false) {
        return array($GLOBALS['tc_test_day']);
    }
    if (strpos($sql, 'FROM tabcalorie_entries') !== false) {
        return array();
    }
    return array();
}

function SQLInsert($table, &$rec) { $rec['ID'] = 1; return 1; }
function SQLUpdate($table, $rec) { return true; }
function SQLExec($sql) { $GLOBALS['tc_sql_exec'][] = $sql; return true; }

function getGlobal($name) { return isset($GLOBALS['tc_globals'][$name]) ? $GLOBALS['tc_globals'][$name] : ''; }
function setGlobal($name, $value, $keepHistory = 0) { $GLOBALS['tc_globals'][$name] = $value; return true; }

function addClass($name, $parent = '') { return 1; }
function addClassProperty($class, $prop, $history = 0) { return 1; }
function addClassObject($class, $object, $system = '') { return 1; }

define('ROOTHTML', '/');
