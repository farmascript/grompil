<?php
/**
 *
 * @date_cre: Sun 2026-10-18 20:35:47
 * @date_mut: Sun 2026-10-25 21:36:11
 *
 * @credit_software: Lieven Maus
 * @credit_code: chatGPT, codex
 *
 * @copyright: gromPil SaaS - copyright 1981-present - Lieven Maus
 * @info_email: info@grompil.com
 *
 * @description: Connects to the Belgian company register service and handles lookups.
 *
 */

declare(strict_types=1);

require_once __DIR__ . '/credentials_config.php';

/** Read KBO webservice credentials from the selected private credentials file. */
function grompil_kbo_config(): array
{
    $credentials = grompil_credentials_config();
    return is_array($credentials['kbo'] ?? null)
        ? $credentials['kbo']
        : [];
}

/** Return KBO requests made in the rolling seven-day quota period. */
function grompil_kbo_weekly_usage(): int
{
    $result = pg_query(
        db(),
        "SELECT count(*) AS request_count
         FROM kbo_lookup_attempts
         WHERE requested_at >= now() - interval '7 days'"
    );
    $row = $result ? pg_fetch_assoc($result) : false;
    if (!$row) {
        throw new RuntimeException('Unable to read KBO usage from the database.');
    }
    return (int) $row['request_count'];
}

/** Reserve and log one request atomically so concurrent clicks cannot exceed the weekly limit. */
function grompil_kbo_reserve_request(string $term, string $requestType = 'read_enterprise'): void
{
    grompil_kbo_reserve_requests($term, [$requestType]);
}

/** Reserve a group of related requests as one atomic operation. */
function grompil_kbo_reserve_requests(string $term, array $requestTypes): void
{
    if ($requestTypes === []) {
        return;
    }
    $connection = db();
    if (!pg_query($connection, 'BEGIN')) {
        throw new RuntimeException('Unable to record KBO usage.');
    }
    try {
        if (!pg_query($connection, 'SELECT pg_advisory_xact_lock(812710, 1)')) {
            throw new RuntimeException('Unable to record KBO usage.');
        }
        $countResult = pg_query(
            $connection,
            "SELECT count(*) AS request_count
             FROM kbo_lookup_attempts
             WHERE requested_at >= now() - interval '7 days'"
        );
        $count = $countResult ? pg_fetch_result($countResult, 0, 'request_count') : false;
        if ($count === false) {
            throw new RuntimeException('Unable to check KBO usage.');
        }
        if ((int) $count + count($requestTypes) > 100) {
            throw new RuntimeException('kbo_weekly_limit_reached');
        }
        $userId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
        foreach ($requestTypes as $requestType) {
            $inserted = pg_query_params(
                $connection,
                'INSERT INTO kbo_lookup_attempts (enterprise_number, request_type, requested_by)
                 VALUES ($1, $2, $3)',
                [$requestType === 'read_enterprise' ? $term : '', $requestType, $userId]
            );
            if (!$inserted) {
                throw new RuntimeException('Unable to record KBO usage.');
            }
        }
        if (!pg_query($connection, 'COMMIT')) {
            throw new RuntimeException('Unable to record KBO usage.');
        }
    } catch (Throwable $exception) {
        @pg_query($connection, 'ROLLBACK');
        throw $exception;
    }
}

/** Fetch public enterprise details from the official CBE/KBO Public Search service. */
function grompil_kbo_lookup(string $number): array
{
    $number = preg_replace('/\D+/', '', $number) ?? '';
    if (!preg_match('/^(?:0|1)?\d{9}$/', $number)) {
        throw new RuntimeException('The enterprise number has an invalid format.');
    }
    $config = grompil_kbo_config();
    $username = trim((string) ($config['username'] ?? ''));
    $password = (string) ($config['password'] ?? '');
    if ($username === '' || $password === '') {
        throw new RuntimeException('kbo_not_configured');
    }
    grompil_kbo_reserve_request($number);

    $created = gmdate('Y-m-d\TH:i:s\Z');
    $nonce = random_bytes(16);
    $nonce64 = base64_encode($nonce);
    $digest = base64_encode(sha1($nonce . $created . $password, true));
    $requestId = bin2hex(random_bytes(16));
    $xml = '<?xml version="1.0" encoding="UTF-8"?>'
        . '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/"'
        . ' xmlns:mes="http://economie.fgov.be/kbopub/webservices/v1/messages"'
        . ' xmlns:dat="http://economie.fgov.be/kbopub/webservices/v1/datamodel"'
        . ' xmlns:wsse="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd"'
        . ' xmlns:wsu="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd">'
        . '<soapenv:Header><wsse:Security><wsu:Timestamp><wsu:Created>' . $created
        . '</wsu:Created><wsu:Expires>' . gmdate('Y-m-d\TH:i:s\Z', time() + 300)
        . '</wsu:Expires></wsu:Timestamp><wsse:UsernameToken><wsse:Username>' . htmlspecialchars($username, ENT_XML1)
        . '</wsse:Username><wsse:Password Type="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-username-token-profile-1.0#PasswordDigest">'
        . $digest . '</wsse:Password><wsse:Nonce EncodingType="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-soap-message-security-1.0#Base64Binary">'
        . $nonce64 . '</wsse:Nonce><wsu:Created>' . $created . '</wsu:Created></wsse:UsernameToken></wsse:Security>'
        . '<mes:RequestContext><mes:Id>' . $requestId . '</mes:Id><mes:Language>nl</mes:Language></mes:RequestContext></soapenv:Header>'
        . '<soapenv:Body><mes:ReadEnterpriseRequest><dat:EnterpriseNumber>' . $number
        . '</dat:EnterpriseNumber></mes:ReadEnterpriseRequest></soapenv:Body></soapenv:Envelope>';

    $curl = curl_init('https://kbopub.economie.fgov.be/kbopubws180000/services/wsKBOPub');
    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $xml,
        CURLOPT_HTTPHEADER => ['Content-Type: text/xml; charset=utf-8', 'Accept: text/xml'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 25,
    ]);
    $response = curl_exec($curl);
    $curlError = curl_error($curl);
    $curlErrorNumber = curl_errno($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    curl_close($curl);
    if (!is_string($response)) {
        $diagnostic = 'cURL ' . $curlErrorNumber . ': ' . ($curlError !== '' ? $curlError : 'request failed without an error message');
        error_log('Grompil KBO request failed: ' . $diagnostic);
        throw new RuntimeException('kbo_transport_error: ' . $diagnostic);
    }

    $document = new DOMDocument();
    if (!@$document->loadXML($response, LIBXML_NONET)) {
        error_log('Grompil KBO returned invalid XML; HTTP status ' . $status . '.');
        if ($status < 200 || $status >= 300) {
            throw new RuntimeException('kbo_http_error: HTTP ' . $status . ' returned a non-SOAP response');
        }
        throw new RuntimeException('The CBE service returned an invalid response.');
    }
    $xpath = new DOMXPath($document);
    $fault = $xpath->query('//*[local-name()="Fault"]')->item(0);
    if ($fault) {
        $faultText = trim((string) ($xpath->query('.//*[local-name()="faultstring" or local-name()="Text"]', $fault)->item(0)?->textContent ?? ''));
        $safeFaultText = substr(preg_replace('/\s+/', ' ', $faultText) ?? '', 0, 500);
        error_log('Grompil KBO SOAP fault; HTTP status ' . $status . ': ' . ($safeFaultText !== '' ? $safeFaultText : 'no fault text'));
        $normalizedFault = strtolower($faultText);
        if (str_contains($normalizedFault, 'terms of service') || str_contains($normalizedFault, 'accept the terms')) {
            throw new RuntimeException('kbo_terms_not_accepted');
        }
        if (str_contains($normalizedFault, 'quota exceeded') || str_contains($normalizedFault, 'balance is empty')) {
            throw new RuntimeException('kbo_service_quota_exceeded');
        }
        if (str_contains($normalizedFault, 'security token') || str_contains($normalizedFault, 'username') || str_contains($normalizedFault, 'password')) {
            throw new RuntimeException('kbo_authentication_failed');
        }
        throw new RuntimeException('kbo_service_rejected');
    }
    if ($status < 200 || $status >= 300) {
        error_log('Grompil KBO returned HTTP status ' . $status . ' without a SOAP fault.');
        throw new RuntimeException('kbo_http_error: HTTP ' . $status . ' without a SOAP fault');
    }
    $enterprise = $xpath->query('//*[local-name()="Enterprise"]')->item(0);
    if (!$enterprise) {
        throw new RuntimeException('No enterprise was found for this enterprise number.');
    }
    $value = static function (DOMXPath $xpath, DOMNode $context, string $path): string {
        return trim((string) ($xpath->query($path, $context)->item(0)?->textContent ?? ''));
    };
    $denomination = $xpath->query('./*[local-name()="Denomination"]/*[local-name()="Description"]/*[local-name()="Value"]', $enterprise)->item(0);
    $address = $xpath->query('./*[local-name()="Address"]', $enterprise)->item(0);
    $address ??= $xpath->query('.//*[local-name()="Address"]', $enterprise)->item(0);
    $contacts = $xpath->query('.//*[local-name()="ContactInformation"]', $enterprise);
    $contact = ['phone' => '', 'email' => ''];
    foreach ($contacts as $item) {
        $type = $value($xpath, $item, './*[local-name()="ContactType"]');
        $contactValue = $value($xpath, $item, './*[local-name()="Value"]');
        if ($type === '001' && $contact['phone'] === '') {
            $contact['phone'] = $contactValue;
        } elseif ($type === '004' && $contact['email'] === '') {
            $contact['email'] = $contactValue;
        }
    }
    $statusNode = $xpath->query('.//*[local-name()="JuridicalSituation"]/*[local-name()="Status"]/*[local-name()="Description"]/*[local-name()="Value"]', $enterprise)->item(0);

    return [
        'number' => $value($xpath, $enterprise, './*[local-name()="Number"]') ?: $number,
        'name' => trim((string) ($denomination?->textContent ?? '')),
        'status' => trim((string) ($statusNode?->textContent ?? '')),
        'street' => $address ? $value($xpath, $address, './*[local-name()="Street"]/*[local-name()="Description"]/*[local-name()="Value"]') : '',
        'house_number' => $address ? $value($xpath, $address, './*[local-name()="HouseNumber"]') : '',
        'postal_code' => $address ? $value($xpath, $address, './*[local-name()="Zipcode"]') : '',
        'city' => $address ? $value($xpath, $address, './*[local-name()="Municipality"]/*[local-name()="Description"]/*[local-name()="Value"]') : '',
        'phone' => $contact['phone'],
        'email' => $contact['email'],
    ];
}

/** Search all enterprise types by name phoneme through the official CBE/KBO Public Search service. */
function grompil_kbo_search_enterprises(string $query): array
{
    $query = trim(preg_replace('/\s+/u', ' ', $query) ?? '');
    $length = function_exists('mb_strlen') ? mb_strlen($query, 'UTF-8') : strlen($query);
    $phonemes = $query === '' ? [] : explode(' ', $query);
    if ($length < 2 || $length > 120 || count($phonemes) > 10) {
        throw new RuntimeException('kbo_search_query_invalid');
    }
    $config = grompil_kbo_config();
    $username = trim((string) ($config['username'] ?? ''));
    $password = (string) ($config['password'] ?? '');
    if ($username === '' || $password === '') {
        throw new RuntimeException('kbo_not_configured');
    }
    $created = gmdate('Y-m-d\TH:i:s\Z');
    $nonce = random_bytes(16);
    $nonce64 = base64_encode($nonce);
    $digest = base64_encode(sha1($nonce . $created . $password, true));
    $requestId = bin2hex(random_bytes(16));
    $safeUsername = htmlspecialchars($username, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    $phonemeXml = '';
    foreach ($phonemes as $phoneme) {
        $phonemeXml .= '<mes:Phoneme>' . htmlspecialchars($phoneme, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</mes:Phoneme>';
    }
    $xml = '<?xml version="1.0" encoding="UTF-8"?>'
        . '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/"'
        . ' xmlns:mes="http://economie.fgov.be/kbopub/webservices/v1/messages"'
        . ' xmlns:wsse="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd"'
        . ' xmlns:wsu="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd">'
        . '<soapenv:Header><wsse:Security><wsu:Timestamp><wsu:Created>' . $created
        . '</wsu:Created><wsu:Expires>' . gmdate('Y-m-d\TH:i:s\Z', time() + 300)
        . '</wsu:Expires></wsu:Timestamp><wsse:UsernameToken><wsse:Username>' . $safeUsername
        . '</wsse:Username><wsse:Password Type="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-username-token-profile-1.0#PasswordDigest">'
        . $digest . '</wsse:Password><wsse:Nonce EncodingType="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-soap-message-security-1.0#Base64Binary">'
        . $nonce64 . '</wsse:Nonce><wsu:Created>' . $created . '</wsu:Created></wsse:UsernameToken></wsse:Security>'
        . '<mes:RequestContext><mes:Id>' . $requestId . '</mes:Id><mes:Language>nl</mes:Language></mes:RequestContext></soapenv:Header>'
        . '<soapenv:Body><mes:ReadEnterpriseByPhonemeRequest>' . $phonemeXml
        . '<mes:TypeOfEnterprise>ELP</mes:TypeOfEnterprise><mes:TypeOfEnterprise>EPP</mes:TypeOfEnterprise>'
        . '<mes:ActiveFilter>true</mes:ActiveFilter></mes:ReadEnterpriseByPhonemeRequest></soapenv:Body></soapenv:Envelope>';

    $curl = curl_init('https://kbopub.economie.fgov.be/kbopubws180000/services/wsKBOPub');
    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $xml,
        CURLOPT_HTTPHEADER => ['Content-Type: text/xml; charset=utf-8', 'Accept: text/xml'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 25,
    ]);
    $response = curl_exec($curl);
    $curlError = curl_error($curl);
    $curlErrorNumber = curl_errno($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    curl_close($curl);
    if (!is_string($response)) {
        $diagnostic = 'cURL ' . $curlErrorNumber . ': ' . ($curlError !== '' ? $curlError : 'request failed without an error message');
        error_log('Grompil KBO search failed: ' . $diagnostic);
        throw new RuntimeException('kbo_transport_error: ' . $diagnostic);
    }

    $document = new DOMDocument();
    if (!@$document->loadXML($response, LIBXML_NONET)) {
        error_log('Grompil KBO search returned invalid XML; HTTP status ' . $status . '.');
        if ($status < 200 || $status >= 300) {
            throw new RuntimeException('kbo_http_error: HTTP ' . $status . ' returned a non-SOAP response');
        }
        throw new RuntimeException('The CBE service returned an invalid response.');
    }
    $xpath = new DOMXPath($document);
    $fault = $xpath->query('//*[local-name()="Fault"]')->item(0);
    if ($fault) {
        $faultText = trim((string) ($xpath->query('.//*[local-name()="faultstring" or local-name()="Text"]', $fault)->item(0)?->textContent ?? ''));
        $safeFaultText = substr(preg_replace('/\s+/', ' ', $faultText) ?? '', 0, 500);
        error_log('Grompil KBO search SOAP fault; HTTP status ' . $status . ': ' . ($safeFaultText !== '' ? $safeFaultText : 'no fault text'));
        $normalizedFault = strtolower($faultText);
        if (str_contains($normalizedFault, 'terms of service') || str_contains($normalizedFault, 'accept the terms')) {
            throw new RuntimeException('kbo_terms_not_accepted');
        }
        if (str_contains($normalizedFault, 'quota exceeded') || str_contains($normalizedFault, 'balance is empty')) {
            throw new RuntimeException('kbo_service_quota_exceeded');
        }
        if (str_contains($normalizedFault, 'security token') || str_contains($normalizedFault, 'username') || str_contains($normalizedFault, 'password')) {
            throw new RuntimeException('kbo_authentication_failed');
        }
        throw new RuntimeException('kbo_service_rejected');
    }
    if ($status < 200 || $status >= 300) {
        error_log('Grompil KBO search returned HTTP status ' . $status . ' without a SOAP fault.');
        throw new RuntimeException('kbo_http_error: HTTP ' . $status . ' without a SOAP fault');
    }

    $results = [];
    foreach ($xpath->query('//*[local-name()="ReadEnterpriseByPhonemeReply"]//*[local-name()="Enterprise"]') as $enterprise) {
        $read = static function (string $path) use ($xpath, $enterprise): string {
            return trim((string) ($xpath->query($path, $enterprise)->item(0)?->textContent ?? ''));
        };
        $name = $read('./*[local-name()="PrimaryDenomination"]/*[local-name()="Value"]');
        if ($name === '') {
            $name = $read('.//*[local-name()="PrimaryDenomination"]/*[local-name()="Value"]');
        }
        $address = $xpath->query('./*[local-name()="Address"]', $enterprise)->item(0);
        $results[] = [
            'number' => $read('./*[local-name()="Number"]'),
            'name' => $name,
            'status' => $read('.//*[local-name()="JuridicalSituation"]/*[local-name()="Status"]/*[local-name()="Description"]/*[local-name()="Value"]'),
            'street' => $address ? trim((string) ($xpath->query('.//*[local-name()="Street"]/*[local-name()="Description"]/*[local-name()="Value"]', $address)->item(0)?->textContent ?? '')) : '',
            'house_number' => $address ? trim((string) ($xpath->query('.//*[local-name()="HouseNumber"]', $address)->item(0)?->textContent ?? '')) : '',
            'postal_code' => $address ? trim((string) ($xpath->query('.//*[local-name()="Zipcode"]', $address)->item(0)?->textContent ?? '')) : '',
            'city' => $address ? trim((string) ($xpath->query('.//*[local-name()="Municipality"]/*[local-name()="Description"]/*[local-name()="Value"]', $address)->item(0)?->textContent ?? '')) : '',
        ];
    }
    return $results;
}

/** Search named establishment units and return their parent enterprise number. */
function grompil_kbo_search_establishments(string $query): array
{
    $query = trim(preg_replace('/\\s+/u', ' ', $query) ?? '');
    $length = function_exists('mb_strlen') ? mb_strlen($query, 'UTF-8') : strlen($query);
    $phonemes = $query === '' ? [] : explode(' ', $query);
    if ($length < 2 || $length > 120 || count($phonemes) > 10) {
        throw new RuntimeException('kbo_search_query_invalid');
    }
    $config = grompil_kbo_config();
    $username = trim((string) ($config['username'] ?? ''));
    $password = (string) ($config['password'] ?? '');
    if ($username === '' || $password === '') {
        throw new RuntimeException('kbo_not_configured');
    }
    $created = gmdate('Y-m-d\\TH:i:s\\Z');
    $nonce = random_bytes(16);
    $nonce64 = base64_encode($nonce);
    $digest = base64_encode(sha1($nonce . $created . $password, true));
    $requestId = bin2hex(random_bytes(16));
    $safeUsername = htmlspecialchars($username, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    $phonemeXml = '';
    foreach ($phonemes as $phoneme) {
        $phonemeXml .= '<mes:Phoneme>' . htmlspecialchars($phoneme, ENT_XML1 | ENT_QUOTES, 'UTF-8') . '</mes:Phoneme>';
    }
    $xml = '<?xml version="1.0" encoding="UTF-8"?>'
        . '<soapenv:Envelope xmlns:soapenv="http://schemas.xmlsoap.org/soap/envelope/"'
        . ' xmlns:mes="http://economie.fgov.be/kbopub/webservices/v1/messages"'
        . ' xmlns:wsse="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd"'
        . ' xmlns:wsu="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd">'
        . '<soapenv:Header><wsse:Security><wsu:Timestamp><wsu:Created>' . $created
        . '</wsu:Created><wsu:Expires>' . gmdate('Y-m-d\\TH:i:s\\Z', time() + 300)
        . '</wsu:Expires></wsu:Timestamp><wsse:UsernameToken><wsse:Username>' . $safeUsername
        . '</wsse:Username><wsse:Password Type="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-username-token-profile-1.0#PasswordDigest">'
        . $digest . '</wsse:Password><wsse:Nonce EncodingType="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-soap-message-security-1.0#Base64Binary">'
        . $nonce64 . '</wsse:Nonce><wsu:Created>' . $created . '</wsu:Created></wsse:UsernameToken></wsse:Security>'
        . '<mes:RequestContext><mes:Id>' . $requestId . '</mes:Id><mes:Language>nl</mes:Language></mes:RequestContext></soapenv:Header>'
        . '<soapenv:Body><mes:ReadEstablishmentByPhonemeRequest>' . $phonemeXml
        . '<mes:ActiveFilter>true</mes:ActiveFilter></mes:ReadEstablishmentByPhonemeRequest></soapenv:Body></soapenv:Envelope>';

    $curl = curl_init('https://kbopub.economie.fgov.be/kbopubws180000/services/wsKBOPub');
    curl_setopt_array($curl, [
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => $xml,
        CURLOPT_HTTPHEADER => ['Content-Type: text/xml; charset=utf-8', 'Accept: text/xml'],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT => 25,
    ]);
    $response = curl_exec($curl);
    $curlError = curl_error($curl);
    $curlErrorNumber = curl_errno($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    curl_close($curl);
    if (!is_string($response)) {
        $diagnostic = 'cURL ' . $curlErrorNumber . ': ' . ($curlError !== '' ? $curlError : 'request failed without an error message');
        error_log('Grompil KBO establishment search failed: ' . $diagnostic);
        throw new RuntimeException('kbo_transport_error: ' . $diagnostic);
    }

    $document = new DOMDocument();
    if (!@$document->loadXML($response, LIBXML_NONET)) {
        error_log('Grompil KBO establishment search returned invalid XML; HTTP status ' . $status . '.');
        if ($status < 200 || $status >= 300) {
            throw new RuntimeException('kbo_http_error: HTTP ' . $status . ' returned a non-SOAP response');
        }
        throw new RuntimeException('The CBE service returned an invalid response.');
    }
    $xpath = new DOMXPath($document);
    $fault = $xpath->query('//*[local-name()="Fault"]')->item(0);
    if ($fault) {
        $faultText = trim((string) ($xpath->query('.//*[local-name()="faultstring" or local-name()="Text"]', $fault)->item(0)?->textContent ?? ''));
        $safeFaultText = substr(preg_replace('/\\s+/', ' ', $faultText) ?? '', 0, 500);
        error_log('Grompil KBO establishment search SOAP fault; HTTP status ' . $status . ': ' . ($safeFaultText !== '' ? $safeFaultText : 'no fault text'));
        $normalizedFault = strtolower($faultText);
        if (str_contains($normalizedFault, 'terms of service') || str_contains($normalizedFault, 'accept the terms')) {
            throw new RuntimeException('kbo_terms_not_accepted');
        }
        if (str_contains($normalizedFault, 'quota exceeded') || str_contains($normalizedFault, 'balance is empty')) {
            throw new RuntimeException('kbo_service_quota_exceeded');
        }
        if (str_contains($normalizedFault, 'security token') || str_contains($normalizedFault, 'username') || str_contains($normalizedFault, 'password')) {
            throw new RuntimeException('kbo_authentication_failed');
        }
        throw new RuntimeException('kbo_service_rejected');
    }
    if ($status < 200 || $status >= 300) {
        error_log('Grompil KBO establishment search returned HTTP status ' . $status . ' without a SOAP fault.');
        throw new RuntimeException('kbo_http_error: HTTP ' . $status . ' without a SOAP fault');
    }

    $results = [];
    foreach ($xpath->query('//*[local-name()="ReadEstablishmentByPhonemeReply"]//*[local-name()="Establishment"]') as $establishment) {
        $read = static function (string $path) use ($xpath, $establishment): string {
            return trim((string) ($xpath->query($path, $establishment)->item(0)?->textContent ?? ''));
        };
        $address = $xpath->query('./*[local-name()="Address"]', $establishment)->item(0);
        $addressValue = static function (string $path) use ($xpath, $address): string {
            return $address ? trim((string) ($xpath->query($path, $address)->item(0)?->textContent ?? '')) : '';
        };
        $results[] = [
            'number' => $read('./*[local-name()="EnterpriseNumber"]'),
            'establishment_number' => $read('./*[local-name()="EstablishmentNumber"]'),
            'name' => $read('./*[local-name()="PrimaryDenomination"]/*[local-name()="Value"]'),
            'status' => $read('./*[local-name()="Status"]/*[local-name()="Description"]/*[local-name()="Value"]'),
            'street' => $addressValue('.//*[local-name()="Street"]/*[local-name()="Description"]/*[local-name()="Value"]'),
            'house_number' => $addressValue('.//*[local-name()="HouseNumber"]'),
            'postal_code' => $addressValue('.//*[local-name()="Zipcode"]'),
            'city' => $addressValue('.//*[local-name()="Municipality"]/*[local-name()="Description"]/*[local-name()="Value"]'),
        ];
    }
    return $results;
}

/** Search enterprises and establishment units; establishment results link to their parent enterprise. */
function grompil_kbo_search(string $query): array
{
    grompil_kbo_reserve_requests($query, [
        'search_enterprise_by_phoneme',
        'search_establishment_by_phoneme',
    ]);
    return array_merge(grompil_kbo_search_enterprises($query), grompil_kbo_search_establishments($query));
}

