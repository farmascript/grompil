<?php

/**
 *
 * @date_cre: Wed 2026-11-04
 * @date_mut: Fri 2026-11-06
 *
 * @credit_software: Lieven Maus
 * @credit_code: chatGPT, codex
 *
 * @copyright: gromPil SaaS - copyright 1981-present - Lieven Maus
 * @info_email: info@grompil.com
 *
 * @description: Runs parameterized PostgreSQL queries with consistent failure handling.
 *
 * https://dw.grompil.com/!saas/doku.php?id=broncode:includes:do_6_char_code_test
 * 
 */

require("do_6_char_code.php");

// Genereer een code uit een getal
$origineelGetal = 98765432;
$origineelGetal = 1000;
$schoneCode = getalNaarCode($origineelGetal); // Geeft bijv. 'CLLCLC'-patroon variant

// Test met slordige/geformatteerde invoer van een gebruiker
$vuileInvoer = " " . substr($schoneCode, 0, 2) . "-" . substr($schoneCode, 2, 2) . "-" . substr($schoneCode, 4, 2) . " "; 
// Dit maakt er bijvoorbeeld " AB-12-C3 " van (inclusief kleine letters als de basis dat had)

echo "Vuile invoer: '" . $vuileInvoer . "'\n";

try {
    $teruggerekend = codeNaarGetal($vuileInvoer);
    echo "Succesvol opgeschoond en teruggerekend naar: " . $teruggerekend . "\n";
    
    if ($origineelGetal === $teruggerekend) {
        echo "De code klopt exact met het origineel!";
    }
} catch (Exception $e) {
    echo "Fout: " . $e->getMessage();
}
