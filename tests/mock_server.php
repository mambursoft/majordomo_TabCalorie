<?php
/** Local mock of the TK internal API for tests. Never used in production. */

$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$logFile = __DIR__ . '/fixtures/last_request.json';

if ($path === '/login/create') {
    header('Content-Type: application/json');
    header('Set-Cookie: TCSESSION=test-session-123; Path=/; HttpOnly');
    echo json_encode(array('code' => 0, 'message' => 'OK', 'data' => 'logged in'));
    return true;
}
if (preg_match('#^/user/diary/\d{2}\.\d{2}\.\d{4}/get$#', $path)) {
    header('Content-Type: application/json');
    echo file_get_contents(__DIR__ . '/fixtures/diary.json');
    return true;
}
if ($path === '/user/foodstuff/add') {
    file_put_contents($logFile, json_encode(array(
        'method' => $_SERVER['REQUEST_METHOD'],
        'body' => file_get_contents('php://input'),
        'cookie' => isset($_SERVER['HTTP_COOKIE']) ? $_SERVER['HTTP_COOKIE'] : '',
    )));
    header('Content-Type: application/json');
    echo json_encode(array('code' => 0, 'message' => 'Zapsáno', 'data' => ''));
    return true;
}
if ($path === '/user/diary/summary/' . date('d.m.Y') . '/get' || strpos($path, '/user/diary/summary/') === 0) {
    header('Content-Type: application/json');
    echo json_encode(array('code' => 0, 'message' => 'OK', 'data' => array('items' => array())));
    return true;
}
header('Content-Type: application/json');
echo json_encode(array('code' => 0, 'message' => 'OK', 'data' => array()));
return true;
