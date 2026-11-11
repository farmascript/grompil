<?php
/**
 *
 * @date_cre: Sun 2026-10-18 20:35:47
 * @date_mut: Wed 2026-11-11
 *
 * @credit_software: Lieven Maus
 * @credit_code: chatGPT, codex
 *
 * @copyright: gromPil SaaS - copyright 1981-present - Lieven Maus
 * @info_email: info@grompil.com
 *
 * @description: Provides official address suggestions to forms through the address service.
 *
 * https://dw.grompil.com/!saas/doku.php?id=broncode:src:api:address_api
 */

declare(strict_types=1);

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
require_once dirname(__DIR__, 2) . '/includes/session.php';

/**
 * Return a JSON error response from the address lookup endpoint.
 *
 * @param int $status The HTTP status code.
 * @param string $message The error message returned in the JSON response.
 * @return never
 */
function address_api_error(int $status, string $message): never
{
    http_response_code($status);
    echo json_encode(['error' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Send an HTTP request to the address lookup service.
 *
 * @param string $url The request URL.
 * @param array $headers The HTTP request headers.
 * @return array
 */
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

grompil_start_session();
$type = (string) ($_GET['type'] ?? '');
$query = trim((string) ($_GET['q'] ?? ''));
$postCode = trim((string) ($_GET['postal_code'] ?? ''));
$city = trim((string) ($_GET['city'] ?? ''));
$nisCode = trim((string) ($_GET['nis_code'] ?? ''));
$streetId = trim((string) ($_GET['street_id'] ?? ''));
$street = trim((string) ($_GET['street'] ?? ''));
$houseNumber = trim((string) ($_GET['house_number'] ?? ''));
if (strlen($query) > 100 || strlen($postCode) > 20 || strlen($city) > 100 || strlen($nisCode) > 10
    || ($nisCode !== '' && !ctype_digit($nisCode)) || strlen($street) > 160 || strlen($houseNumber) > 32) {
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
    case 'locality':
        if (strlen($query) < 2) address_api_error(400, 'Enter at least two characters.');
        // Flanders publishes named postal areas (including districts such as Ekeren).
        // Search those first so selecting a place can immediately provide its postcode.
        $path = '/postalInfos';
        $parameters['name'] = '*' . $query . '*';
        $parameters['pageSize'] = 100;
        break;
    case 'municipality':
        if (strlen($query) < 2) address_api_error(400, 'Enter at least two characters.');
        $path = '/municipalities';
        $parameters['name'] = '*' . $query . '*';
        break;
    case 'street':
        if (strlen($query) < 2) address_api_error(400, 'Enter at least two characters.');
        $path = '/streets';
        $parameters['name'] = '*' . $query . '*';
        $parameters['embed'] = 'municipalities';
        $parameters['pageSize'] = $postCode === '' && $nisCode === '' ? 100 : 20;
        if ($postCode !== '') $parameters['postCode'] = $postCode;
        elseif ($nisCode !== '') $parameters['nisCode'] = $nisCode;
        break;
    case 'address':
        if ($houseNumber === '' || ($postCode === '' && $streetId === '' && ($city === '' || $street === ''))) address_api_error(400, 'Enter a postal code or municipality and street first.');
        $path = '/addresses';
        $parameters['houseNumber'] = $houseNumber;
        $parameters['embed'] = 'true';
        $parameters['firstOfBuilding'] = 'true';
        if ($postCode !== '') $parameters['postCode'] = $postCode;
        elseif ($nisCode !== '') $parameters['nisCode'] = $nisCode;
        if ($streetId !== '') $parameters['streetId'] = $streetId;
        elseif ($street !== '') $parameters['streetName'] = $street;
        break;
    default:
        address_api_error(400, 'Invalid search type.');
}

$url = $baseUrl . $path . '?' . http_build_query($parameters);
$traceId = bin2hex(random_bytes(16));
$data = address_api_http($url, ['Accept: application/json', 'BelGov-Trace-Id: ' . $traceId]);
if ($type === 'locality' && empty($data['items'])) {
    // Postal-area names are only supplied for Flanders. Fall back to official
    // municipality names elsewhere, where the user can continue with streets.
    $fallbackUrl = $baseUrl . '/municipalities?' . http_build_query([
        'status' => 'current', 'pageSize' => 10, 'name' => '*' . $query . '*',
    ]);
    $data = address_api_http($fallbackUrl, ['Accept: application/json', 'BelGov-Trace-Id: ' . $traceId]);
}
$items = [];
$language = strtolower((string) ($_SESSION['language'] ?? 'NL'));
$name = static function (mixed $value) use ($language): string {
    if (!is_array($value)) return '';
    $selected = $value[$language] ?? $value['nl'] ?? $value['fr'] ?? $value['en'] ?? '';
    return (string) ($selected !== '' ? $selected : (reset($value) ?: ''));
};
foreach (array_slice($data['items'] ?? [], 0, $type === 'locality' ? 50 : 10) as $item) {
    if (!is_array($item)) continue;
    $postalCode = (string) ($item['postCode'] ?? $item['postalCode'] ?? '');
    if ($type === 'postal') {
        $items[] = ['id' => (string) ($item['id'] ?? ''), 'label' => trim($postalCode . ' ' . $name($item['name'] ?? null)), 'postal_code' => $postalCode, 'city' => $name($item['name'] ?? null)];
    } elseif ($type === 'municipality' || ($type === 'locality' && $postalCode === '')) {
        $items[] = [
            'id' => (string) ($item['id'] ?? ''),
            'label' => $name($item['name'] ?? null),
            'city' => $name($item['name'] ?? null),
            'postal_code' => $postalCode,
            'nis_code' => (string) ($item['nisCode'] ?? ''),
        ];
    } elseif ($type === 'locality') {
        $localityName = $name($item['name'] ?? null);
        $items[] = [
            'id' => (string) ($item['id'] ?? ''),
            'label' => trim($postalCode . ' — ' . $localityName),
            'postal_code' => $postalCode,
            'city' => $localityName,
        ];
    } elseif ($type === 'street') {
        $streetName = $name($item['name'] ?? null);
        $assignedBy = $item['isAssignedBy'] ?? $item['isAssignedByMunicipality'] ?? [];
        $assignedMunicipality = $name($assignedBy['name'] ?? null);
        $items[] = [
            'id' => (string) ($item['id'] ?? ''),
            'label' => $streetName . ($assignedMunicipality !== '' ? ' — ' . $assignedMunicipality : ''),
            'street' => $streetName,
            'city' => $assignedMunicipality,
        ];
    } else {
        $streetData = $item['hasStreetName'] ?? [];
        $municipalityData = $item['hasMunicipality'] ?? [];
        $postalData = $item['hasPostalInfo'] ?? [];
        $embedded = $item['embedded'] ?? [];
        if (is_array($embedded) && is_array($postalData)) {
            $postalHref = (string) ($postalData['href'] ?? '');
            if ($postalHref !== '' && is_array($embedded[$postalHref] ?? null)) {
                $postalData = $embedded[$postalHref];
            } elseif (isset($postalData['id'])) {
                foreach ($embedded as $embeddedEntity) {
                    if (is_array($embeddedEntity) && (string) ($embeddedEntity['id'] ?? '') === (string) $postalData['id']) {
                        $postalData = $embeddedEntity;
                        break;
                    }
                }
            }
        }
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

