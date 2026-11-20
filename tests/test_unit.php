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

echo "== Store::parseUnit ==\n";
$u = TabCalorieStore::parseUnit('166 x 1 g');
check('quantity 166', abs($u[0] - 166) < 0.001, json_encode($u));
check('unit g', $u[1] === 'g', json_encode($u));
$u2 = TabCalorieStore::parseUnit('200 ml');
check('plain quantity', abs($u2[0] - 200) < 0.001, json_encode($u2));
$u3 = TabCalorieStore::parseUnit('2 x 200 g');
check('multiplier applied', abs($u3[0] - 400) < 0.001 && $u3[1] === 'g', json_encode($u3));

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
$idempotentKey = $rows[0]['ENTRY_KEY'];
check('stable key', $idempotentKey === md5('2026-11-19|1|food-oat|150 x 1 g'));

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
