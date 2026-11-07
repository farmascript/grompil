
<?php

/**
 *
 * @date_cre: Wed 2026-11-04
 * @date_mut: Sat 2026-11-07
 *
 * @credit_software: Lieven Maus
 * @credit_code: chatGPT, codex
 *
 * @copyright: gromPil SaaS - copyright 1981-present - Lieven Maus
 * @info_email: info@grompil.com
 *
 * @description: Converts integers to reversible six-character alphanumeric codes.
 *
 * https://dw.grompil.com/!saas/doku.php?id=broncode:includes:do_6_char_code
 *
 */

/**
 * Zet een uniek getal om naar een 6-karakter code (3 letters, 3 cijfers).
 */
function getalNaarCode(int $getal): string 
{
    $totaalCombinaties = 351520000;
    
    if ($getal < 0 || $getal >= $totaalCombinaties) {
        throw new InvalidArgumentException("Getal moet tussen 0 en " . ($totaalCombinaties - 1) . " liggen.");
    }

    $alfabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    
    // De 20 manieren om precies 3 letters (L) en 3 cijfers (C) te rangschikken.
    static $patronen = [
        'LLLCCC', 'LLCLCC', 'LLCCLC', 'LLCCCL', 'LCLLCC', 'LCLCLC', 'LCLCCL', 'LCCLLC', 'LCCLCL', 'LCCCLL',
        'CLLLCC', 'CLLCLC', 'CLLCCL', 'CLCLLC', 'CLCLCL', 'CLCCLL', 'CCLLLC', 'CCLLCL', 'CCLCLC', 'CCCLLL',
    ];


    // 1. Bepaal welk van de 20 patronen we gebruiken
    $patroonIndex = intdiv($getal, 17576000);
    $rest = $getal % 17576000;

    // 2. Splits de rest in een deel voor letters (0-17575) en cijfers (0-999)
    $letterGetal = intdiv($rest, 1000);
    $cijferGetal = $rest % 1000;

    // 3. Bereken de 3 letters (26-tallig stelsel)
    $l1 = $alfabet[intdiv($letterGetal, 676) % 26];
    $l2 = $alfabet[intdiv($letterGetal, 26) % 26];
    $l3 = $alfabet[$letterGetal % 26];
    $letters = [$l1, $l2, $l3];

    // 4. Bereken de 3 cijfers (met voorloopnullen)
    $cijferString = str_pad((string)$cijferGetal, 3, '0', STR_PAD_LEFT);
    $cijfers = str_split($cijferString);

    // 5. Weef de letters en cijfers samen volgens het gekozen patroon
    $patroon = $patronen[$patroonIndex];
    $code = '';
    $lCount = 0;
    $cCount = 0;

    for ($i = 0; $i < 6; $i++) {
        if ($patroon[$i] === 'L') {
            $code .= $letters[$lCount++];
        } else {
            $code .= $cijfers[$cCount++];
        }
    }

    return $code;
}

/**
 * Zet een 6-karakter code terug om naar het oorspronkelijke getal (inclusief automatische opschoning).
 */
function codeNaarGetal(string $code): int 
{
    // --- AUTOMATISCHE OPSCHONING ---
    // Zet om naar hoofdletters en verwijder alles wat GEEN letter of cijfer is (zoals spaties, streepjes, etc.)
    $code = strtoupper($code);
    $code = preg_replace('/[^A-Z0-9]/', '', $code);

    if (strlen($code) !== 6) {
        throw new InvalidArgumentException("Code moet (na opschonen) exact 6 karakters lang zijn.");
    }

    $alfabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    
    static $patronen = [
        'LLLCCC', 'LLCLCC', 'LLCCLC', 'LLCCCL', 'LCLLCC', 'LCLCLC', 'LCLCCL', 'LCCLLC', 'LCCLCL', 'LCCCLL',
        'CLLLCC', 'CLLCLC', 'CLLCCL', 'CLCLLC', 'CLCLCL', 'CLCCLL', 'CCLLLC', 'CCLLCL', 'CCLCLC', 'CCCLLL',
    ];


    // 1. Analyseer de opgeschoonde code om het patroon te herkennen (bijv. 'LCLCLC')
    $gedetecteerdPatroon = '';
    $letters = [];
    $cijfers = [];

    foreach (str_split($code) as $karakter) {
        if (ctype_alpha($karakter)) {
            $gedetecteerdPatroon .= 'L';
            $letters[] = $karakter;
        } else {
            $gedetecteerdPatroon .= 'C';
            $cijfers[] = $karakter;
        }
    }

    // Controleer of de code wel exact 3 letters en 3 cijfers bevat
    if (count($letters) !== 3 || count($cijfers) !== 3) {
        throw new InvalidArgumentException("Code moet exact 3 letters en 3 cijfers bevatten.");
    }

    // 2. Zoek de index van dit patroon op in de array
    $patroonIndex = array_search($gedetecteerdPatroon, $patronen, true);
    if ($patroonIndex === false) {
        throw new RuntimeException("Ongeldige patroonstructuur.");
    }

    // 3. Reken de letters terug naar een getal
    $l1_waarde = strpos($alfabet, $letters[0]);
    $l2_waarde = strpos($alfabet, $letters[1]);
    $l3_waarde = strpos($alfabet, $letters[2]);
    $letterGetal = ($l1_waarde * 676) + ($l2_waarde * 26) + $l3_waarde;

    // 4. Reken de cijfers terug naar een getal
    $cijferGetal = (int)implode('', $cijfers);

    // 5. Reconstrueer het oorspronkelijke getal
    $rest = ($letterGetal * 1000) + $cijferGetal;
    
    return ($patroonIndex * 17576000) + $rest;
}
