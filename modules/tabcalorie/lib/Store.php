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
                    'FOOD_URL' => isset($item['url']) ? (string)$item['url'] : '',
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

    /**
     * Parse the `activities` array returned by the diary endpoint.
     * Health Connect activity is synced INTO TK, so this is read-only here.
     */
    public static function parseActivities($diary, $date)
    {
        $rows = array();
        if (!is_array($diary) || empty($diary['activities']) || !is_array($diary['activities'])) {
            return $rows;
        }
        foreach ($diary['activities'] as $idx => $a) {
            if (!is_array($a)) {
                continue;
            }
            $id = isset($a['id']) && $a['id'] !== '' ? (string)$a['id'] : '';
            if ($id === '' && isset($a['guidType'])) {
                $id = (string)$a['guidType'];
            }
            $title = isset($a['title']) ? trim((string)$a['title']) : '';
            if ($id === '' && $title === '') {
                continue;
            }
            if ($id === '') {
                $id = md5($title . '|' . $date . '|' . $idx);
            }
            $rows[] = array(
                'ACTIVITY_KEY' => md5($date . '|' . $id),
                'ACTIVITY_DATE' => $date,
                'ACTIVITY_ID' => $id,
                'TITLE' => $title,
                'TYPE' => isset($a['type']) ? (string)$a['type'] : 'activity',
                'ACTIVITY_TYPE' => isset($a['activityType']) ? (string)$a['activityType'] : '',
                'STEPS' => self::round1(self::num(isset($a['steps']) ? $a['steps'] : 0)),
                'ENERGY' => self::round1(self::num(isset($a['energy']) ? $a['energy'] : 0)),
                'UNIT' => isset($a['energyUnit']) ? (string)$a['energyUnit'] : 'kcal',
                'DURATION' => self::round1(self::num(isset($a['duration']) ? $a['duration'] : 0)),
                'RAW_JSON' => mb_substr(json_encode($a, JSON_UNESCAPED_UNICODE), 0, 4000),
                'SYNCED_AT' => date('Y-m-d H:i:s'),
            );
        }
        return $rows;
    }

    /** Idempotent insert/update of activity rows. */
    public static function saveActivities($rows)
    {
        $count = 0;
        foreach ($rows as $row) {
            $key = DBSafe($row['ACTIVITY_KEY']);
            $old = SQLSelectOne("SELECT ID FROM tabcalorie_activities WHERE ACTIVITY_KEY='" . $key . "'");
            $record = $row;
            if ($old['ID']) {
                $record['ID'] = $old['ID'];
                SQLUpdate('tabcalorie_activities', $record);
            } else {
                SQLInsert('tabcalorie_activities', $record);
            }
            $count++;
        }
        return $count;
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
        $old = SQLSelectOne("SELECT * FROM tabcalorie_days WHERE DAY_DATE='" . DBSafe($date) . "'");
        $act = SQLSelectOne("SELECT SUM(STEPS) AS S, SUM(ENERGY) AS E, COUNT(*) AS N
                             FROM tabcalorie_activities WHERE ACTIVITY_DATE='" . DBSafe($date) . "'");
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
            'STEPS' => self::round1(isset($act['S']) ? $act['S'] : 0),
            'ACTIVITY_KCAL' => self::round1(isset($act['E']) ? $act['E'] : 0),
            'ACTIVITY_COUNT' => (int)(isset($act['N']) ? $act['N'] : 0),
            'RAW_JSON' => $old['RAW_JSON'],
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
        if (is_array($diary)) {
            $raw = json_encode($diary, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $day['RAW_JSON'] = mb_substr((string)$raw, 0, 2000000);
        }
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
                'kcal' => 0.0, 'protein' => 0.0, 'fat' => 0.0, 'fiber' => 0.0,
                'sugar' => 0.0, 'count' => 0, 'present' => false);
        }
        $rows = SQLSelect("SELECT SLOT_ID, SUM(CARBOHYDRATE) AS C, SUM(ENERGY) AS E,
                           SUM(PROTEIN) AS P, SUM(FAT) AS F, SUM(FIBER) AS FI, SUM(SUGAR) AS S,
                           COUNT(*) AS N
                           FROM tabcalorie_entries WHERE ENTRY_DATE='" . DBSafe($date) . "'
                           GROUP BY SLOT_ID");
        foreach ($rows as $r) {
            $id = (int)$r['SLOT_ID'];
            if (isset($slots[$id])) {
                $slots[$id]['carbs'] = self::round1($r['C']);
                $slots[$id]['kcal'] = self::round1($r['E']);
                $slots[$id]['protein'] = self::round1($r['P']);
                $slots[$id]['fat'] = self::round1($r['F']);
                $slots[$id]['fiber'] = self::round1($r['FI']);
                $slots[$id]['sugar'] = self::round1($r['S']);
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
            'steps' => self::round1(isset($day['STEPS']) ? $day['STEPS'] : 0),
            'activity_kcal' => self::round1(isset($day['ACTIVITY_KCAL']) ? $day['ACTIVITY_KCAL'] : 0),
            'activity_count' => (int)(isset($day['ACTIVITY_COUNT']) ? $day['ACTIVITY_COUNT'] : 0),
        );
    }

    public static function slotKey($id)
    {
        $keys = array(1 => 'TC_BREAKFAST', 2 => 'TC_SNACK1', 3 => 'TC_LUNCH',
            4 => 'TC_SNACK2', 5 => 'TC_DINNER', 6 => 'TC_LATE_DINNER');
        return isset($keys[(int)$id]) ? $keys[(int)$id] : 'TC_MEAL';
    }

    /**
     * Daily norm share for one meal slot.
     * Main meals (1,3,5) receive MEAL_NORM_SHARE percent each; the remainder is
     * split evenly between the three snack slots (2,4,6).
     */
    public static function mealShare($slotId, $sharePercent)
    {
        $share = max(10, min(50, (float)$sharePercent)) / 100;
        $id = (int)$slotId;
        if (in_array($id, array(1, 3, 5), true)) {
            return $share <= 1 / 3 ? $share : 1 / 3;
        }
        if (in_array($id, array(2, 4, 6), true)) {
            return $share <= 1 / 3 ? (1 - 3 * $share) / 3 : 0;
        }
        return 0;
    }

    /** Norm for a given meal and metric (carbs/protein/fat/kcal). */
    public static function mealNorm($dailyTarget, $slotId, $sharePercent)
    {
        return self::round1(max(0, (float)$dailyTarget) * self::mealShare($slotId, $sharePercent));
    }

    /** Return the MajorDoMo object with per-meal aggregates for the last filled day. */
    public static function updateObject($overview, $gramsPerXe = 10, $cfg = array())
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
            'TotalSugar', 'TotalXE', 'GI', 'Notes', 'Updated', 'Status', 'Raw',
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
            'Status' => self::computeStatus($cfg),
            'Raw' => self::rawPayload($overview['date']),
            'ActivitySteps' => isset($overview['steps']) ? $overview['steps'] : 0,
            'ActivityKcal' => isset($overview['activity_kcal']) ? $overview['activity_kcal'] : 0,
            'ActivityCount' => isset($overview['activity_count']) ? $overview['activity_count'] : 0,
        );
        for ($i = 1; $i <= 6; $i++) {
            $values['Slot' . $i . 'Carbs'] = $overview['slots'][$i]['carbs'];
            $values['Slot' . $i . 'Kcal'] = $overview['slots'][$i]['kcal'];
            $values['Slot' . $i . 'Protein'] = $overview['slots'][$i]['protein'];
            $values['Slot' . $i . 'Fat'] = $overview['slots'][$i]['fat'];
        }
        foreach ($values as $name => $value) {
            $obj->setProperty($name, $value, 0, 'tabcalorie');
        }
        return true;
    }

    /** Lightweight refresh of Status/Raw without a full sync (page loads, failures). */
    public static function refreshStatusObject($cfg)
    {
        if (!function_exists('getObject')) {
            return false;
        }
        $obj = @getObject('TabCalorie');
        if (!$obj) {
            return false;
        }
        $obj->setProperty('Status', self::computeStatus($cfg), 0, 'tabcalorie');
        $obj->setProperty('Raw', self::rawPayload(), 0, 'tabcalorie');
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
        $rows = SQLSelect("SELECT FOOD_GUID, TITLE, UNIT, MAX(FOOD_URL) AS FOOD_URL,
                    SUM(QUANTITY) AS Q, COUNT(*) AS CNT,
                    SUM(ENERGY) AS E, SUM(PROTEIN) AS P, SUM(FAT) AS F,
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
                'FOOD_ID' => $r['FOOD_GUID'],
                'TITLE' => $r['TITLE'],
                'UNIT' => $r['UNIT'],
                'ENERGY_100' => self::round1(self::num($r['E']) * $k),
                'PROTEIN_100' => self::round1(self::num($r['P']) * $k),
                'FAT_100' => self::round1(self::num($r['F']) * $k),
                'CARB_100' => self::round1(self::num($r['C']) * $k),
                'FIBER_100' => self::round1(self::num($r['FI']) * $k),
                'SUGAR_100' => self::round1(self::num($r['S']) * $k),
                'FOOD_URL' => isset($r['FOOD_URL']) ? $r['FOOD_URL'] : '',
                'UPDATED' => date('Y-m-d H:i:s'),
            );
            $old = SQLSelectOne("SELECT * FROM tabcalorie_foods WHERE FOOD_GUID='" . DBSafe($r['FOOD_GUID']) . "'");
            if ($old['ID']) {
                $rec['ID'] = $old['ID'];
                // User-entered service data always wins over imported guesses.
                foreach (array('GI', 'NOTES', 'FOOD_ID', 'FOOD_URL', 'UNIT_GUID',
                    'UNIT_OPTIONS', 'DEFAULT_MULTIPLIER', 'DEFAULT_SLOT', 'SERVICE_JSON') as $keep) {
                    if (isset($old[$keep]) && $old[$keep] !== '') {
                        $rec[$keep] = $old[$keep];
                    }
                }
                SQLUpdate('tabcalorie_foods', $rec);
            } else {
                $rec['UNIT_GUID'] = '0000000000000001';
                $rec['DEFAULT_MULTIPLIER'] = self::round1($q / max(1, (int)$r['CNT']));
                $rec['DEFAULT_SLOT'] = 0;
                $rec['UNIT_OPTIONS'] = '';
                SQLInsert('tabcalorie_foods', $rec);
            }
            $n++;
        }
        return $n;
    }

    /** Full food record by ID (used by the write-back form and queue). */
    public static function foodRecord($id)
    {
        return SQLSelectOne("SELECT * FROM tabcalorie_foods WHERE ID=" . (int)$id);
    }

    /** Create or update a food record from the admin form. */
    public static function saveFoodRecord($data)
    {
        $id = (int)(isset($data['ID']) ? $data['ID'] : 0);
        $old = $id ? self::foodRecord($id) : array();
        $rec = array(
            'FOOD_GUID' => mb_substr(trim((string)(isset($data['FOOD_GUID']) ? $data['FOOD_GUID'] : '')), 0, 64),
            'FOOD_ID' => mb_substr(trim((string)(isset($data['FOOD_ID']) ? $data['FOOD_ID'] : '')), 0, 64),
            'TITLE' => mb_substr(trim((string)(isset($data['TITLE']) ? $data['TITLE'] : '')), 0, 255),
            'UNIT' => mb_substr(trim((string)(isset($data['UNIT']) ? $data['UNIT'] : 'g')), 0, 32),
            'FOOD_URL' => mb_substr(trim((string)(isset($data['FOOD_URL']) ? $data['FOOD_URL'] : '')), 0, 255),
            'UNIT_GUID' => mb_substr(trim((string)(isset($data['UNIT_GUID']) ? $data['UNIT_GUID'] : '0000000000000001')), 0, 64),
            'UNIT_OPTIONS' => mb_substr((string)(isset($data['UNIT_OPTIONS']) ? $data['UNIT_OPTIONS'] : ''), 0, 8000),
            'DEFAULT_MULTIPLIER' => self::round1(self::num(isset($data['DEFAULT_MULTIPLIER']) ? $data['DEFAULT_MULTIPLIER'] : 100)),
            'DEFAULT_SLOT' => max(0, min(6, (int)(isset($data['DEFAULT_SLOT']) ? $data['DEFAULT_SLOT'] : 0))),
            'GI' => max(0, min(200, self::num(isset($data['GI']) ? $data['GI'] : 0))),
            'NOTES' => mb_substr(strip_tags((string)(isset($data['NOTES']) ? $data['NOTES'] : '')), 0, 1000),
            'ENERGY_100' => self::round1(self::num(isset($data['ENERGY_100']) ? $data['ENERGY_100'] : 0)),
            'PROTEIN_100' => self::round1(self::num(isset($data['PROTEIN_100']) ? $data['PROTEIN_100'] : 0)),
            'FAT_100' => self::round1(self::num(isset($data['FAT_100']) ? $data['FAT_100'] : 0)),
            'CARB_100' => self::round1(self::num(isset($data['CARB_100']) ? $data['CARB_100'] : 0)),
            'FIBER_100' => self::round1(self::num(isset($data['FIBER_100']) ? $data['FIBER_100'] : 0)),
            'SUGAR_100' => self::round1(self::num(isset($data['SUGAR_100']) ? $data['SUGAR_100'] : 0)),
            'UPDATED' => date('Y-m-d H:i:s'),
        );
        if ($rec['FOOD_GUID'] === '') {
            $rec['FOOD_GUID'] = md5($rec['TITLE'] . '|' . $rec['FOOD_URL']);
        }
        if ($rec['FOOD_ID'] === '') {
            $rec['FOOD_ID'] = $rec['FOOD_GUID'];
        }
        if (isset($old['SERVICE_JSON']) && $old['SERVICE_JSON'] !== '') {
            $rec['SERVICE_JSON'] = $old['SERVICE_JSON'];
        }
        if ($old['ID']) {
            $rec['ID'] = $old['ID'];
            SQLUpdate('tabcalorie_foods', $rec);
            return (int)$old['ID'];
        }
        return (int)SQLInsert('tabcalorie_foods', $rec);
    }

    public static function deleteFoodRecord($id)
    {
        SQLExec("DELETE FROM tabcalorie_foods WHERE ID=" . (int)$id);
        return true;
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

    /**
     * Retention cleanup: entries/days by data retention, sync log by age AND
     * row count (rotation), write queue by relevance period (TTL).
     */
    public static function cleanup($retentionDays, $cfg = array())
    {
        $days = max(30, (int)$retentionDays);
        $cut = date('Y-m-d', time() - $days * 86400);
        SQLExec("DELETE FROM tabcalorie_entries WHERE ENTRY_DATE<'" . DBSafe($cut) . "'");
        SQLExec("DELETE FROM tabcalorie_days WHERE DAY_DATE<'" . DBSafe($cut) . "'");
        SQLExec("DELETE FROM tabcalorie_activities WHERE ACTIVITY_DATE<'" . DBSafe($cut) . "'");

        $logDays = max(1, (int)(isset($cfg['LOG_RETENTION_DAYS']) ? $cfg['LOG_RETENTION_DAYS'] : 30));
        $logCut = date('Y-m-d H:i:s', time() - $logDays * 86400);
        SQLExec("DELETE FROM tabcalorie_sync_log WHERE RUN_AT<'" . DBSafe($logCut) . "'");
        $maxRows = max(100, (int)(isset($cfg['LOG_MAX_ROWS']) ? $cfg['LOG_MAX_ROWS'] : 2000));
        $cnt = SQLSelectOne("SELECT COUNT(*) AS N FROM tabcalorie_sync_log");
        if ((int)$cnt['N'] > $maxRows) {
            SQLExec("DELETE FROM tabcalorie_sync_log ORDER BY ID ASC LIMIT " . ((int)$cnt['N'] - $maxRows));
        }

        $ttl = max(1, (int)(isset($cfg['WRITE_QUEUE_TTL_DAYS']) ? $cfg['WRITE_QUEUE_TTL_DAYS'] : 7));
        $queueCut = date('Y-m-d H:i:s', time() - $ttl * 86400);
        SQLExec("DELETE FROM tabcalorie_write_queue WHERE CREATED_AT<'" . DBSafe($queueCut) . "'");
        return $cut;
    }

    /** All activity records for a day. */
    public static function activityRows($date)
    {
        return SQLSelect("SELECT * FROM tabcalorie_activities WHERE ACTIVITY_DATE='" . DBSafe($date) . "'
                          ORDER BY ID");
    }

    /**
     * Full raw payload for the last day that has at least one record:
     * original TK diary JSON plus normalized entries and activities.
     */
    public static function rawPayload($date = null)
    {
        if (!$date) {
            $date = self::lastFilledDate();
        }
        $day = SQLSelectOne("SELECT RAW_JSON FROM tabcalorie_days WHERE DAY_DATE='" . DBSafe($date) . "'");
        $diary = array();
        if (!empty($day['RAW_JSON'])) {
            $decoded = json_decode($day['RAW_JSON'], true);
            if (is_array($decoded)) {
                $diary = $decoded;
            }
        }
        $payload = array(
            'date' => $date,
            'diary' => $diary,
            'entries' => SQLSelect("SELECT * FROM tabcalorie_entries WHERE ENTRY_DATE='" . DBSafe($date) . "'
                                    ORDER BY SLOT_ID, ID"),
            'activities' => self::activityRows($date),
        );
        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        return mb_substr((string)$json, 0, 2000000);
    }

    /**
     * Integration status for the object: 0 on any problem or when the background
     * cycle is disabled/stale, 1 when everything looks healthy.
     */
    public static function computeStatus($cfg)
    {
        if ((int)(isset($cfg['SYNC_ENABLED']) ? $cfg['SYNC_ENABLED'] : 0) !== 1) {
            return 0;
        }
        if (!empty($cfg['LAST_ERROR'])) {
            return 0;
        }
        if ((int)(isset($cfg['CIRCUIT_UNTIL']) ? $cfg['CIRCUIT_UNTIL'] : 0) > time()) {
            return 0;
        }
        $last = (int)getGlobal('cycle_tabcalorieRunTs');
        if ($last <= 0 || time() - $last > 600) {
            return 0;
        }
        return 1;
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
