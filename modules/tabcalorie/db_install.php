<?php
/**
 * Reference copy of the TabCalorie database schema.
 * The authoritative schema lives in tabcalorie.class.php::dbInstall().
 * This file is never auto-executed by MajorDoMo - it is for documentation
 * and manual repair only.
 *
 * @package tabcalorie
 * @license MIT
 */

$tc_schema = array(
    'tabcalorie_entries' => array(
        'ID int(10) unsigned NOT NULL auto_increment',
        "ENTRY_KEY varchar(64) NOT NULL DEFAULT ''",
        'ENTRY_DATE date NOT NULL',
        "SLOT_ID int(11) NOT NULL DEFAULT '0'",
        "SLOT_TITLE varchar(255) NOT NULL DEFAULT ''",
        "TITLE varchar(255) NOT NULL DEFAULT ''",
        "FOOD_GUID varchar(64) NOT NULL DEFAULT ''",
        "UNIT_RAW varchar(64) NOT NULL DEFAULT ''",
        "QUANTITY float NOT NULL DEFAULT '0'",
        "UNIT varchar(32) NOT NULL DEFAULT ''",
        "ENERGY float NOT NULL DEFAULT '0'",
        "PROTEIN float NOT NULL DEFAULT '0'",
        "FAT float NOT NULL DEFAULT '0'",
        "CARBOHYDRATE float NOT NULL DEFAULT '0'",
        "FIBER float NOT NULL DEFAULT '0'",
        "SUGAR float NOT NULL DEFAULT '0'",
        "SOURCE varchar(16) NOT NULL DEFAULT 'tk'",
        "SYNCED_AT datetime NOT NULL DEFAULT '0000-00-00 00:00:00'",
        'KEY IDX_TC_ENTRY_DATE (ENTRY_DATE)',
        'KEY IDX_TC_ENTRY_GUID (FOOD_GUID)',
        'UNIQUE KEY UQ_TC_ENTRY (ENTRY_KEY(64))',
    ),
    'tabcalorie_foods' => array(
        'ID int(10) unsigned NOT NULL auto_increment',
        "FOOD_GUID varchar(64) NOT NULL DEFAULT ''",
        "TITLE varchar(255) NOT NULL DEFAULT ''",
        "UNIT varchar(32) NOT NULL DEFAULT ''",
        "ENERGY_100 float NOT NULL DEFAULT '0'",
        "PROTEIN_100 float NOT NULL DEFAULT '0'",
        "FAT_100 float NOT NULL DEFAULT '0'",
        "CARB_100 float NOT NULL DEFAULT '0'",
        "FIBER_100 float NOT NULL DEFAULT '0'",
        "SUGAR_100 float NOT NULL DEFAULT '0'",
        "GI float NOT NULL DEFAULT '0'",
        'NOTES text',
        "UPDATED datetime NOT NULL DEFAULT '0000-00-00 00:00:00'",
    ),
    'tabcalorie_days' => array(
        'ID int(10) unsigned NOT NULL auto_increment',
        'DAY_DATE date NOT NULL',
        "ENERGY float NOT NULL DEFAULT '0'",
        "PROTEIN float NOT NULL DEFAULT '0'",
        "FAT float NOT NULL DEFAULT '0'",
        "CARBOHYDRATE float NOT NULL DEFAULT '0'",
        "FIBER float NOT NULL DEFAULT '0'",
        "SUGAR float NOT NULL DEFAULT '0'",
        "SLOT_COUNT int(11) NOT NULL DEFAULT '0'",
        "FOODSTUFF_COUNT int(11) NOT NULL DEFAULT '0'",
        'NOTES text',
        "UPDATED datetime NOT NULL DEFAULT '0000-00-00 00:00:00'",
        'KEY IDX_TC_DAY_DATE (DAY_DATE)',
    ),
    'tabcalorie_sync_log' => array(
        'ID int(10) unsigned NOT NULL auto_increment',
        "RUN_AT datetime NOT NULL DEFAULT '0000-00-00 00:00:00'",
        "KIND varchar(32) NOT NULL DEFAULT ''",
        "STATUS varchar(16) NOT NULL DEFAULT ''",
        "HTTP_CODE int(11) NOT NULL DEFAULT '0'",
        "ROWS int(11) NOT NULL DEFAULT '0'",
        'MESSAGE text',
    ),
    'tabcalorie_write_queue' => array(
        'ID int(10) unsigned NOT NULL auto_increment',
        "ENTRY_ID int(11) NOT NULL DEFAULT '0'",
        'PAYLOAD text',
        "STATUS varchar(16) NOT NULL DEFAULT 'draft'",
        "CREATED_AT datetime NOT NULL DEFAULT '0000-00-00 00:00:00'",
        "SENT_AT datetime NOT NULL DEFAULT '0000-00-00 00:00:00'",
        'MESSAGE text',
    ),
);

// Optional helper: echo CREATE TABLE statements for manual repair.
if (PHP_SAPI === 'cli' && isset($argv[1]) && $argv[1] === '--sql') {
    foreach ($tc_schema as $table => $fields) {
        echo 'CREATE TABLE IF NOT EXISTS ' . $table . " (\n  " . implode(",\n  ", $fields) .
            "\n) CHARACTER SET utf8 COLLATE utf8_general_ci;\n\n";
    }
}
