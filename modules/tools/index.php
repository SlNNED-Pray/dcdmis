<?php
// tools/index.php

contentTitle('Monitoring Tools');

$userPositionId = position($userId)['position_id'] ?? null;
$sectionCode = employeeSection($userId)['id'] ?? null;
$allowedPositions = ['SDS', 'ASDS', 'CES', 'ATY3', 'ITO1', 'A3', 'ADOF5', 'PSDS', 'EPS', 'PLO3'];

$showFilledUnfilledItems = in_array($userPositionId, $allowedPositions, true)
    || ($userPositionId === 'ADOF4' && $sectionCode === 'PER')
    || (in_array($userPositionId, ['SREPS', 'EPS2'], true) && $sectionCode === 'SME')
    || ($userPositionId === 'SREPS' && $sectionCode === 'HRD');
?>

<div class="row mt-4">
    <?php
    if (!$isNonDivision) {
        $divisionCards = [
            ['title' => 'Procurement Monitoring', 'url' => 'https://docs.google.com/spreadsheets/d/1GJedbErwA0BFT4LfM-5QTBJMQeliHXMK/edit?gid=1975740446#gid=1975740446', 'color' => 'primary'],
            ['title' => 'Agency Action Plan and Status of Implementation', 'url' => 'https://docs.google.com/spreadsheets/d/15UW4kGOiJvMIViW-muOG7KTCX3Io5HcX9ZyRgj8rXAc/edit?usp=sharing', 'color' => 'primary'],
        ];

        foreach ($divisionCards as $card) {
            cardMini($card['title'], $card['url'], 'fa-file-excel', $card['color'], true);
        }

        if ($showFilledUnfilledItems) {
            $restrictedCards = [
                ['title' => 'School Heads and Administrative Officer II Deployment', 'url' => 'https://depedph-my.sharepoint.com/:x:/g/personal/love_ricafort_deped_gov_ph/IQDHDuxIqpoIRYPcTDHRnjdFAUp6F2tLvC_pU-doGvVkBKU?e=Mraf6I&fbclid=IwY2xjawSKKS5leHRuA2FlbQIxMABicmlkETFIalZjVmNMOUZLanp0M3BHc3J0YwZhcHBfaWQQMjIyMDM5MTc4ODIwMDg5MgABHsCgj_m_N359LUp1YfVGc0pNimXwtPQ1ZWhPXrykVM1uE1WuCqCjoozvhdVV_aem_-1IDu7yofb23YBiYwwcbpQ'],
                ['title' => 'Filled and Unfilled Items', 'url' => 'https://depedph-my.sharepoint.com/:x:/g/personal/love_ricafort_deped_gov_ph/IQALaDK4t-BhRI-wKJCb6qvAAR45KtJb8sRJwflKUZ1XJDU?e=gi7Njk&fbclid=IwY2xjawSKAEVleHRuA2FlbQIxMABicmlkETFIalZjVmNMOUZLanp0M3BHc3J0YwZhcHBfaWQQMjIyMDM5MTc4ODIwMDg5MgABHoPs_8vDS_7XrlUXvaWJ1II2sYCXvTirrxXi6Jy_tLjL0vBmkPhgF2AOpdX8_aem_Sqcv-SPKcNW10XD3TZeXrg'],
                ['title' => 'Filled and Unfilled Items Per Authorized Positions', 'url' => 'https://depedph-my.sharepoint.com/personal/love_ricafort_deped_gov_ph/_layouts/15/onedrive.aspx?id=%2Fpersonal%2Flove%5Fricafort%5Fdeped%5Fgov%5Fph%2FDocuments%2FPLOTTING%20AND%20MONITORING%20OF%20PERSONNEL%20%281%29%2FDBM%2DGMIS%2F2026&ga=1'],
            ];

            foreach ($restrictedCards as $card) {
                cardMini($card['title'], $card['url'], 'fa-file-excel', 'primary', true);
            }
        }

        $sectionTools = [
            ['title' => 'Admin and GS Monitoring', 'url' => 'https://depedph-my.sharepoint.com/:x:/g/personal/leslie_egason_deped_gov_ph/IQDHLrtJKnXoRIrx0td58-JvAfOse_FBmd8hm40CGl27Vo4?rtime=ZZSda7LK3kg', 'color' => 'primary'],
            ['title' => 'CID Sub-AROs Monitoring', 'url' => 'https://docs.google.com/spreadsheets/d/1_6VWyaPAIn0I212ig4j2Ek7CLQKqz5iT/edit?gid=609413157#gid=609413157', 'color' => 'success'],
            ['title' => 'Master Class Program Submission Monitoring', 'url' => 'https://docs.google.com/spreadsheets/d/12Yc_OKO6Ry8U1npJMxYZKFu61cHl7SMq/edit?gid=1793410442#gid=1793410442', 'color' => 'success'],
            ['title' => 'Project CHIEF', 'url' => 'https://depedph-my.sharepoint.com/:x:/g/personal/nur_hussien_deped_gov_ph/IQDsNFGvmEPFT79ZSoyQHxdpAYwuC5CAVxKo_4Ua4vajSvo?e=3BrEAi&fbclid=IwY2xjawQR5SxleHRuA2FlbQIxMQBzcnRjBmFwcF9pZAEwAAEe6G2zEsEnEhevADQniaiyEoa6Hge2INXw1rWOpvUIBWmtOIv0T0_Yid5G8z4_aem_bNHrnmNi8Pq5N8yl4-yXSQ', 'color' => 'orange'],
            ['title' => 'Project One Drive, One Data Management', 'url' => 'https://depedph-my.sharepoint.com/:x:/g/personal/smme_dipolog_deped_gov_ph/Eccc1Z9Bi8hJiQgXX_WbQhsBxHgNljGwKUHOZMhixltFpw?e=j54Pne&fbclid=IwY2xjawQR521leHRuA2FlbQIxMQBzcnRjBmFwcF9pZAEwAAEebYJqACyofMKxXSMvziJnaBiQkryQZrBQW6JKHiQlDnvzc5LzNGCboR0MVYc_aem_nUwz_RBdjJRubvz6UIYALQ', 'color' => 'orange'],
            ['title' => 'SGOD Sub-AROs Monitoring', 'url' => 'https://docs.google.com/spreadsheets/d/1HhxfiUdDbzZFddKwi48TfrqEscGPaZtC/edit?gid=609413157#gid=609413157', 'color' => 'orange'],
            ['title' => 'Monitoring Tool for PRIME-HRM Level III', 'url' => 'https://depedph-my.sharepoint.com/:x:/g/personal/smme_dipolog_deped_gov_ph/IQD3HpsFq2nsSqDIV9ugNY9DAbCjeWIST5zuiRk3W3c6SE0?rtime=GPLFmc7G3kg', 'color' => 'orange'],
            ['title' => 'SDO PIR-RMETA 2026', 'url' => 'https://tinyurl.com/SDODIPOLOG-PIR-RMETA2026', 'color' => 'orange'],
        ];

        foreach ($sectionTools as $card) {
            cardMini($card['title'], $card['url'], 'fa-file-excel', $card['color'], true);
        }
    }

    $globalCards = [
        ['title' => 'Project TRACE SY 2025-2026', 'url' => 'https://depedph-my.sharepoint.com/:x:/g/personal/smme_dipolog_deped_gov_ph/IQB5qVXMavCeS7lnrnKRJ-nuAQuFN0dcgiossurYPr9bfHc?e=TOxaIe', 'color' => 'orange'],
        ['title' => 'Project TRACE SY 2026-2027', 'url' => 'https://depedph-my.sharepoint.com/:x:/g/personal/smme_dipolog_deped_gov_ph/IQAs1Jt1VwAzRa6Zazby-5M8AR7uYnpPPSMGH-J9LoXUVFc?e=0c9SVh', 'color' => 'orange'],
        ['title' => 'Comparative Monitoring of LSF and PSF', 'url' => 'https://drive.google.com/file/d/1-_Cv-95p_ONlPKqJpOyxg02mv5MJH91K/view?usp=sharing', 'color' => 'success'],
    ];

    foreach ($globalCards as $card) {
        cardMini($card['title'], $card['url'], 'fa-file-excel', $card['color'], true);
    }
    ?>
</div>