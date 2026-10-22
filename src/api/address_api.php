<?php
/**
 *
 * @date_cre: Sun 2026-10-18 20:35:47
 * @date_mut: Wed 2026-10-21 09:10:00
 *
 * @credit_software: Lieven Maus
 * @credit_code: chatGPT, codex
 *
 * @copyright: gromPil SaaS - copyright 1981-present - Lieven Maus
 * @info_email: info@grompil.com
 *
 * @description: Provides official address suggestions to forms through the address service.
 *
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');

function address_api_error(int $status, string $message): never
{
    http_response_code($status);
    echo json_encode(['error' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

function address_api_http(string $url, array $headers): array
{
    if (!function_exists('curl_init')) {
        address_api_error(503, 'The PHP cURL extension is not enabled.');
    }
    $handle = curl_init($url);
    curl_setopt_array($handle, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 5,
        CURLOPT_TIMEOUT => 12,
        CURLOPT_HTTPHEADER => $headers,
    ]);
    $body = curl_exec($handle);
    $status = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
    $curlError = curl_error($handle);
    if (!is_string($body)) {
        error_log('BeSt Address request failed at transport level: ' . $curlError);
        address_api_error(502, 'The PHP server could not reach the BeSt address service. Check outbound HTTPS access and the PHP/cURL CA certificate bundle.');
    }
    if ($status === 429) {
        address_api_error(429, 'The address service is temporarily limiting requests. Wait a moment and try again.');
    }
    if ($status < 200 || $status >= 300) {
        error_log('BeSt Address returned HTTP ' . $status . '.');
        address_api_error(502, $status === 400
            ? 'The address service rejected this search. Select a valid postal code or municipality first, then select a street.'
            : 'The official address service returned an error (HTTP ' . $status . ').');
    }
    $json = json_decode($body, true);
    if (!is_array($json)) {
        address_api_error(502, 'The address service returned an invalid response.');
    }
    return $json;
}

ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
ini_set('session.cookie_httponly', '1');
ini_set('session.cookie_samesite', 'Lax');
$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
$sessionLifetime = 60 * 60 * 24 * 30;
ini_set('session.gc_maxlifetime', (string) $sessionLifetime);
session_set_cookie_params(['lifetime' => $sessionLifetime, 'path' => '/', 'secure' => $https, 'httponly' => true, 'samesite' => 'Lax']);
session_name('grompil_session');
session_start();
$type = (string) ($_GET['type'] ?? '');
$query = trim((string) ($_GET['q'] ?? ''));
$postCode = trim((string) ($_GET['postal_code'] ?? ''));
$city = trim((string) ($_GET['city'] ?? ''));
$streetId = trim((string) ($_GET['street_id'] ?? ''));
$street = trim((string) ($_GET['street'] ?? ''));
$houseNumber = trim((string) ($_GET['house_number'] ?? ''));
if (strlen($query) > 100 || strlen($postCode) > 20 || strlen($city) > 100 || strlen($street) > 160 || strlen($houseNumber) > 32) {
    address_api_error(400, 'The search term is too long.');
}

$baseUrl = rtrim((string) (getenv('BEST_ADDRESS_API_URL') ?: 'https://best.pr.fedservices.be/api/opendata/best/v1/belgianAddress/v2'), '/');
$parameters = ['status' => 'current', 'pageSize' => 10];
switch ($type) {
    case 'postal':
        if (strlen($query) < 2) address_api_error(400, 'Enter at least two characters.');
        $path = '/postalInfos';
        $parameters['postCode'] = ctype_digit($query) && strlen($query) < 4 ? $query . '*' : $query;
        break;
    case 'municipality':
        if (strlen($query) < 2) address_api_error(400, 'Enter at least two characters.');
        $path = '/municipalities';
        $parameters['name'] = '*' . $query . '*';
        break;
    case 'street':
        if (strlen($query) < 2 || ($postCode === '' && $city === '')) address_api_error(400, 'Select a postal code or municipality first.');
        $path = '/streets';
        $parameters['name'] = '*' . $query . '*';
        if ($postCode !== '') $parameters['postCode'] = $postCode;
        if ($city !== '') $parameters['municipalityName'] = $city;
        break;
    case 'address':
        if ($houseNumber === '' || ($postCode === '' && ($city === '' || $street === ''))) address_api_error(400, 'Enter a postal code or municipality and street first.');
        $path = '/addresses';
        $parameters['houseNumber'] = $houseNumber;
        $parameters['embed'] = 'true';
        if ($postCode !== '') $parameters['postCode'] = $postCode;
        if ($city !== '') $parameters['municipalityName'] = $city;
        if ($streetId !== '') $parameters['streetId'] = $streetId;
        elseif ($street !== '') $parameters['streetName'] = $street;
        break;
    default:
        address_api_error(400, 'Invalid search type.');
}

$url = $baseUrl . $path . '?' . http_build_query($parameters);
$traceId = bin2hex(random_bytes(16));
$data = address_api_http($url, ['Accept: application/json', 'BelGov-Trace-Id: ' . $traceId]);
$items = [];
$language = strtolower((string) ($_SESSION['language'] ?? 'NL'));
$name = static function (mixed $value) use ($language): string {
    if (!is_array($value)) return '';
    $selected = $value[$language] ?? $value['nl'] ?? $value['fr'] ?? $value['en'] ?? '';
    return (string) ($selected !== '' ? $selected : (reset($value) ?: ''));
};
foreach (array_slice($data['items'] ?? [], 0, 10) as $item) {
    if (!is_array($item)) continue;
    if ($type === 'postal') {
        $items[] = ['id' => (string) ($item['id'] ?? ''), 'label' => trim((string) ($item['postCode'] ?? '') . ' ' . $name($item['name'] ?? null)), 'postal_code' => (string) ($item['postCode'] ?? ''), 'city' => $name($item['name'] ?? null)];
    } elseif ($type === 'municipality') {
        $items[] = ['id' => (string) ($item['id'] ?? ''), 'label' => $name($item['name'] ?? null), 'city' => $name($item['name'] ?? null)];
    } elseif ($type === 'street') {
        $items[] = ['id' => (string) ($item['id'] ?? ''), 'label' => $name($item['name'] ?? null), 'street' => $name($item['name'] ?? null)];
    } else {
        $streetData = $item['hasStreetName'] ?? [];
        $municipalityData = $item['hasMunicipality'] ?? [];
        $postalData = $item['hasPostalInfo'] ?? [];
        $items[] = [
            'id' => (string) ($item['id'] ?? ''),
            'label' => trim($name($streetData['name'] ?? null) . ' ' . (string) ($item['houseNumber'] ?? '') . (!empty($item['boxNumber']) ? ' bus ' . $item['boxNumber'] : '') . ', ' . (string) ($postalData['postCode'] ?? '') . ' ' . $name($municipalityData['name'] ?? null)),
            'street' => $name($streetData['name'] ?? null),
            'house_number' => (string) ($item['houseNumber'] ?? ''),
            'box_number' => (string) ($item['boxNumber'] ?? ''),
            'postal_code' => (string) ($postalData['postCode'] ?? ''),
            'city' => $name($municipalityData['name'] ?? null),
        ];
    }
}
echo json_encode(['items' => $items], JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);

