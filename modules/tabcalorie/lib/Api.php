<?php
/**
 * TabCalorie API client for MajorDoMo.
 *
 * UNOFFICIAL internal API of the "Таблиця калорійності" web portal
 * (tablycjakalorijnosti.com.ua / kaloricketabulky.cz). There is no public API.
 * Be a respectful guest: slow polling, single request at a time, timeouts,
 * bounded response size, no credential leakage.
 *
 * @package tabcalorie
 * @license MIT
 */

require_once(dirname(__FILE__) . '/Demo.php');

class TabCalorieApi
{
    /** @var array module config */
    private $config;
    /** @var array session cookies (name => value) */
    private $cookies = array();
    /** @var string last raw error, always sanitized before use */
    private $lastError = '';
    /** @var int last HTTP code */
    private $httpCode = 0;

    const UA = 'MajorDoMo-TabCalorie/1.0 (+https://github.com/mambursoft/majordomo_TabCalorie)';
    const MAX_BYTES = 2097152; // 2 MiB

    public function __construct($config)
    {
        $this->config = is_array($config) ? $config : array();
    }

    public function getLastError()
    {
        return $this->lastError;
    }

    public function getHttpCode()
    {
        return $this->httpCode;
    }

    public function baseUrl()
    {
        $url = isset($this->config['BASE_URL']) ? trim($this->config['BASE_URL']) : '';
        if ($url === '') {
            $url = 'https://www.tablycjakalorijnosti.com.ua';
        }
        return rtrim($url, '/');
    }

    /** Offline demo mode: no network, no credentials, deterministic data. */
    public function isDemo()
    {
        return (int)(isset($this->config['DEMO_MODE']) ? $this->config['DEMO_MODE'] : 0) === 1;
    }

    /**
     * Low-level cURL request. Never logs credentials.
     *
     * @param string $url absolute URL
     * @param string $method GET|POST
     * @param array|null $payload JSON body
     * @param bool $captureCookies read Set-Cookie from response
     * @return array|null decoded JSON or null
     * @throws Exception on transport/HTTP/JSON error
     */
    private function request($url, $method = 'GET', $payload = null, $captureCookies = false)
    {
        $ch = curl_init();
        $headers = array(
            'Accept: application/json',
            'User-Agent: ' . self::UA,
            'Cache-Control: no-cache',
        );
        if ($this->cookies) {
            $pairs = array();
            foreach ($this->cookies as $k => $v) {
                $pairs[] = $k . '=' . $v;
            }
            $headers[] = 'Cookie: ' . implode('; ', $pairs);
        }

        curl_setopt_array($ch, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 20,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_ENCODING => '',
        ));
        if ($method === 'POST') {
            $body = $payload === null ? '' : json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
            $headers[] = 'Content-Type: application/json; charset=utf-8';
            $headers[] = 'Content-Length: ' . strlen($body);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        }

        $raw = curl_exec($ch);
        $errno = curl_errno($ch);
        $err = curl_error($ch);
        $headerSize = (int)curl_getinfo($ch, CURLINFO_HEADER_SIZE);
        $this->httpCode = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($errno) {
            $this->lastError = 'curl error ' . (int)$errno . ': ' . $this->sanitize($err);
            throw new Exception($this->lastError);
        }
        if (!is_string($raw)) {
            $this->lastError = 'empty response';
            throw new Exception($this->lastError);
        }
        $headerBlock = substr($raw, 0, $headerSize);
        $body = substr($raw, $headerSize);
        if (strlen($body) > self::MAX_BYTES) {
            $this->lastError = 'response too large';
            throw new Exception($this->lastError);
        }
        if ($captureCookies) {
            $this->captureCookies($headerBlock);
        }
        if ($this->httpCode < 200 || $this->httpCode >= 300) {
            $this->lastError = 'HTTP ' . $this->httpCode;
            throw new Exception($this->lastError);
        }
        $decoded = json_decode($body, true);
        if (!is_array($decoded)) {
            $this->lastError = 'invalid JSON response (HTTP ' . $this->httpCode . ')';
            throw new Exception($this->lastError);
        }
        return $decoded;
    }

    /** Parse all Set-Cookie headers and keep name/value pairs (no attributes). */
    private function captureCookies($headerBlock)
    {
        foreach (preg_split('/\r?\n/', $headerBlock) as $line) {
            if (!preg_match('/^set-cookie\s*:\s*(.+)$/i', trim($line), $m)) {
                continue;
            }
            $pair = trim(explode(';', $m[1], 2)[0]);
            if ($pair === '' || strpos($pair, '=') === false) {
                continue;
            }
            list($name, $value) = explode('=', $pair, 2);
            $name = trim($name);
            if ($name === '') {
                continue;
            }
            if (strtolower($value) === 'deleted' || $value === '""') {
                unset($this->cookies[$name]);
                continue;
            }
            $this->cookies[$name] = $value;
        }
    }

    /** Remove anything that could leak credentials or personal data. */
    private function sanitize($message)
    {
        $message = (string)$message;
        $message = preg_replace('/(password|passwd|secret|token|email|cookie)\s*[:=]\s*\S+/i', '$1=***', $message);
        $message = preg_replace('/[\r\n\t]+/', ' ', $message);
        return mb_substr($message, 0, 300);
    }

    private function envelope($response, $context)
    {
        if (!isset($response['code'])) {
            $this->lastError = $context . ': unexpected response (no code)';
            throw new Exception($this->lastError);
        }
        if ((int)$response['code'] !== 0) {
            $msg = isset($response['message']) ? $this->sanitize($response['message']) : 'code ' . $response['code'];
            $this->lastError = $context . ': ' . $msg;
            throw new Exception($this->lastError);
        }
        return isset($response['data']) ? $response['data'] : array();
    }

    /**
     * Authenticate. The endpoint requires MD5 of the UTF-8 password (legacy API).
     * On failure the body code is non-zero even with HTTP 200.
     */
    public function login()
    {
        if ($this->isDemo()) {
            $this->cookies['DEMO'] = '1';
            return true;
        }
        $email = isset($this->config['EMAIL']) ? trim($this->config['EMAIL']) : '';
        $password = isset($this->config['PASSWORD']) ? (string)$this->config['PASSWORD'] : '';
        if ($email === '' || $password === '') {
            $this->lastError = 'email/password not configured';
            throw new Exception($this->lastError);
        }
        $url = $this->baseUrl() . '/login/create?format=json&voucher=false';
        $payload = array(
            'email' => $email,
            'password' => md5($password),
        );
        $response = $this->request($url, 'POST', $payload, true);
        $this->envelope($response, 'login');
        if (!$this->cookies) {
            $this->lastError = 'login returned no session cookie';
            throw new Exception($this->lastError);
        }
        return true;
    }

    /** GET /user/diary/ДД.ММ.РРРР/get?format=json */
    public function getDiary($date)
    {
        if ($this->isDemo()) {
            return TabCalorieDemo::diary($date);
        }
        $pathDate = date('d.m.Y', strtotime($date));
        $url = $this->baseUrl() . '/user/diary/' . $pathDate . '/get?format=json';
        $response = $this->request($url, 'GET', null, true);
        return $this->envelope($response, 'diary ' . $pathDate);
    }

    /** GET /user/diary/summary/ДД.ММ.РРРР/get?format=json */
    public function getSummary($date)
    {
        if ($this->isDemo()) {
            return array('items' => array());
        }
        $pathDate = date('d.m.Y', strtotime($date));
        $url = $this->baseUrl() . '/user/diary/summary/' . $pathDate . '/get?format=json';
        $response = $this->request($url, 'GET');
        return $this->envelope($response, 'summary ' . $pathDate);
    }

    /** Free text food search from the official autocomplete endpoint. */
    public function searchFood($query)
    {
        $url = $this->baseUrl() . '/autocomplete/foodstuff-activity-meal?format=json&query=' . rawurlencode($query);
        $response = $this->request($url, 'GET');
        return is_array($response) ? $response : array();
    }

    /** Product form for unit options (used before a confirmed write-back). */
    public function getFoodForm($guid)
    {
        if ($this->isDemo()) {
            return array('guid' => $guid, 'unitGuid' => '0000000000000001',
                'unitOptions' => array(
                    array('id' => '0000000000000001', 'title' => '1 g', 'multiplier' => 1),
                    array('id' => 'demo-100', 'title' => '100 g', 'multiplier' => 100),
                ), 'multiplier' => 100);
        }
        $url = $this->baseUrl() . '/user/foodstuff/add/form/' . rawurlencode($guid) . '?format=json';
        $response = $this->request($url, 'GET');
        return $this->envelope($response, 'food form');
    }

    /** Normalize the food URL: never duplicate the "potraviny/" prefix. */
    private function foodUrl($url)
    {
        $url = ltrim($url, '/');
        if ($url === '') {
            return 'potraviny/';
        }
        if (stripos($url, 'potraviny/') === 0) {
            return $url;
        }
        return 'potraviny/' . $url;
    }

    /** Unit options from the food dictionary, with a safe gram fallback. */
    private function unitOptions($params)
    {
        if (isset($params['unitOptions'])) {
            $decoded = is_string($params['unitOptions'])
                ? json_decode($params['unitOptions'], true) : $params['unitOptions'];
            if (is_array($decoded) && $decoded) {
                $out = array();
                foreach ($decoded as $u) {
                    if (!is_array($u) || !isset($u['id'])) {
                        continue;
                    }
                    $out[] = array(
                        'id' => (string)$u['id'],
                        'title' => isset($u['title']) ? (string)$u['title'] : '1 g',
                        'multiplier' => isset($u['multiplier']) ? (float)$u['multiplier'] : 1,
                    );
                }
                if ($out) {
                    return $out;
                }
            }
        }
        return array(array('id' => '0000000000000001', 'title' => '1 g', 'multiplier' => 1));
    }

    /**
     * Write a food entry back to TK. IMPORTANT: unofficial endpoint.
     * Must only be called after explicit user confirmation.
     *
     * @param array $params guid,title,url,date,multiplier,diaryTimeGuid
     * @return string server message
     */
    public function addFood($params)
    {
        $dto = array(
            'guid' => (string)(isset($params['guid']) ? $params['guid'] : ''),
            'title' => (string)(isset($params['title']) ? $params['title'] : ''),
            'url' => $this->foodUrl(isset($params['url']) ? (string)$params['url'] : ''),
            'date' => date('d.m.Y', strtotime($params['date'])),
            'multiplier' => (string)(isset($params['multiplier']) ? $params['multiplier'] : '100'),
            'diaryTimeGuid' => (string)(isset($params['diaryTimeGuid']) ? $params['diaryTimeGuid'] : '1'),
            'unitGuid' => isset($params['unitGuid']) && $params['unitGuid'] !== ''
                ? (string)$params['unitGuid'] : '0000000000000001',
            'unitOptions' => $this->unitOptions($params),
        );
        $url = $this->baseUrl() . '/user/foodstuff/add?format=json';
        $response = $this->request($url, 'POST', $dto, true);
        $this->envelope($response, 'food add');
        return isset($response['message']) ? $this->sanitize($response['message']) : 'OK';
    }

    /** Delete a food entry from TK (requires explicit confirmation too). */
    public function deleteFood($id)
    {
        $url = $this->baseUrl() . '/user/diary/foodstuff/delete/' . rawurlencode((string)$id) . '?format=json';
        $response = $this->request($url, 'GET', null, true);
        $this->envelope($response, 'food delete');
        return isset($response['message']) ? $this->sanitize($response['message']) : 'OK';
    }

    /** Optional Nightscout treatment push. Disabled unless explicitly enabled. */
    public function pushNightscout($createdAt, $carbs, $notes)
    {
        $nsUrl = isset($this->config['NS_URL']) ? trim($this->config['NS_URL']) : '';
        $secret = isset($this->config['NS_API_SECRET']) ? (string)$this->config['NS_API_SECRET'] : '';
        if ($nsUrl === '' || $secret === '') {
            throw new Exception('Nightscout URL/secret not configured');
        }
        $url = rtrim($nsUrl, '/') . '/api/v1/treatments.json';
        $ch = curl_init();
        curl_setopt_array($ch, array(
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode(array(array(
                'eventType' => 'Meal Bolus',
                'created_at' => $createdAt,
                'carbs' => (float)$carbs,
                'enteredBy' => 'MajorDoMo-TabCalorie',
                'notes' => mb_substr((string)$notes, 0, 255),
            )), JSON_UNESCAPED_UNICODE),
            CURLOPT_HTTPHEADER => array(
                'Content-Type: application/json',
                'API-SECRET: ' . sha1(strtolower($secret)),
                'User-Agent: ' . self::UA,
            ),
            CURLOPT_CONNECTTIMEOUT => 5,
            CURLOPT_TIMEOUT => 15,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_SSL_VERIFYHOST => 2,
        ));
        $body = curl_exec($ch);
        $code = (int)curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);
        if ($err || $code < 200 || $code >= 300) {
            $this->lastError = 'Nightscout HTTP ' . $code . ($err ? ' (' . $this->sanitize($err) . ')' : '');
            throw new Exception($this->lastError);
        }
        return true;
    }

    /**
     * Connection diagnostics. Does not modify any data.
     * @return array list of array(step, ok, message)
     */
    public function diagnose()
    {
        if ($this->isDemo()) {
            return array(array('step' => 'demo', 'ok' => true,
                'message' => 'offline demo data, no network requests'));
        }
        $steps = array();
        $url = $this->baseUrl();
        $parts = parse_url($url);
        $steps[] = array('step' => 'base_url', 'ok' => (bool)$parts && in_array($parts['scheme'], array('https', 'http'), true),
            'message' => $url);
        if ($parts && isset($parts['host'])) {
            $host = $parts['host'];
            if (filter_var($host, FILTER_VALIDATE_IP)) {
                $steps[] = array('step' => 'dns', 'ok' => true, 'message' => $host . ' (IP literal)');
            } else {
                $ip = gethostbyname($host);
                $steps[] = array('step' => 'dns', 'ok' => ($ip !== $host), 'message' => $ip);
            }
        }
        $email = isset($this->config['EMAIL']) ? trim($this->config['EMAIL']) : '';
        $password = isset($this->config['PASSWORD']) ? (string)$this->config['PASSWORD'] : '';
        $steps[] = array('step' => 'credentials', 'ok' => ($email !== '' && $password !== ''),
            'message' => $email !== '' ? '***@' . substr($email, strpos($email, '@') + 1) : 'missing');
        if ($email === '' || $password === '') {
            return $steps;
        }
        try {
            $this->login();
            $steps[] = array('step' => 'login', 'ok' => true, 'message' => 'OK (' . count($this->cookies) . ' cookies)');
        } catch (Exception $e) {
            $steps[] = array('step' => 'login', 'ok' => false, 'message' => $this->getLastError());
            return $steps;
        }
        try {
            $diary = $this->getDiary(date('Y-m-d'));
            $slots = isset($diary['times']) && is_array($diary['times']) ? count($diary['times']) : 0;
            $steps[] = array('step' => 'diary', 'ok' => true, 'message' => 'OK, ' . $slots . ' meal slots');
        } catch (Exception $e) {
            $steps[] = array('step' => 'diary', 'ok' => false, 'message' => $this->getLastError());
        }
        return $steps;
    }
}
