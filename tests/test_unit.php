<?php
/**
 * Unit tests for TabCalorie pure logic (no web server required).
 * Run: php tests/test_unit.php
 */

require_once(__DIR__ . '/stubs.php');
require_once(__DIR__ . '/../modules/tabcalorie/lib/Api.php');
require_once(__DIR__ . '/../modules/tabcalorie/lib/Store.php');
require_once(__DIR__ . '/../modules/tabcalorie/lib/Exporter.php');
require_once(__DIR__ . '/../modules/tabcalorie/lib/Chart.php');

$pass = 0;
$fail = 0;
function check($name, $condition, $detail = '')
{
    global $pass, $fail;
    if ($condition) {
        $pass++;
        echo "  PASS  $name\n";
    } else {
        $fail++;
        echo "  FAIL  $name" . ($detail ? " -- $detail" : '') . "\n";
    }
}

echo "== Store::num ==\n";
check('Czech spaced decimal', abs(TabCalorieStore::num('1 234,5') - 1234.5) < 0.001);
check('plain float', abs(TabCalorieStore::num('12.5') - 12.5) < 0.001);
check('null -> zero', TabCalorieStore::num(null) === 0.0);
check('US thousands', abs(TabCalorieStore::num('1,234.5') - 1234.5) < 0.001);
check('EU thousands', abs(TabCalorieStore::num('1.234,5') - 1234.5) < 0.001);
check('garbage rejected', TabCalorieStore::num('abc123') === 0.0);

echo "== Store::parseUnit ==\n";
$u = TabCalorieStore::parseUnit('166 x 1 g');
check('quantity 166', abs($u[0] - 166) < 0.001, json_encode($u));
check('unit g', $u[1] === 'g', json_encode($u));
$u2 = TabCalorieStore::parseUnit('200 ml');
check('plain quantity', abs($u2[0] - 200) < 0.001, json_encode($u2));
$u3 = TabCalorieStore::parseUnit('2 x 200 g');
check('multiplier applied', abs($u3[0] - 400) < 0.001 && $u3[1] === 'g', json_encode($u3));
$u4 = TabCalorieStore::parseUnit('100 г');
check('cyrillic gram', abs($u4[0] - 100) < 0.001 && $u4[1] === 'г', json_encode($u4));
$u5 = TabCalorieStore::parseUnit('166 х 1 g');
check('cyrillic x', abs($u5[0] - 166) < 0.001 && $u5[1] === 'g', json_encode($u5));
$u6 = TabCalorieStore::parseUnit('1 склянка (200 ml)');
check('parenthesized unit', abs($u6[0] - 200) < 0.001 && $u6[1] === 'ml', json_encode($u6));

echo "== Store::parseDiary ==\n";
$fixture = json_decode(file_get_contents(__DIR__ . '/fixtures/diary.json'), true);
check('fixture valid', is_array($fixture));
$rows = TabCalorieStore::parseDiary($fixture['data'], '2026-11-19');
check('two food rows', count($rows) === 2, 'got ' . count($rows));
check('slot ids', $rows[0]['SLOT_ID'] === 1 && $rows[1]['SLOT_ID'] === 3);
check('carbs parsed', abs($rows[0]['CARBOHYDRATE'] - 40) < 0.01 && abs($rows[1]['CARBOHYDRATE'] - 60) < 0.01);
check('unit raw kept', $rows[0]['UNIT_RAW'] === '150 x 1 g');
check('guid used', $rows[0]['FOOD_GUID'] === 'food-oat');
check('source tk', $rows[0]['SOURCE'] === 'tk');
check('meal time parsed', $rows[0]['MEAL_TIME'] === '08:15');

$dupDiary = array('times' => array(array('id' => '1', 'title' => 'Сніданок', 'foodstuff' => array(
    array('id' => 'same-1', 'guidType' => 'apple', 'title' => 'Яблуко', 'unit' => '100 x 1 g', 'energy' => '50', 'carbohydrate' => '10'),
    array('id' => 'same-2', 'guidType' => 'apple', 'title' => 'Яблуко', 'unit' => '100 x 1 g', 'energy' => '50', 'carbohydrate' => '10'),
))));
$dupRows = TabCalorieStore::parseDiary($dupDiary, '2026-11-19');
check('two identical foods kept', count($dupRows) === 2);
check('duplicate keys differ', $dupRows[0]['ENTRY_KEY'] !== $dupRows[1]['ENTRY_KEY']);
$idempotentKey = $rows[0]['ENTRY_KEY'];
check('stable key', $idempotentKey === md5('2026-11-19|1|food-oat|150 x 1 g|e1'));

echo "== Store::overview ==\n";
$ov = TabCalorieStore::overview('2026-11-19');
check('last date', $ov['date'] === '2026-11-19');
check('partial day', $ov['partial'] === true && $ov['slot_count'] === 2);
check('slot 1 carbs', abs($ov['slots'][1]['carbs'] - 40) < 0.01);
check('empty slot 5', $ov['slots'][5]['present'] === false && $ov['slots'][5]['carbs'] === 0.0);
check('main group = 100', abs($ov['groups']['main'] - 100) < 0.01, json_encode($ov['groups']));
check('snacks group = 0', abs($ov['groups']['snacks']) < 0.01);
check('total = 100', abs($ov['total_carbs'] - 100) < 0.01);
check('notes from slot', strpos($ov['notes'], 'кориц') !== false, $ov['notes']);

echo "== Dishes by meal ==\n";
$byMeal = TabCalorieStore::dayDishes('2026-11-19');
check('grouped by slot', isset($byMeal[1], $byMeal[3]) && count($byMeal) === 2);
check('dish title kept', $byMeal[1][0]['TITLE'] === 'Вівсянка');
check('slot 2 empty', empty($byMeal[2]));
check('meal time from API', TabCalorieStore::mealTime(1, $byMeal[1][0]['MEAL_TIME']) === '08:15');
check('meal time fallback', TabCalorieStore::mealTime(3, '') === '13:00');

echo "== Exporter ==\n";
list($from, $to) = TabCalorieExporter::periodRange('week', '2026-11-19');
check('week range', $from === '2026-11-13' && $to === '2026-11-19', "$from..$to");
check('date UA', TabCalorieExporter::dateUa('2026-11-19') === '19.11.2026');
check('formula escaped', strpos(TabCalorieExporter::cell('=SUM(A1)'), "'=") === 0);
$csv = TabCalorieExporter::build(array('A', 'B'), array(array('x;y', 'z')));
check('BOM', strncmp($csv, "\xEF\xBB\xBF", 3) === 0);
check('delimiter', strpos($csv, '"A";"B"') !== false);
check('quote escaped', strpos($csv, '"x;y"') !== false);

echo "== Meal norms ==\n";
check('main share 25%', abs(TabCalorieStore::mealShare(1, 25) - 0.25) < 0.001);
check('snack share 1/12', abs(TabCalorieStore::mealShare(2, 25) - (1 / 12)) < 0.001);
check('sum of shares = 1', abs(
    TabCalorieStore::mealShare(1, 25) + TabCalorieStore::mealShare(2, 25) +
    TabCalorieStore::mealShare(3, 25) + TabCalorieStore::mealShare(4, 25) +
    TabCalorieStore::mealShare(5, 25) + TabCalorieStore::mealShare(6, 25) - 1) < 0.001);
check('main carbs norm 30g', abs(TabCalorieStore::mealNorm(120, 1, 25) - 30) < 0.01);
check('snack carbs norm 10g', abs(TabCalorieStore::mealNorm(120, 2, 25) - 10) < 0.01);
check('main kcal norm 450', abs(TabCalorieStore::mealNorm(1800, 3, 25) - 450) < 0.01);
check('norm zero when target off', TabCalorieStore::mealNorm(0, 1, 25) === 0.0);
check('slot protein aggregated', abs($ov['slots'][1]['protein'] - 12.5) < 0.01);
check('slot fat aggregated', abs($ov['slots'][3]['fat'] - 0.9) < 0.01);

echo "== Activity (Health Connect -> TK) ==\n";
$acts = TabCalorieStore::parseActivities($fixture['data'], '2026-11-19');
check('two activities', count($acts) === 2, 'got ' . count($acts));
check('steps parsed', abs($acts[0]['STEPS'] - 3200) < 0.01);
check('czech energy', abs($acts[0]['ENERGY'] - 180) < 0.01);
check('activity id', $acts[0]['ACTIVITY_ID'] === 'a1');
$daySaved = TabCalorieStore::saveDay('2026-11-19', $fixture['data']);
check('day steps aggregated', abs($daySaved['STEPS'] - 3200) < 0.01);
check('day kcal aggregated', abs($daySaved['ACTIVITY_KCAL'] - 440) < 0.01);
check('raw stored', strpos($daySaved['RAW_JSON'], 'activities') !== false);
$ovAct = TabCalorieStore::overview('2026-11-19');
check('overview activity', abs($ovAct['steps'] - 3200) < 0.01 && $ovAct['activity_count'] === 2);
$rawDecoded = json_decode(TabCalorieStore::rawPayload('2026-11-19'), true);
check('raw payload keys', isset($rawDecoded['date'], $rawDecoded['diary'], $rawDecoded['entries'], $rawDecoded['activities']));

echo "== Retention / rotation ==\n";
TabCalorieStore::cleanup(1825, array(
    'LOG_RETENTION_DAYS' => 30, 'LOG_MAX_ROWS' => 2000, 'WRITE_QUEUE_TTL_DAYS' => 7));
$sqls = implode(' ', $GLOBALS['tc_sql_exec']);
check('log age rotation', strpos($sqls, 'tabcalorie_sync_log WHERE RUN_AT<') !== false);
check('log row cap', strpos($sqls, 'DELETE FROM tabcalorie_sync_log ORDER BY ID ASC LIMIT 500') !== false, $sqls);
check('queue TTL', strpos($sqls, 'tabcalorie_write_queue WHERE CREATED_AT<') !== false);
check('activity retention', strpos($sqls, 'tabcalorie_activities WHERE ACTIVITY_DATE<') !== false);

echo "== Object status ==\n";
check('status off when cycle disabled',
    TabCalorieStore::computeStatus(array('SYNC_ENABLED' => 0)) === 0);
setGlobal('cycle_tabcalorieRunTs', time());
check('status healthy',
    TabCalorieStore::computeStatus(array('SYNC_ENABLED' => 1, 'LAST_ERROR' => '', 'CIRCUIT_UNTIL' => 0)) === 1);
check('status error',
    TabCalorieStore::computeStatus(array('SYNC_ENABLED' => 1, 'LAST_ERROR' => 'boom', 'CIRCUIT_UNTIL' => 0)) === 0);
check('status circuit open',
    TabCalorieStore::computeStatus(array('SYNC_ENABLED' => 1, 'LAST_ERROR' => '', 'CIRCUIT_UNTIL' => time() + 300)) === 0);
setGlobal('cycle_tabcalorieRunTs', time() - 3600);
check('status stale cycle',
    TabCalorieStore::computeStatus(array('SYNC_ENABLED' => 1, 'LAST_ERROR' => '', 'CIRCUIT_UNTIL' => 0)) === 0);
setGlobal('cycle_tabcalorieRunTs', time());

echo "== Backoff / URL guard / Raw cap ==\n";
check('backoff 60s', TabCalorieStore::backoffSeconds(1) === 60);
check('backoff 120s', TabCalorieStore::backoffSeconds(2) === 120);
check('backoff capped', TabCalorieStore::backoffSeconds(20) === 3600);
check('reject http URL', TabCalorieStore::validRemoteUrl('http://example.org') === false);
check('reject IP literal', TabCalorieStore::validRemoteUrl('https://127.0.0.1') === false);
check('reject userinfo', TabCalorieStore::validRemoteUrl('https://user:pass@example.org') === false);
check('raw byte cap', TabCalorieStore::MAX_RAW_BYTES <= 60000);

echo "== CSV columns ==\n";
$csvDaily = TabCalorieExporter::dailyCsv('2026-11-19', '2026-11-19', ';');
$csvDetailed = TabCalorieExporter::detailedCsv('2026-11-19', '2026-11-19', ';');
check('daily header columns', count(explode(';', trim(explode("\r\n", $csvDaily)[0]))) >= 14);
check('detailed header columns', count(explode(';', trim(explode("\r\n", $csvDetailed)[0]))) >= 12);

echo "== Chart ==\n";
$url = TabCalorieChart::standardUrl(7);
check('pChart url', strpos($url, 'pChart/?') === 0 && strpos($url, 'TabCalorie.Slot1Carbs') !== false);
check('all series', strpos($url, 'Slot3Carbs') !== false && strpos($url, 'Slot5Carbs') !== false && strpos($url, 'TotalCarbs') !== false);
$svg = TabCalorieChart::svg(7);
check('svg generated', strpos($svg, '<svg') !== false);

echo "== Api (no network) ==\n";
$api = new TabCalorieApi(array('BASE_URL' => 'https://www.tablycjakalorijnosti.com.ua'));
check('default base url', $api->baseUrl() === 'https://www.tablycjakalorijnosti.com.ua');

echo "== Demo mode ==\n";
$demo = new TabCalorieApi(array('DEMO_MODE' => 1));
check('demo login', $demo->login() === true);
$demoDiary = $demo->getDiary('2026-11-19');
check('demo 6 slots', isset($demoDiary['times']) && count($demoDiary['times']) === 6);
check('demo parses', count(TabCalorieStore::parseDiary($demoDiary, '2026-11-19')) === 2);
check('demo diagnostics', $demo->diagnose()[0]['step'] === 'demo');

echo "\nRESULT: $pass passed, $fail failed\n";
exit($fail ? 1 : 0);
