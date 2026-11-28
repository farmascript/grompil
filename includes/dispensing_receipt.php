<?php
/**
 * @date_cre: Fri 2026-11-20 12:50:11
 * @date_mut: Fri 2026-11-27
 *
 * @credit_software: Lieven Maus
 * @credit_code: ChatGPT, Codex
 *
 * @copyright: gromPil SaaS - copyright 1981-present - Lieven Maus
 * @info_email: info@grompil.com
 *
 * @description: Manages dispensing receipt.
 *
 * https://dw.grompil.com/!saas/doku.php?id=broncode:includes:dispensing_receipt
 */
declare(strict_types=1);

/**
 * Creates a small, dependency-free PDF from the fixed dispensing receipt layout.
 *
 * @param array $receipt The receipt.
 * @param string $language Language code.
 *
 * @return string The resulting text.
 */
function grompil_dispensing_receipt_pdf(array $receipt, string $language): string
{
    $layoutPath = GROMPIL_ROOT . '/tmpl/receipts/dispensing/default.json';
    $layout = is_file($layoutPath) ? json_decode((string) file_get_contents($layoutPath), true) : null;
    if (!is_array($layout)) {
        throw new RuntimeException('Dispensing receipt layout is unavailable.');
    }
    $pageWidth = (float) ($layout['page_width'] ?? 595);
    $pageHeight = (float) ($layout['page_height'] ?? 842);
    $margin = (float) ($layout['margin'] ?? 42);
    $lineHeight = (float) ($layout['line_height'] ?? 15);
    $maxChars = (int) ($layout['wrap_characters'] ?? 82);
    $text = [
        'receipt' => fn_tForLanguage('dispensing_receipt_pdf_title', $language),
        'date' => fn_tForLanguage('dispensing_receipt_date_label', $language),
        'session' => fn_tForLanguage('dispensing_receipt_session_label', $language),
        'prescription_number' => fn_tForLanguage('dispensing_receipt_prescription_number', $language),
        'subtotal' => fn_tForLanguage('dispensing_receipt_group_subtotal', $language),
        'total' => fn_tForLanguage('dispensing_receipt_total_products', $language),
        'price' => fn_tForLanguage('dispensing_receipt_amount_due', $language),
        'price_na' => fn_tForLanguage('dispensing_receipt_price_unavailable', $language),
        'enterprise' => fn_tForLanguage('dispensing_receipt_enterprise_number', $language),
        'vat' => fn_tForLanguage('dispensing_receipt_vat_number', $language),
    ];
    $items = [];
    $add = static function (string $value, int $size = 10, bool $bold = false) use (&$items, $maxChars): void {
        $value = trim($value);
        if ($value === '') return;
        $parts = preg_split('/\R/u', $value) ?: [$value];
        foreach ($parts as $part) {
            if (mb_strlen($part, 'UTF-8') <= $maxChars) {
                $items[] = [$part, $size, $bold];
                continue;
            }
            $words = preg_split('/\s+/u', $part) ?: [$part];
            $line = '';
            foreach ($words as $word) {
                $candidate = $line === '' ? $word : $line . ' ' . $word;
                if ($line !== '' && mb_strlen($candidate, 'UTF-8') > $maxChars) {
                    $items[] = [$line, $size, $bold];
                    $line = $word;
                } else $line = $candidate;
            }
            if ($line !== '') $items[] = [$line, $size, $bold];
        }
    };
    $add((string) ($receipt['company_name'] ?? ''), (int) ($layout['company_font_size'] ?? 17), true);
    foreach (['legal_name', 'address'] as $field) $add((string) ($receipt[$field] ?? ''), 9);
    if (($receipt['enterprise_number'] ?? '') !== '') $add($text['enterprise'] . ': ' . $receipt['enterprise_number'], 9);
    if (($receipt['vat_number'] ?? '') !== '') $add($text['vat'] . ': ' . $receipt['vat_number'], 9);
    $add($text['receipt'], 13, true);
    $add($text['date'] . ': ' . (string) ($receipt['date'] ?? ''), 10);
    $add($text['session'] . ': ' . (string) ($receipt['session_id'] ?? ''), 9);
    if (($receipt['prescription_number'] ?? null) !== null) {
        $add($text['prescription_number'] . ': #' . (string) $receipt['prescription_number'], 10, true);
    }
    $items[] = ['', 7, false];
    $grandTotal = 0;
    foreach (($receipt['groups'] ?? []) as $group) {
        $groupName = ($group['type'] ?? '') === 'anonymous' ? 'NN' : (string) ($group['name'] ?? '');
        $add($groupName, 11, true);
        foreach (($group['lines'] ?? []) as $line) {
            $quantity = (int) ($line['quantity'] ?? 0);
            $grandTotal += $quantity;
            $productName = strtoupper($language) === 'FR'
                ? ((string) ($line['name_fr'] ?? '') ?: (string) ($line['name_nl'] ?? ''))
                : ((string) ($line['name_nl'] ?? '') ?: (string) ($line['name_fr'] ?? ''));
            $package = strtoupper($language) === 'FR'
                ? ((string) ($line['package_fr'] ?? '') ?: (string) ($line['package_nl'] ?? ''))
                : ((string) ($line['package_nl'] ?? '') ?: (string) ($line['package_fr'] ?? ''));
            $productName = trim(preg_replace('/[\s\p{Z}]+/u', ' ', $productName) ?? $productName);
            $package = trim(preg_replace('/[\s\p{Z}]+/u', ' ', $package) ?? $package);
            $productDescription = trim($productName . ' ' . $package);
            $items[] = [
                'segments' => [
                    ['text' => (string) ($line['statusShortCode'] ?? '')],
                    ['text' => ' · ' . (string) ($line['cnk'] ?? '')],
                    ['text' => ' · ' . $quantity . ' · '],
                    ['text' => $productDescription, 'bold' => true],
                ],
                'size' => 8,
            ];
        }
        $groupCount = array_sum(array_map(static fn(array $line): int => (int) ($line['quantity'] ?? 0), $group['lines'] ?? []));
        $add(str_replace('{count}', (string) $groupCount, $text['subtotal']), 9, true);
        $items[] = ['', 5, false];
    }
    $items[] = ['----------------------------------------------------------------', 9, false];
    $add(str_replace('{count}', (string) $grandTotal, $text['total']), 11, true);
    $add($text['price'] . ': ' . $text['price_na'], 10, true);
    $items[] = ['', 12, false];
    $add((string) ($receipt['thank_you'] ?? ''), 10, false);

    $pages = [];
    $pageCommands = [];
    $y = $pageHeight - $margin;
    foreach ($items as $item) {
        if (isset($item['segments'])) {
            $segments = $item['segments'];
            $lineText = implode('', array_map(static fn(array $segment): string => (string) $segment['text'], $segments));
            $lineLength = max(1, mb_strlen($lineText, 'UTF-8'));
            $size = min((float) $item['size'], max(5.5, (($pageWidth - (2 * $margin)) / ($lineLength * 0.52))));
            if ($y < $margin) {
                $pages[] = implode("\n", $pageCommands);
                $pageCommands = [];
                $y = $pageHeight - $margin;
            }
            $x = $margin;
            foreach ($segments as $segment) {
                $encoded = iconv('UTF-8', 'Windows-1252//TRANSLIT', (string) $segment['text']);
                if ($encoded === false) $encoded = preg_replace('/[^\x20-\x7E]/', '?', (string) $segment['text']) ?? '';
                $encoded = str_replace(['\\', '(', ')', "\r", "\n"], ['\\\\', '\\(', '\\)', '', ''], $encoded);
                $font = !empty($segment['bold']) ? 'F2' : 'F1';
                $pageCommands[] = sprintf('BT /%s %.2f Tf 1 0 0 1 %.2f %.2f Tm (%s) Tj ET', $font, $size, $x, $y, $encoded);
                $x += strlen($encoded) * $size * 0.52;
            }
            $y -= max($lineHeight, $size + 4);
            continue;
        }
        [$value, $size, $bold] = $item;
        if ($y < $margin) {
            $pages[] = implode("\n", $pageCommands);
            $pageCommands = [];
            $y = $pageHeight - $margin;
        }
        if ($value === '') { $y -= $lineHeight; continue; }
        $encoded = iconv('UTF-8', 'Windows-1252//TRANSLIT', (string) $value);
        if ($encoded === false) $encoded = preg_replace('/[^\x20-\x7E]/', '?', (string) $value) ?? '';
        $encoded = str_replace(['\\', '(', ')', "\r", "\n"], ['\\\\', '\\(', '\\)', '', ''], $encoded);
        $font = $bold ? 'F2' : 'F1';
        $pageCommands[] = sprintf('BT /%s %d Tf 1 0 0 1 %.2f %.2f Tm (%s) Tj ET', $font, $size, $margin, $y, $encoded);
        $y -= max($lineHeight, $size + 4);
    }
    $pages[] = implode("\n", $pageCommands);

    $objects = [1 => '<< /Type /Catalog /Pages 2 0 R >>', 2 => ''];
    $pageObjectIds = [];
    $nextId = 3;
    $fontRegularId = 3 + (2 * count($pages));
    $fontBoldId = $fontRegularId + 1;
    foreach ($pages as $commands) {
        $contentId = $nextId++;
        $pageId = $nextId++;
        $objects[$contentId] = '<< /Length ' . strlen($commands) . " >>\nstream\n" . $commands . "\nendstream";
        $objects[$pageId] = '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 ' . $pageWidth . ' ' . $pageHeight
            . '] /Resources << /Font << /F1 ' . $fontRegularId . ' 0 R /F2 ' . $fontBoldId . ' 0 R >> >> /Contents '
            . $contentId . ' 0 R >>';
        $pageObjectIds[] = $pageId;
    }
    $nextId++;
    $nextId++;
    $objects[$fontRegularId] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica /Encoding /WinAnsiEncoding >>';
    $objects[$fontBoldId] = '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold /Encoding /WinAnsiEncoding >>';
    $objects[2] = '<< /Type /Pages /Kids [' . implode(' ', array_map(static fn(int $id): string => $id . ' 0 R', $pageObjectIds))
        . '] /Count ' . count($pageObjectIds) . ' >>';
    ksort($objects);
    $pdf = "%PDF-1.4\n%\xE2\xE3\xCF\xD3\n";
    $offsets = [0];
    foreach ($objects as $id => $object) {
        $offsets[$id] = strlen($pdf);
        $pdf .= $id . " 0 obj\n" . $object . "\nendobj\n";
    }
    $xrefOffset = strlen($pdf);
    $pdf .= "xref\n0 " . (count($objects) + 1) . "\n0000000000 65535 f \n";
    for ($id = 1; $id <= count($objects); $id++) $pdf .= sprintf('%010d 00000 n ', $offsets[$id]) . "\n";
    $pdf .= 'trailer << /Size ' . (count($objects) + 1) . " /Root 1 0 R >>\nstartxref\n" . $xrefOffset . "\n%%EOF";
    return $pdf;
}
