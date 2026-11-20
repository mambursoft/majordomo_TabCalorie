<?php
/**
 * Integration test against tests/mock_server.php.
 * Start the mock first: php -S 127.0.0.1:8765 tests/mock_server.php
 * Run: php tests/test_api.php
 */

require_once(__DIR__ . '/../modules/tabcalorie/lib/Api.php');
require_once(__DIR__ . '/../modules/tabcalorie/lib/Store.php');

$base = getenv('TC_TEST_BASE_URL') ?: 'http://127.0.0.1:8765';
$cfg = array(
    'BASE_URL' => $base,
    'EMAIL' => 'user@example.org',
    'PASSWORD' => 'test-password-not-real',
);

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

$api = new TabCalorieApi($cfg);

echo "== login ==\n";
try {
    check('login ok', $api->login() === true);
} catch (Exception $e) {
    check('login ok', false, $api->getLastError());
}

echo "== diary ==\n";
try {
    $diary = $api->getDiary('2026-11-19');
    check('6 slots', isset($diary['times']) && count($diary['times']) === 6);
    $rows = TabCalorieStore::parseDiary($diary, '2026-11-19');
    check('2 entries parsed', count($rows) === 2, 'got ' . count($rows));
} catch (Exception $e) {
    check('diary ok', false, $api->getLastError());
}

echo "== confirmed write-back ==\n";
try {
    $msg = $api->addFood(array(
        'guid' => 'food-oat', 'title' => 'Вівсянка', 'url' => 'vivsianka',
        'date' => '2026-11-19', 'multiplier' => '150', 'diaryTimeGuid' => '3',
    ));
    check('message returned', $msg === 'Zapsáno', $msg);
    $logFile = __DIR__ . '/fixtures/last_request.json';
    $log = json_decode(file_get_contents($logFile), true);
    check('POST used', isset($log['method']) && $log['method'] === 'POST');
    check('session cookie sent', isset($log['cookie']) && strpos($log['cookie'], 'TCSESSION=') !== false,
        isset($log['cookie']) ? $log['cookie'] : 'none');
    $body = isset($log['body']) ? json_decode($log['body'], true) : array();
    check('meal slot', isset($body['diaryTimeGuid']) && $body['diaryTimeGuid'] === '3');
    check('grams', isset($body['multiplier']) && $body['multiplier'] === '150');
    check('unit guid', isset($body['unitGuid']) && $body['unitGuid'] === '0000000000000001');
    check('no password in body', strpos(json_encode($body), 'test-password') === false);
} catch (Exception $e) {
    check('write-back ok', false, $api->getLastError());
}

echo "== food service data in write-back ==\n";
try {
    $api->addFood(array(
        'guid' => 'food-rice', 'title' => 'Рис', 'url' => 'rys', 'date' => '2026-11-19',
        'multiplier' => '200', 'diaryTimeGuid' => '3',
        'unitGuid' => 'unit-custom-123',
        'unitOptions' => array(array('id' => 'unit-custom-123', 'title' => 'порція (200 g)', 'multiplier' => 200)),
    ));
    $body2 = json_decode(file_get_contents($logFile), true);
    $body2 = json_decode($body2['body'], true);
    check('custom unit guid', isset($body2['unitGuid']) && $body2['unitGuid'] === 'unit-custom-123');
    check('custom unit options',
        isset($body2['unitOptions'][0]['multiplier']) && $body2['unitOptions'][0]['multiplier'] == 200);
} catch (Exception $e) {
    check('write-back ok', false, $api->getLastError());
}

echo "== diagnostics ==\n";
$steps = $api->diagnose();
$byStep = array();
foreach ($steps as $s) {
    $byStep[$s['step']] = $s;
}
check('base_url ok', isset($byStep['base_url']) && $byStep['base_url']['ok']);
check('dns ok', isset($byStep['dns']) && $byStep['dns']['ok']);
check('login step ok', isset($byStep['login']) && $byStep['login']['ok']);
check('diary step ok', isset($byStep['diary']) && $byStep['diary']['ok']);
check('credentials masked', isset($byStep['credentials']) && strpos($byStep['credentials']['message'], '***') !== false,
    isset($byStep['credentials']) ? $byStep['credentials']['message'] : '');

echo "\nRESULT: $pass passed, $fail failed\n";
exit($fail ? 1 : 0);
