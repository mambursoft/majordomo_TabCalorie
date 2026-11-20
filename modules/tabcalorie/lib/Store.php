<?php
/**
 * TabCalorie storage layer. All SQL goes through MajorDoMo helpers.
 *
 * @package tabcalorie
 * @license MIT
 */

class TabCalorieStore
{
    /** Parse "1 234,5" / "12.5" / null into float. */
    public static function num($value)
    {
        if ($value === null || $value === '' || is_array($value)) {
            return 0.0;
        }
        if (is_numeric($value)) {
            return (float)$value;
        }
        $s = str_replace(array("\xc2\xa0", ' ', "\t"), '', (string)$value);
        $s = str_replace(',', '.', $s);
        $s = preg_replace('/[^0-9.\-+eE]/', '', $s);
        return is_numeric($s) ? (float)$s : 0.0;
    }

    public static function round1($v)
    {
        return round((float)$v, 1);
    }

    /**
     * Parse "166 x 1 g" or "2 x 200 ml" into total quantity and unit label.
     * The API stores count x unit-multiplier, so both parts are multiplied.
     */
    public static function parseUnit($raw)
    {
        $raw = trim((string)$raw);
        if (preg_match('/^([0-9]+(?:[.,][0-9]+)?)\s*x\s*([0-9]+(?:[.,][0-9]+)?)\s*(.+)$/iu', $raw, $m)) {
            return array(self::num($m[1]) * self::num($m[2]), trim($m[3]));
        }
        if (preg_match('/^([0-9]+(?:[.,][0-9]+)?)\s*x\s*(.+)$/iu', $raw, $m)) {
            return array(self::num($m[1]), trim($m[2]));
        }
        if (preg_match('/^([0-9]+(?:[.,][0-9]+)?)\s*(g|ml|ks|pc|шт)\b/iu', $raw, $m)) {
            return array(self::num($m[1]), strtolower($m[2]));
        }
        return array(0.0, $raw);
    }

    /** Unique key for an entry, used for idempotent upserts. */
    private static function entryKey($date, $slotId, $foodGuid, $unitRaw)
    {
        return md5($date . '|' . $slotId . '|' . $foodGuid . '|' . $unitRaw);
    }

    /**
     * Convert one diary response into normalized entry rows.
     * @return array rows ready for upsert
     */
    public static function parseDiary($diary, $date)
    {
        $rows = array();
        if (!is_array($diary)) {
            return $rows;
        }
        $times = isset($diary['times']) && is_array($diary['times']) ? $diary['times'] : array();
        foreach ($times as $idx => $slot) {
            if (!is_array($slot)) {
                continue;
            }
            $slotId = (int)(isset($slot['id']) ? $slot['id'] : ($idx + 1));
            $slotTitle = isset($slot['title']) ? (string)$slot['title'] : '';
            $foods = isset($slot['foodstuff']) && is_array($slot['foodstuff']) ? $slot['foodstuff'] : array();
            foreach ($foods as $item) {
                if (!is_array($item)) {
                    continue;
                }
                $title = isset($item['title']) ? trim((string)$item['title']) : '';
                if ($title === '') {
                    continue;
                }
                $unitRaw = isset($item['unit']) ? (string)$item['unit'] : '';
                $foodGuid = isset($item['guidType']) && $item['guidType'] ? (string)$item['guidType'] : '';
                if ($foodGuid === '' && isset($item['id'])) {
                    $foodGuid = (string)$item['id'];
                }
                if ($foodGuid === '') {
                    $foodGuid = md5($title . '|' . $unitRaw);
                }
                list($quantity, $unit) = self::parseUnit($unitRaw);
                $row = array(
                    'ENTRY_KEY' => self::entryKey($date, $slotId, $foodGuid, $unitRaw),
                    'ENTRY_DATE' => $date,
                    'SLOT_ID' => $slotId,
                    'SLOT_TITLE' => $slotTitle,
                    'TITLE' => $title,
                    'FOOD_GUID' => $foodGuid,
                    'UNIT_RAW' => $unitRaw,
                    'QUANTITY' => $quantity,
                    'UNIT' => $unit,
                    'ENERGY' => self::num(isset($item['energy']) ? $item['energy'] : 0),
                    'PROTEIN' => self::num(isset($item['protein']) ? $item['protein'] : 0),
                    'FAT' => self::num(isset($item['fat']) ? $item['fat'] : 0),
                    'CARBOHYDRATE' => self::num(isset($item['carbohydrate']) ? $item['carbohydrate'] : 0),
                    'FIBER' => self::num(isset($item['fiber']) ? $item['fiber'] : 0),
                    'SUGAR' => self::num(isset($item['sugar']) ? $item['sugar'] : 0),
                    'SOURCE' => 'tk',
                    'SYNCED_AT' => date('Y-m-d H:i:s'),
                );
                $rows[] = $row;
            }
        }
        return $rows;
    }

    /** Idempotent insert/update of parsed rows. Returns affected row count. */
    public static function saveEntries($rows)
    {
        $count = 0;
        foreach ($rows as $row) {
            $key = DBSafe($row['ENTRY_KEY']);
            $old = SQLSelectOne("SELECT ID FROM tabcalorie_entries WHERE ENTRY_KEY='" . $key . "'");
            $record = $row;
            if ($old['ID']) {
                $record['ID'] = $old['ID'];
                SQLUpdate('tabcalorie_entries', $record);
            } else {
                SQLInsert('tabcalorie_entries', $record);
            }
            $count++;
        }
        return $count;
    }

    /** Recompute and store daily totals from entries. */
    public static function saveDay($date, $diary = null)
    {
        $sql = "SELECT
                    COUNT(*) AS CNT,
                    SUM(ENERGY) AS ENERGY,
                    SUM(PROTEIN) AS PROTEIN,
                    SUM(FAT) AS FAT,
                    SUM(CARBOHYDRATE) AS CARB,
                    SUM(FIBER) AS FIBER,
                    SUM(SUGAR) AS SUGAR,
                    COUNT(DISTINCT SLOT_ID) AS SLOTS
                FROM tabcalorie_entries WHERE ENTRY_DATE='" . DBSafe($date) . "'";
        $sum = SQLSelectOne($sql);
        $carbs = self::round1(isset($sum['CARB']) ? $sum['CARB'] : 0);
        $day = array(
            'DAY_DATE' => $date,
            'ENERGY' => self::round1(isset($sum['ENERGY']) ? $sum['ENERGY'] : 0),
            'PROTEIN' => self::round1(isset($sum['PROTEIN']) ? $sum['PROTEIN'] : 0),
            'FAT' => self::round1(isset($sum['FAT']) ? $sum['FAT'] : 0),
            'CARBOHYDRATE' => $carbs,
            'FIBER' => self::round1(isset($sum['FIBER']) ? $sum['FIBER'] : 0),
            'SUGAR' => self::round1(isset($sum['SUGAR']) ? $sum['SUGAR'] : 0),
            'FOODSTUFF_COUNT' => (int)(isset($sum['CNT']) ? $sum['CNT'] : 0),
            'SLOT_COUNT' => (int)(isset($sum['SLOTS']) ? $sum['SLOTS'] : 0),
            'UPDATED' => date('Y-m-d H:i:s'),
        );
        // Keep notes from the diary slot level (API has no per-food notes).
        $notes = array();
        if (is_array($diary) && isset($diary['times']) && is_array($diary['times'])) {
            foreach ($diary['times'] as $slot) {
                if (is_array($slot) && isset($slot['notes']) && is_array($slot['notes'])) {
                    foreach ($slot['notes'] as $note) {
                        if (is_array($note)) {
                            $note = isset($note['title']) ? $note['title'] : json_encode($note);
                        }
                        $note = trim(strip_tags((string)$note));
                        if ($note !== '') {
                            $notes[] = $note;
                        }
                    }
                }
            }
        }
        $day['NOTES'] = mb_substr(implode(' | ', $notes), 0, 1000);
        $old = SQLSelectOne("SELECT ID FROM tabcalorie_days WHERE DAY_DATE='" . DBSafe($date) . "'");
        if ($old['ID']) {
            $day['ID'] = $old['ID'];
            SQLUpdate('tabcalorie_days', $day);
        } else {
            SQLInsert('tabcalorie_days', $day);
        }
        return $day;
    }

    /** Most recent date that actually has entries (never skip to another day). */
    public static function lastFilledDate()
    {
        $rec = SQLSelectOne("SELECT MAX(ENTRY_DATE) AS D FROM tabcalorie_entries");
        return isset($rec['D']) && $rec['D'] ? $rec['D'] : date('Y-m-d');
    }

    /** Aggregation for the last filled day: 6 slots + 3 groups. */
    public static function overview($date = null)
    {
        if (!$date) {
            $date = self::lastFilledDate();
        }
        $day = SQLSelectOne("SELECT * FROM tabcalorie_days WHERE DAY_DATE='" . DBSafe($date) . "'");
        if (!$day['ID']) {
            $day = array('DAY_DATE' => $date, 'CARBOHYDRATE' => 0, 'ENERGY' => 0,
                'PROTEIN' => 0, 'FAT' => 0, 'FIBER' => 0, 'SUGAR' => 0,
                'SLOT_COUNT' => 0, 'FOODSTUFF_COUNT' => 0, 'NOTES' => '');
        }
        $slots = array();
        for ($i = 1; $i <= 6; $i++) {
            $slots[$i] = array('id' => $i, 'title_key' => self::slotKey($i), 'carbs' => 0.0,
                'kcal' => 0.0, 'count' => 0, 'present' => false);
        }
        $rows = SQLSelect("SELECT SLOT_ID, SUM(CARBOHYDRATE) AS C, SUM(ENERGY) AS E, COUNT(*) AS N
                           FROM tabcalorie_entries WHERE ENTRY_DATE='" . DBSafe($date) . "'
                           GROUP BY SLOT_ID");
        foreach ($rows as $r) {
            $id = (int)$r['SLOT_ID'];
            if (isset($slots[$id])) {
                $slots[$id]['carbs'] = self::round1($r['C']);
                $slots[$id]['kcal'] = self::round1($r['E']);
                $slots[$id]['count'] = (int)$r['N'];
                $slots[$id]['present'] = true;
            }
        }
        $main = $slots[1]['carbs'] + $slots[3]['carbs'] + $slots[5]['carbs'];
        $snacks = $slots[2]['carbs'] + $slots[4]['carbs'] + $slots[6]['carbs'];
        return array(
            'date' => $date,
            'partial' => ((int)(isset($day['SLOT_COUNT']) ? $day['SLOT_COUNT'] : 0) < 6),
            'slot_count' => (int)(isset($day['SLOT_COUNT']) ? $day['SLOT_COUNT'] : 0),
            'slots' => $slots,
            'groups' => array('main' => self::round1($main), 'snacks' => self::round1($snacks),
                'day' => self::round1($main + $snacks)),
            'total_carbs' => self::round1(isset($day['CARBOHYDRATE']) ? $day['CARBOHYDRATE'] : ($main + $snacks)),
            'total_kcal' => self::round1(isset($day['ENERGY']) ? $day['ENERGY'] : 0),
            'protein' => self::round1(isset($day['PROTEIN']) ? $day['PROTEIN'] : 0),
            'fat' => self::round1(isset($day['FAT']) ? $day['FAT'] : 0),
            'fiber' => self::round1(isset($day['FIBER']) ? $day['FIBER'] : 0),
            'sugar' => self::round1(isset($day['SUGAR']) ? $day['SUGAR'] : 0),
            'notes' => isset($day['NOTES']) ? (string)$day['NOTES'] : '',
        );
    }

    public static function slotKey($id)
    {
        $keys = array(1 => 'TC_BREAKFAST', 2 => 'TC_SNACK1', 3 => 'TC_LUNCH',
            4 => 'TC_SNACK2', 5 => 'TC_DINNER', 6 => 'TC_LATE_DINNER');
        return isset($keys[(int)$id]) ? $keys[(int)$id] : 'TC_MEAL';
    }

    /** Return the MajorDoMo object with per-meal aggregates for the last filled day. */
    public static function updateObject($overview, $gramsPerXe = 10)
    {
        if (!function_exists('addClass') || !function_exists('getObject')) {
            return false;
        }
        $classId = addClass('STabCalorie');
        if (!$classId) {
            return false;
        }
        $props = array('LastDate', 'Partial', 'FilledSlots', 'GroupMainCarbs', 'GroupSnacksCarbs',
            'GroupDayCarbs', 'TotalCarbs', 'TotalKcal', 'TotalProtein', 'TotalFat', 'TotalFiber',
            'TotalSugar', 'TotalXE', 'GI', 'Notes', 'Updated');
        for ($i = 1; $i <= 6; $i++) {
            $props[] = 'Slot' . $i . 'Carbs';
            $props[] = 'Slot' . $i . 'Kcal';
        }
        foreach ($props as $p) {
            $history = (strpos($p, 'Carbs') !== false || strpos($p, 'Total') === 0) ? 365 : 0;
            addClassProperty('STabCalorie', $p, $history);
        }
        addClassObject('STabCalorie', 'TabCalorie', 'tabcalorie');
        $obj = getObject('TabCalorie');
        if (!$obj) {
            return false;
        }
        $values = array(
            'LastDate' => $overview['date'],
            'Partial' => $overview['partial'] ? 1 : 0,
            'FilledSlots' => $overview['slot_count'],
            'GroupMainCarbs' => $overview['groups']['main'],
            'GroupSnacksCarbs' => $overview['groups']['snacks'],
            'GroupDayCarbs' => $overview['groups']['day'],
            'TotalCarbs' => $overview['total_carbs'],
            'TotalKcal' => $overview['total_kcal'],
            'TotalProtein' => $overview['protein'],
            'TotalFat' => $overview['fat'],
            'TotalFiber' => $overview['fiber'],
            'TotalSugar' => $overview['sugar'],
            'TotalXE' => self::round1($overview['total_carbs'] / max(1, (float)$gramsPerXe)),
            'GI' => self::averageGi($overview['date']),
            'Notes' => $overview['notes'],
            'Updated' => date('Y-m-d H:i:s'),
        );
        for ($i = 1; $i <= 6; $i++) {
            $values['Slot' . $i . 'Carbs'] = $overview['slots'][$i]['carbs'];
            $values['Slot' . $i . 'Kcal'] = $overview['slots'][$i]['kcal'];
        }
        foreach ($values as $name => $value) {
            $obj->setProperty($name, $value, 0, 'tabcalorie');
        }
        return true;
    }

    /** Weighted average GI from the local food dictionary. */
    public static function averageGi($date)
    {
        $rows = SQLSelect("SELECT e.FOOD_GUID, e.QUANTITY, f.GI
                           FROM tabcalorie_entries e
                           LEFT JOIN tabcalorie_foods f ON f.FOOD_GUID=e.FOOD_GUID
                           WHERE e.ENTRY_DATE='" . DBSafe($date) . "' AND f.GI>0");
        $sum = 0.0;
        $weight = 0.0;
        foreach ($rows as $r) {
            $w = self::num($r['QUANTITY']);
            if ($w <= 0) {
                $w = 1;
            }
            $sum += self::num($r['GI']) * $w;
            $weight += $w;
        }
        return $weight > 0 ? self::round1($sum / $weight) : 0.0;
    }

    /** Rebuild the per-100 g food dictionary from imported entries. */
    public static function rebuildFoods()
    {
        $rows = SQLSelect("SELECT FOOD_GUID, TITLE, UNIT,
                    SUM(QUANTITY) AS Q, SUM(ENERGY) AS E, SUM(PROTEIN) AS P, SUM(FAT) AS F,
                    SUM(CARBOHYDRATE) AS C, SUM(FIBER) AS FI, SUM(SUGAR) AS S
                   FROM tabcalorie_entries
                   GROUP BY FOOD_GUID, TITLE, UNIT");
        $n = 0;
        foreach ($rows as $r) {
            $q = self::num($r['Q']);
            if ($q <= 0) {
                continue;
            }
            $k = 100 / $q;
            $rec = array(
                'FOOD_GUID' => $r['FOOD_GUID'],
                'TITLE' => $r['TITLE'],
                'UNIT' => $r['UNIT'],
                'ENERGY_100' => self::round1(self::num($r['E']) * $k),
                'PROTEIN_100' => self::round1(self::num($r['P']) * $k),
                'FAT_100' => self::round1(self::num($r['F']) * $k),
                'CARB_100' => self::round1(self::num($r['C']) * $k),
                'FIBER_100' => self::round1(self::num($r['FI']) * $k),
                'SUGAR_100' => self::round1(self::num($r['S']) * $k),
                'UPDATED' => date('Y-m-d H:i:s'),
            );
            $old = SQLSelectOne("SELECT ID, GI, NOTES FROM tabcalorie_foods WHERE FOOD_GUID='" . DBSafe($r['FOOD_GUID']) . "'");
            if ($old['ID']) {
                $rec['ID'] = $old['ID'];
                if (isset($old['GI'])) {
                    $rec['GI'] = $old['GI'];
                }
                if (isset($old['NOTES'])) {
                    $rec['NOTES'] = $old['NOTES'];
                }
                SQLUpdate('tabcalorie_foods', $rec);
            } else {
                SQLInsert('tabcalorie_foods', $rec);
            }
            $n++;
        }
        return $n;
    }

    public static function log($kind, $status, $httpCode, $rows, $message)
    {
        $rec = array(
            'RUN_AT' => date('Y-m-d H:i:s'),
            'KIND' => substr((string)$kind, 0, 32),
            'STATUS' => substr((string)$status, 0, 16),
            'HTTP_CODE' => (int)$httpCode,
            'ROWS' => (int)$rows,
            'MESSAGE' => mb_substr(preg_replace('/[\r\n\t]+/', ' ', strip_tags((string)$message)), 0, 500),
        );
        return SQLInsert('tabcalorie_sync_log', $rec);
    }

    public static function cleanup($retentionDays)
    {
        $days = max(30, (int)$retentionDays);
        $cut = date('Y-m-d', time() - $days * 86400);
        SQLExec("DELETE FROM tabcalorie_entries WHERE ENTRY_DATE<'" . DBSafe($cut) . "'");
        SQLExec("DELETE FROM tabcalorie_days WHERE DAY_DATE<'" . DBSafe($cut) . "'");
        SQLExec("DELETE FROM tabcalorie_sync_log WHERE RUN_AT<'" . DBSafe($cut) . "'");
        return $cut;
    }

    public static function dayRows($from, $to)
    {
        return SQLSelect("SELECT * FROM tabcalorie_days WHERE DAY_DATE>='" . DBSafe($from) . "'
                          AND DAY_DATE<='" . DBSafe($to) . "' ORDER BY DAY_DATE");
    }

    public static function entryRows($from, $to)
    {
        return SQLSelect("SELECT ENTRY_DATE, SLOT_ID, SLOT_TITLE, TITLE, UNIT_RAW, QUANTITY, UNIT,
                          ENERGY, PROTEIN, FAT, CARBOHYDRATE, FIBER, SUGAR, SOURCE
                          FROM tabcalorie_entries WHERE ENTRY_DATE>='" . DBSafe($from) . "'
                          AND ENTRY_DATE<='" . DBSafe($to) . "' ORDER BY ENTRY_DATE, SLOT_ID");
    }

    public static function queueWrite($entryId, $payload)
    {
        $rec = array(
            'ENTRY_ID' => (int)$entryId,
            'PAYLOAD' => json_encode($payload, JSON_UNESCAPED_UNICODE),
            'STATUS' => 'draft',
            'CREATED_AT' => date('Y-m-d H:i:s'),
        );
        return SQLInsert('tabcalorie_write_queue', $rec);
    }

    public static function writeQueue($limit = 50)
    {
        return SQLSelect("SELECT * FROM tabcalorie_write_queue ORDER BY ID DESC LIMIT " . max(1, (int)$limit));
    }

    /** Nightscout de-duplication marker stored in the same queue table. */
    public static function nsSent($date, $slot)
    {
        $needle = '%"date":"' . DBSafe($date) . '","slot":' . (int)$slot . '%';
        $rec = SQLSelectOne("SELECT ID FROM tabcalorie_write_queue WHERE STATUS='ns_sent' AND PAYLOAD LIKE '" . $needle . "'");
        return (bool)$rec['ID'];
    }

    public static function markNsSent($date, $slot, $message)
    {
        $id = self::queueWrite(0, array('kind' => 'ns', 'date' => $date, 'slot' => (int)$slot));
        self::setWriteStatus($id, 'ns_sent', $message);
        return $id;
    }

    public static function setWriteStatus($id, $status, $message)
    {
        $rec = SQLSelectOne("SELECT * FROM tabcalorie_write_queue WHERE ID=" . (int)$id);
        if (!$rec['ID']) {
            return false;
        }
        $rec['STATUS'] = substr((string)$status, 0, 16);
        $rec['MESSAGE'] = mb_substr((string)$message, 0, 300);
        $rec['SENT_AT'] = date('Y-m-d H:i:s');
        SQLUpdate('tabcalorie_write_queue', $rec);
        return true;
    }
}
