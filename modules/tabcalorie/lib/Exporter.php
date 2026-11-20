<?php
/**
 * CSV exporter for TabCalorie. UTF-8 BOM, configurable delimiter,
 * Ukrainian-style dates and safe cell escaping (no formula injection).
 *
 * @package tabcalorie
 * @license MIT
 */

class TabCalorieExporter
{
    /** Period keys supported by the UI. */
    public static function periodRange($period, $anchor = null)
    {
        $anchor = $anchor ?: date('Y-m-d');
        $ts = strtotime($anchor);
        switch ($period) {
            case 'day':
                return array(date('Y-m-d', $ts), date('Y-m-d', $ts));
            case 'week':
                return array(date('Y-m-d', strtotime('-6 days', $ts)), date('Y-m-d', $ts));
            case 'month':
                return array(date('Y-m-01', $ts), date('Y-m-t', $ts));
            case 'year':
                return array(date('Y-01-01', $ts), date('Y-12-31', $ts));
            default:
                return array(date('Y-m-d', strtotime('-6 days', $ts)), date('Y-m-d', $ts));
        }
    }

    public static function dateUa($iso)
    {
        $ts = strtotime($iso);
        return $ts ? date('d.m.Y', $ts) : (string)$iso;
    }

    /** Neutralize spreadsheet formula injection. */
    public static function cell($value)
    {
        $s = (string)$value;
        if ($s !== '' && in_array($s[0], array('=', '+', '-', '@', "\t", "\r"), true)) {
            $s = "'" . $s;
        }
        return $s;
    }

    /** Build a CSV string with BOM. */
    public static function build($headers, $rows, $delimiter = ';')
    {
        $delimiter = in_array($delimiter, array(';', ',', "\t", '|'), true) ? $delimiter : ';';
        $out = "\xEF\xBB\xBF";
        $write = function ($fields) use (&$out, $delimiter) {
            $escaped = array();
            foreach ($fields as $f) {
                $s = self::cell($f);
                $s = str_replace(array('"', "\r", "\n"), array('""', ' ', ' '), $s);
                $escaped[] = '"' . $s . '"';
            }
            $out .= implode($delimiter, $escaped) . "\r\n";
        };
        $write($headers);
        foreach ($rows as $r) {
            $write($r);
        }
        return $out;
    }

    /** Daily summary CSV. */
    public static function dailyCsv($from, $to, $delimiter = ';', $gramsPerXe = 10)
    {
        $rows = TabCalorieStore::dayRows($from, $to);
        $headers = array('Дата', 'Ккал', 'Білки,г', 'Жири,г', 'Вуглеводи,г', 'ХО',
            'Клітковина,г', 'Цукри,г', 'Прийомів', 'Продуктів', 'Нотатки');
        $data = array();
        foreach ($rows as $r) {
            $carbs = TabCalorieStore::round1($r['CARBOHYDRATE']);
            $data[] = array(
                self::dateUa($r['DAY_DATE']),
                $r['ENERGY'], $r['PROTEIN'], $r['FAT'], $carbs,
                TabCalorieStore::round1($carbs / max(1, (float)$gramsPerXe)),
                $r['FIBER'], $r['SUGAR'], $r['SLOT_COUNT'], $r['FOODSTUFF_COUNT'], $r['NOTES'],
            );
        }
        return self::build($headers, $data, $delimiter);
    }

    /** Detailed per-food CSV. */
    public static function detailedCsv($from, $to, $delimiter = ';')
    {
        $rows = TabCalorieStore::entryRows($from, $to);
        $headers = array('Дата', 'Слот', 'Страва', 'Кількість', 'Одиниця', 'Ккал',
            'Білки,г', 'Жири,г', 'Вуглеводи,г', 'Клітковина,г', 'Цукри,г', 'Джерело');
        $data = array();
        foreach ($rows as $r) {
            $quantity = TabCalorieStore::num($r['QUANTITY']);
            $quantity = $quantity > 0 ? TabCalorieStore::round1($quantity) : $r['UNIT_RAW'];
            $data[] = array(
                self::dateUa($r['ENTRY_DATE']),
                $r['SLOT_TITLE'] !== '' ? $r['SLOT_TITLE'] : ('#' . $r['SLOT_ID']),
                $r['TITLE'], $quantity, $r['UNIT'],
                $r['ENERGY'], $r['PROTEIN'], $r['FAT'], $r['CARBOHYDRATE'],
                $r['FIBER'], $r['SUGAR'], $r['SOURCE'],
            );
        }
        return self::build($headers, $data, $delimiter);
    }

    /** Send CSV to browser and terminate. Only used from an explicit user action. */
    public static function download($filename, $csv)
    {
        while (ob_get_level() > 0) {
            ob_end_clean();
        }
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="' . basename($filename) . '"');
        header('Cache-Control: no-store, no-cache, must-revalidate');
        header('X-Content-Type-Options: nosniff');
        echo $csv;
        exit;
    }
}
