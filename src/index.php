<?php
session_start();

$dataDir = 'data';
if (!is_dir($dataDir)) {
    mkdir($dataDir, 0755, true);
}

// Name aus Cookie oder Session lesen
$listenName = '';
if (!empty($_COOKIE['listenname'])) {
    $listenName = $_COOKIE['listenname'];
    $_SESSION['listenname'] = $listenName;
} elseif (!empty($_SESSION['listenname'])) {
    $listenName = $_SESSION['listenname'];
}

function getDataFile(string $name, string $dataDir): string {
    if ($name === '') return $dataDir . '/liste_daten.json';
    $safe = preg_replace('/[^a-zA-Z0-9_\-]/', '_', $name);
    $safe = strtolower($safe);
    $safe = preg_replace('/_+/', '_', trim($safe, '_'));
    if ($safe === '') return $dataDir . '/liste_daten.json';
    return $dataDir . '/' . $safe . '_daten.json';
}

$dataFile = getDataFile($listenName, $dataDir);

// Daten laden
$eintraege = [];
if (file_exists($dataFile)) {
    $eintraege = json_decode(file_get_contents($dataFile), true) ?? [];
}

// Material-Vorschläge laden
$materialSuggestions = [];
$materialFile = __DIR__ . '/material.json';
if (file_exists($materialFile)) {
    $materialSuggestions = json_decode(file_get_contents($materialFile), true) ?? [];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $input = json_decode(file_get_contents('php://input'), true);

    if (isset($input['action'])) {

        if ($input['action'] === 'set_name') {
            $newName = trim($input['name'] ?? '');
            if ($newName === '') {
                setcookie('listenname', '', time() - 3600, '/');
                unset($_SESSION['listenname']);
                $listenName = '';
            } else {
                $listenName = $newName;
                setcookie('listenname', $listenName, time() + 365 * 24 * 3600, '/');
                $_SESSION['listenname'] = $listenName;
            }
            $dataFile = getDataFile($listenName, $dataDir);
            $eintraege = [];
            if (file_exists($dataFile)) {
                $eintraege = json_decode(file_get_contents($dataFile), true) ?? [];
            }
            echo json_encode(['success' => true, 'eintraege' => $eintraege, 'dateiname' => basename($dataFile)]);
            exit;
        }

        if ($input['action'] === 'add') {
            $name = trim($input['name'] ?? '');
            $material = trim($input['material'] ?? '');

            if ($name !== '' && $material !== '') {
                if (strpos($name, ';') !== false) {
                    $teile = explode(';', $name);
                    if (count($teile) >= 2) {
                        $nachname = mb_convert_case(trim($teile[0]), MB_CASE_TITLE, "UTF-8");
                        $vorname = mb_convert_case(trim($teile[1]), MB_CASE_TITLE, "UTF-8");
                        $name = $nachname . ", " . $vorname;
                    }
                }

                $datumUhrzeit = date('d.m.Y - H:i');
                $id = uniqid('row_', true);

                $neuereintrag = ['id' => $id, 'datum' => $datumUhrzeit, 'name' => $name, 'material' => $material, 'rueckgabe' => '-'];
                $eintraege[] = $neuereintrag;
                file_put_contents($dataFile, json_encode($eintraege));
                echo json_encode(['success' => true, 'eintrag' => $neuereintrag]);
                exit;
            }
        } elseif ($input['action'] === 'delete') {
            $targetId = $input['id'] ?? '';
            $found = false;
            foreach ($eintraege as $index => $eintrag) {
                $currentId = $eintrag['id'] ?? (string)$index;
                if ($currentId === $targetId) {
                    array_splice($eintraege, $index, 1);
                    $found = true;
                    break;
                }
            }
            if ($found) {
                file_put_contents($dataFile, json_encode($eintraege));
                echo json_encode(['success' => true]);
                exit;
            }
        } elseif ($input['action'] === 'return_by_id') {
            $targetId = $input['id'] ?? '';
            $found = false;
            $updatedEintrag = null;

            foreach ($eintraege as $index => $eintrag) {
                $currentId = $eintrag['id'] ?? (string)$index;
                if ($currentId === $targetId) {
                    $eintraege[$index]['rueckgabe'] = date('d.m.Y - H:i');
                    $updatedEintrag = $eintraege[$index];
                    $found = true;
                    break;
                }
            }

            if ($found) {
                file_put_contents($dataFile, json_encode($eintraege));
                echo json_encode(['success' => true, 'eintrag' => $updatedEintrag]);
                exit;
            } else {
                echo json_encode(['success' => false]);
                exit;
            }
        } elseif ($input['action'] === 'return') {
            $suchBegriff = trim($input['query'] ?? '');
            $found = false;
            $updatedEintrag = null;

            if (strlen($suchBegriff) >= 3) {
                if (strpos($suchBegriff, ';') !== false) {
                    $teile = explode(';', $suchBegriff);
                    if (count($teile) >= 2) {
                        $nachname = mb_convert_case(trim($teile[0]), MB_CASE_TITLE, "UTF-8");
                        $vorname = mb_convert_case(trim($teile[1]), MB_CASE_TITLE, "UTF-8");
                        $suchBegriff = $nachname . ", " . $vorname;
                    }
                }

                $suchBegriffLower = strtolower($suchBegriff);
                for ($i = count($eintraege) - 1; $i >= 0; $i--) {
                    $nameText = strtolower($eintraege[$i]['name'] ?? '');
                    $materialText = strtolower($eintraege[$i]['material'] ?? '');
                    $istBereitsZurueckgegeben = isset($eintraege[$i]['rueckgabe']) && $eintraege[$i]['rueckgabe'] !== '-';

                    if (!$istBereitsZurueckgegeben && ($nameText === $suchBegriffLower || $materialText === $suchBegriffLower || strpos($nameText, $suchBegriffLower) !== false || strpos($materialText, $suchBegriffLower) !== false)) {
                        $eintraege[$i]['rueckgabe'] = date('d.m.Y - H:i');
                        $updatedEintrag = $eintraege[$i];
                        $found = true;
                        break;
                    }
                }
            }

            if ($found) {
                file_put_contents($dataFile, json_encode($eintraege));
                echo json_encode(['success' => true, 'eintrag' => $updatedEintrag]);
                exit;
            } else {
                echo json_encode(['success' => false]);
                exit;
            }
        }
    }
    echo json_encode(['success' => false]);
    exit;
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Digitale - Ausgabeliste</title>
<link class="rounded" rel="icon" type="image/webp" href="barcode.webp">
<script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
<script src="https://unpkg.com/html5-qrcode"></script>
<style>
.highlight {
    background-color: rgb(255, 235, 150) !important;
}
.hide-returned .clv-item-returned {
    display: none !important;
}

/* Tabellen-Styling für den Bildschirm */
.custom-table {
    width: 100%;
    border-collapse: collapse;
}
.custom-table th, .custom-table td {
    border: 1px solid #d1d5db;
    padding: 12px;
}
.custom-table th {
    background-color: #f3f4f6;
    color: #374151;
}
.custom-table tr:hover {
    background-color: #f9fafb;
}

/* Print-Optimierungen für DIN A4 kompakt & Mobilgeräte */
@media print {
    @page {
        size: A4 portrait;
        margin: 8mm 8mm 8mm 8mm;
    }

    html, body {
        background: white !important;
        color: black !important;
        padding: 0 !important;
        margin: 0 !important;
        font-size: 11px !important;
        min-height: 0 !important;
        height: auto !important;
        max-height: none !important;
        overflow: visible !important;
    }

    .max-w-4xl {
        display: contents !important;
        box-shadow: none !important;
        padding: 0 !important;
        margin: 0 !important;
        overflow: visible !important;
        max-width: none !important;
    }

    /* Bedienelemente ausblenden */
    h1, .mb-6.p-4.bg-blue-50, .space-y-4.mb-6, hr, .grid-cols-1.md\:grid-cols-2.gap-4.mb-4, #scanner_modal {
        display: none !important;
    }

    #print_title {
        display: block !important;
        font-size: 20px !important;
        font-weight: bold !important;
        text-align: center !important;
        margin: 5px 0 15px 0 !important;
        color: black !important;
        border-bottom: 2px solid #333 !important;
        padding-bottom: 5px !important;
    }

    .table-container {
        overflow: visible !important;
        border: none !important;
        border-radius: 0px !important;
        padding: 0 !important;
        margin: 0 !important;
        width: 100% !important;
    }

    .custom-table {
        border-collapse: collapse !important;
        width: 99.5% !important;
        table-layout: fixed !important;
        margin: 0 auto !important;
    }
    .custom-table th, .custom-table td {
        border: 1px solid #999 !important;
        padding: 6px 8px !important;
        font-size: 11px !important;
        line-height: 1.3 !important;
        word-wrap: break-word !important;
        color: black !important;
        font-weight: normal !important;
        font-family: Arial, sans-serif !important;
    }
    .custom-table th {
        background-color: #e5e7eb !important;
        border-bottom: 2px solid #666 !important;
        font-weight: bold !important;
    }

    .col-name { width: 24% !important; }
    .col-material { width: 24% !important; }
    .col-ausgabe { width: 26% !important; }
    .col-rueckgabe { width: 26% !important; }

    .custom-table th,
    .clv-datum,
    .clv-rueckgabe {
        white-space: nowrap !important;
    }

    .col-delete, .cell-delete, .col-rueckgabe-btn, .cell-return-btn {
        display: none !important;
    }

    .custom-table tr {
        page-break-inside: avoid !important;
        break-inside: avoid !important;
    }
    .custom-table tr:nth-child(even) {
        background-color: #f9fafb !important;
    }
    .highlight {
        background-color: transparent !important;
    }
}

#print_title {
    display: none;
}
</style>
</head>
<body class="bg-gray-100 min-h-screen p-4 md:p-8 font-sans">

<div class="max-w-4xl mx-auto bg-white p-4 md:p-6 rounded-xl shadow-md overflow-hidden">
<h1 class="text-2xl font-bold mb-6 text-gray-800">Digitale - Ausgabeliste</h1>

<div id="print_title"><?php echo htmlspecialchars($listenName !== '' ? $listenName : 'Ausgabeliste'); ?></div>

<!-- Listenname -->
<div class="mb-6 p-4 bg-blue-50 border border-blue-200 rounded-lg">
<label class="block text-sm font-medium text-gray-700 mb-1">Listenname</label>
<div class="flex flex-wrap gap-2">
<input type="text" id="txt_listenname"
class="flex-1 min-w-[200px] border border-gray-300 rounded-md p-2 focus:ring-2 focus:ring-blue-500 focus:outline-none bg-white"
placeholder="z.B. Lager, Schlüssel, ..."
value="<?php echo htmlspecialchars($listenName); ?>">
<button type="button" id="btn_save_name"
class="px-4 bg-blue-600 text-white font-medium rounded-md hover:bg-blue-700 transition cursor-pointer whitespace-nowrap">
Speichern
</button>
<button type="button" id="btn_clear_name"
class="px-3 bg-gray-200 text-gray-600 font-bold rounded-md hover:bg-red-100 hover:text-red-600 transition cursor-pointer"
title="Namen löschen">
&times;
</button>
<button type="button" id="btn_print_pdf"
class="px-4 bg-emerald-600 text-white font-medium rounded-md hover:bg-emerald-700 transition cursor-pointer whitespace-nowrap flex items-center gap-1"
title="Als PDF drucken">
🖨️ Drucken / PDF
</button>
</div>
<p class="text-xs text-gray-500 mt-1">
Datei: <span id="info_dateiname" class="font-mono"><?php echo htmlspecialchars(basename($dataFile)); ?></span>
</p>
</div>

<div class="space-y-4 mb-6">
<div class="grid grid-cols-1 md:grid-cols-2 gap-4">
<div>
<label class="block text-sm font-medium text-gray-700 mb-1">Material</label>
<div class="flex gap-2">
<input type="text" id="txt_material" list="material_liste" autocomplete="off" class="w-full border border-gray-300 rounded-md p-2 focus:ring-2 focus:ring-blue-500 focus:outline-none" placeholder="z.B. Schlüssel">
<datalist id="material_liste">
<?php foreach ($materialSuggestions as $mat): ?>
    <option value="<?php echo htmlspecialchars($mat); ?>"></option>
<?php endforeach; ?>
</datalist>
<button type="button" class="btn-scan p-2 bg-gray-200 hover:bg-gray-300 rounded-md transition flex items-center justify-center cursor-pointer" data-target="txt_material" title="Barcode/QR-Code scannen">
<img src="barcode.webp" alt="Scan" class="w-6 h-6 object-contain">
</button>
</div>
</div>
<div>
<label class="block text-sm font-medium text-gray-700 mb-1">Nachname, Vorname oder QR-Code</label>
<div class="flex gap-2">
<input type="text" id="txt_name" class="w-full border border-gray-300 rounded-md p-2 focus:ring-2 focus:ring-blue-500 focus:outline-none" placeholder="z.B. Mustermann, Max">
<button type="button" class="btn-scan p-2 bg-gray-200 hover:bg-gray-300 rounded-md transition flex items-center justify-center cursor-pointer" data-target="txt_name" title="Barcode/QR-Code scannen">
<img src="barcode.webp" alt="Scan" class="w-6 h-6 object-contain">
</button>
</div>
</div>
</div>
<button type="button" id="btn_hinzufuegen" class="w-full bg-blue-600 text-white font-medium p-2 rounded-md hover:bg-blue-700 transition cursor-pointer">Hinzufügen</button>
</div>

<hr class="border-gray-200 my-6">

<div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
<div>
<label class="block text-sm font-medium text-gray-700 mb-1">Suche nach (ab 3 Zeichen)</label>
<div class="flex gap-2">
<input type="text" id="txt_suche" class="w-full border border-gray-300 rounded-md p-2 focus:ring-2 focus:ring-yellow-500 focus:outline-none" placeholder="Datum, Name oder Material">
<button type="button" class="btn-scan p-2 bg-gray-200 hover:bg-gray-300 rounded-md transition flex items-center justify-center cursor-pointer" data-target="txt_suche" title="Barcode/QR-Code scannen">
<img src="barcode.webp" alt="Scan" class="w-6 h-6 object-contain">
</button>
</div>
</div>
<div>
<label class="block text-sm font-medium text-gray-700 mb-1 flex justify-between items-center flex-wrap gap-2">
<span>Rückgabe (Enter zum Bestätigen)</span>
<span class="flex items-center text-xs font-normal text-gray-600 gap-1 select-none">
<input type="checkbox" id="chk_hide_returned" class="rounded border-gray-300 text-blue-600 focus:ring-blue-500 cursor-pointer">
Zurückgegebene ausblenden
</span>
</label>
<div class="flex gap-2">
<input type="text" id="txt_rueckgabe" list="rueckgabe_liste" autocomplete="off" class="w-full border border-gray-300 rounded-md p-2 focus:ring-2 focus:ring-yellow-500 focus:outline-none" placeholder="Datum, Name oder Material">
<datalist id="rueckgabe_liste"></datalist>
<button type="button" class="btn-scan p-2 bg-gray-200 hover:bg-gray-300 rounded-md transition flex items-center justify-center cursor-pointer" data-target="txt_rueckgabe" title="Barcode/QR-Code scannen">
<img src="barcode.webp" alt="Scan" class="w-6 h-6 object-contain">
</button>
</div>
</div>
</div>

<div class="table-container w-full overflow-x-auto rounded-md border border-gray-300">
<table class="custom-table">
<thead>
<tr>
<th id="th_name" class="col-name text-left text-sm font-bold cursor-pointer select-none">
<div class="flex justify-between items-center gap-2"><span>Name</span><span class="sort-icon text-xs text-gray-600"></span></div>
</th>
<th id="th_material" class="col-material text-left text-sm font-bold cursor-pointer select-none">
<div class="flex justify-between items-center gap-2"><span>Material</span><span class="sort-icon text-xs text-gray-600"></span></div>
</th>
<th id="th_datum" class="col-ausgabe text-left text-sm font-bold cursor-pointer select-none">
<div class="flex justify-between items-center gap-1"><span>Ausgabe (Datum/Zeit)</span><span class="sort-icon text-xs text-gray-600"></span></div>
</th>
<th class="col-rueckgabe text-left text-sm font-bold select-none">Rückgabe (Datum/Zeit)</th>
<th class="col-rueckgabe-btn text-center text-sm font-bold select-none w-[50px]">Rückgabe</th>
<th class="col-delete text-center text-sm font-bold select-none w-[65px]">Löschen</th>
</tr>
</thead>
<tbody id="clv_liste">
<?php foreach ($eintraege as $index => $eintrag):
$rowId = $eintrag['id'] ?? (string)$index;
$istZurueckgegeben = isset($eintrag['rueckgabe']) && $eintrag['rueckgabe'] !== '-';
?>
<tr class="clv-item <?php echo $istZurueckgegeben ? 'clv-item-returned' : ''; ?> transition" data-id="<?php echo htmlspecialchars($rowId); ?>">
<td class="font-medium text-gray-900 text-xs md:text-sm clv-name break-words"><?php echo htmlspecialchars($eintrag['name']); ?></td>
<td class="text-gray-600 text-xs md:text-sm clv-material break-words"><?php echo htmlspecialchars($eintrag['material']); ?></td>
<td class="text-gray-600 font-mono text-xs md:text-sm clv-datum"><?php echo htmlspecialchars($eintrag['datum'] ?? '-'); ?></td>
<td class="clv-rueckgabe font-mono text-xs md:text-sm <?php echo $istZurueckgegeben ? 'text-green-600 font-medium' : 'text-gray-400'; ?>"><?php echo htmlspecialchars($eintrag['rueckgabe'] ?? '-'); ?></td>
<td class="cell-return-btn text-center text-sm md:text-base p-2">
    <?php if (!$istZurueckgegeben): ?>
        <button type="button" class="btn-return-click text-emerald-600 hover:text-emerald-800 font-bold transition cursor-pointer select-none" title="Als zurückgegeben markieren">↩️</button>
    <?php else: ?>
        <span class="text-green-600 font-bold">✓</span>
    <?php endif; ?>
</td>
<td class="cell-delete text-red-600 font-bold text-center text-sm md:text-base hover:bg-red-50 transition cursor-pointer select-none">&times;</td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</div>

<div id="scanner_modal" class="hidden fixed inset-0 bg-black/80 flex flex-col items-center justify-center p-4 z-50">
<div class="bg-white w-full max-w-md p-4 rounded-xl shadow-xl">
<div class="flex justify-between items-center mb-4">
<h3 class="text-lg font-bold text-gray-900">Barcode / QR-Code scannen</h3>
<button type="button" id="btn_close_scanner" class="text-gray-500 hover:text-gray-700 text-2xl font-bold cursor-pointer">&times;</button>
</div>
<div id="interactive_scanner" class="w-full overflow-hidden rounded-lg bg-gray-100"></div>
</div>
</div>

<script>
const txtMaterial = document.getElementById('txt_material');
const txtName = document.getElementById('txt_name');
const btnHinzufuegen = document.getElementById('btn_hinzufuegen');
const txtSuche = document.getElementById('txt_suche');
const txtRueckgabe = document.getElementById('txt_rueckgabe');
const chkHideReturned = document.getElementById('chk_hide_returned');
const clvListe = document.getElementById('clv_liste');
const scannerModal = document.getElementById('scanner_modal');
const btnCloseScanner = document.getElementById('btn_close_scanner');
const txtListenname = document.getElementById('txt_listenname');
const btnSaveName = document.getElementById('btn_save_name');
const btnClearName = document.getElementById('btn_clear_name');
const btnPrintPdf = document.getElementById('btn_print_pdf');
const infoDateiname = document.getElementById('info_dateiname');
const printTitle = document.getElementById('print_title');

let html5QrcodeScanner = null;
let currentTargetInput = null;
let aktuelleSortierung = { spalte: null, aufsteigend: true };
const beepSound = new Audio('beep.ogg');

txtMaterial.focus();

// Sammelt NUR Materialien von noch nicht zurückgegebenen Einträgen
function updateRueckgabeVorschlaege() {
    const datalist = document.getElementById('rueckgabe_liste');
    if (!datalist) return;
    datalist.innerHTML = '';
    
    const vorschlaege = new Set();
    document.querySelectorAll('.clv-item:not(.clv-item-returned)').forEach(item => {
        const materialText = item.querySelector('.clv-material').textContent.trim();
        if(materialText) vorschlaege.add(materialText);
    });
    
    Array.from(vorschlaege).sort().forEach(wert => {
        const option = document.createElement('option');
        option.value = wert;
        datalist.appendChild(option);
    });
}

// Vorschläge beim Start aufbauen
document.addEventListener('DOMContentLoaded', () => {
    updateRueckgabeVorschlaege();
});

btnPrintPdf.addEventListener('click', () => {
    window.print();
});

async function saveListenname(name) {
    const response = await fetch('index.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'set_name', name: name })
    });
    const result = await response.json();
    if (result.success) {
        infoDateiname.textContent = result.dateiname;
        printTitle.textContent = name !== '' ? name : 'Ausgabeliste';
        renderListe(result.eintraege);
        aktuelleSortierung = { spalte: null, aufsteigend: true };
        document.querySelectorAll('.sort-icon').forEach(el => el.textContent = '');
        txtSuche.value = '';
        txtRueckgabe.value = '';
    }
}

btnSaveName.addEventListener('click', () => {
    saveListenname(txtListenname.value.trim());
});

txtListenname.addEventListener('keydown', (e) => {
    if (e.key === 'Enter') {
        e.preventDefault();
        saveListenname(txtListenname.value.trim());
        txtMaterial.focus();
    }
});

btnClearName.addEventListener('click', () => {
    if (confirm('Listenname löschen? Die gespeicherten Daten bleiben erhalten.')) {
        txtListenname.value = '';
        saveListenname('');
    }
});

function renderListe(eintraege) {
    clvListe.innerHTML = '';
    eintraege.forEach(eintrag => {
        const item = document.createElement('tr');
        const istZurueckgegeben = eintrag.rueckgabe && eintrag.rueckgabe !== '-';
        item.className = `clv-item ${istZurueckgegeben ? 'clv-item-returned' : ''} transition`;
        item.setAttribute('data-id', eintrag.id);
        item.innerHTML = `
        <td class="p-3 font-medium text-gray-900 text-xs md:text-sm clv-name break-words">${escapeHtml(eintrag.name)}</td>
        <td class="p-3 text-gray-600 text-xs md:text-sm clv-material break-words">${escapeHtml(eintrag.material)}</td>
        <td class="p-3 text-gray-600 font-mono text-xs md:text-sm clv-datum">${escapeHtml(eintrag.datum ?? '-')}</td>
        <td class="p-3 clv-rueckgabe font-mono text-xs md:text-sm ${istZurueckgegeben ? 'text-green-600 font-medium' : 'text-gray-400'}">${escapeHtml(eintrag.rueckgabe ?? '-')}</td>
        <td class="cell-return-btn text-center text-sm md:text-base p-2">
            ${!istZurueckgegeben 
                ? '<button type="button" class="btn-return-click text-emerald-600 hover:text-emerald-800 font-bold transition cursor-pointer select-none" title="Als zurückgegeben markieren">↩️</button>' 
                : '<span class="text-green-600 font-bold">✓</span>'}
        </td>
        <td class="cell-delete p-3 text-red-600 font-bold text-center text-sm md:text-base hover:bg-red-50 transition cursor-pointer select-none">&times;</td>
        `;
        clvListe.appendChild(item);
    });
    updateRueckgabeVorschlaege();
}

txtMaterial.addEventListener('keydown', (e) => {
    if (e.key === 'Enter') {
        e.preventDefault();
        txtName.focus();
    }
});

txtName.addEventListener('keydown', (e) => {
    if (e.key === 'Enter') {
        e.preventDefault();
        btnHinzufuegen.click();
    }
});

btnHinzufuegen.addEventListener('click', async (e) => {
    e.preventDefault();
    const materialVal = txtMaterial.value.trim();
    const nameVal = txtName.value.trim();

    if (materialVal === "" || nameVal === "") {
        alert("Bitte Material und Namen eingeben!");
        materialVal === "" ? txtMaterial.focus() : txtName.focus();
        return;
    }

    const response = await fetch('index.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'add', name: nameVal, material: materialVal })
    });

    const result = await response.json();
    if (result.success) {
        const newItem = document.createElement('tr');
        const istZurueckgegeben = result.eintrag.rueckgabe && result.eintrag.rueckgabe !== '-';
        newItem.className = `clv-item ${istZurueckgegeben ? 'clv-item-returned' : ''} transition`;
        newItem.setAttribute('data-id', result.eintrag.id);
        newItem.innerHTML = `
        <td class="p-3 font-medium text-gray-900 text-xs md:text-sm clv-name break-words">${escapeHtml(result.eintrag.name)}</td>
        <td class="p-3 text-gray-600 text-xs md:text-sm clv-material break-words">${escapeHtml(result.eintrag.material)}</td>
        <td class="p-3 text-gray-600 font-mono text-xs md:text-sm clv-datum">${escapeHtml(result.eintrag.datum)}</td>
        <td class="p-3 clv-rueckgabe font-mono text-xs md:text-sm text-gray-400">${escapeHtml(result.eintrag.rueckgabe ?? '-')}</td>
        <td class="cell-return-btn text-center text-sm md:text-base p-2">
            <button type="button" class="btn-return-click text-emerald-600 hover:text-emerald-800 font-bold transition cursor-pointer select-none" title="Als zurückgegeben markieren">↩️</button>
        </td>
        <td class="cell-delete p-3 text-red-600 font-bold text-center text-sm md:text-base hover:bg-red-50 transition cursor-pointer select-none">&times;</td>
        `;
        clvListe.appendChild(newItem);

        txtMaterial.value = "";
        txtName.value = "";
        txtMaterial.focus();

        txtSuche.value = "";
        txtSuche.dispatchEvent(new Event('input'));
        txtRueckgabe.value = "";

        if (aktuelleSortierung.spalte) {
            sortiereListe(aktuelleSortierung.spalte, aktuelleSortierung.aufsteigend);
        }

        updateRueckgabeVorschlaege();
    }
});

clvListe.addEventListener('click', async (e) => {
    const targetRow = e.target.closest('.clv-item');
    if (!targetRow) return;
    const id = targetRow.getAttribute('data-id');
    if (!id) return;

    // Rückgabe per Klick
    if (e.target.classList.contains('btn-return-click')) {
        const response = await fetch('index.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'return_by_id', id: id })
        });

        const result = await response.json();
        if (result.success) {
            targetRow.classList.add('clv-item-returned');
            
            const rueckgabeZelle = targetRow.querySelector('.clv-rueckgabe');
            if (rueckgabeZelle) {
                rueckgabeZelle.textContent = result.eintrag.rueckgabe;
                rueckgabeZelle.classList.remove('text-gray-400');
                rueckgabeZelle.classList.add('text-green-600', 'font-medium');
            }

            const btnZelle = targetRow.querySelector('.cell-return-btn');
            if (btnZelle) {
                btnZelle.innerHTML = '<span class="text-green-600 font-bold">✓</span>';
            }

            bekaempfeSuche();
            updateRueckgabeVorschlaege();
        }
        return;
    }

    // Löschen per Klick
    if (e.target.classList.contains('cell-delete')) {
        if (confirm("Soll der ausgewählte Eintrag gelöscht werden?")) {
            const response = await fetch('index.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ action: 'delete', id: id })
            });

            const result = await response.json();
            if (result.success) {
                targetRow.remove();
                updateRueckgabeVorschlaege();
            }
        }
    }
});

function bekaempfeSuche() {
    let sucheVal = txtSuche.value.trim().toLowerCase();
    let rueckgabeVal = txtRueckgabe.value.trim().toLowerCase();
    const items = document.querySelectorAll('.clv-item');

    items.forEach(item => item.classList.remove('highlight'));

    if (rueckgabeVal.includes(';')) {
        const teile = rueckgabeVal.split(';');
        if (teile.length >= 2 && teile[0].trim() !== '' && teile[1].trim() !== '') {
            rueckgabeVal = teile[0].trim().toLowerCase();
        }
    }

    const aktiverBegriff = rueckgabeVal.length >= 3 ? rueckgabeVal : (sucheVal.length >= 3 ? sucheVal : '');

    if (aktiverBegriff.length < 3) {
        return;
    }

    const suchTeile = aktiverBegriff.split(' ').filter(p => p !== '');
    let ersterTreffer = null;

    items.forEach(item => {
        const datumText = item.querySelector('.clv-datum').textContent.toLowerCase();
        const nameText = item.querySelector('.clv-name').textContent.toLowerCase();
        const materialText = item.querySelector('.clv-material').textContent.toLowerCase();
        const rueckgabeText = item.querySelector('.clv-rueckgabe').textContent.toLowerCase();
        const zeilenInhaltGesamt = `${datumText} ${nameText} ${materialText} ${rueckgabeText}`;

        let alleTeileGefunden = true;
        for (const teil of suchTeile) {
            if (!zeilenInhaltGesamt.includes(teil)) {
                alleTeileGefunden = false;
                break;
            }
        }

        if (alleTeileGefunden) {
            item.classList.add('highlight');
            if (!ersterTreffer) ersterTreffer = item;
        }
    });

    if (ersterTreffer) {
        ersterTreffer.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }
}

txtSuche.addEventListener('input', bekaempfeSuche);
txtRueckgabe.addEventListener('input', bekaempfeSuche);

chkHideReturned.addEventListener('change', () => {
    if (chkHideReturned.checked) {
        clvListe.classList.add('hide-returned');
    } else {
        clvListe.classList.remove('hide-returned');
    }
});

txtRueckgabe.addEventListener('keydown', async (e) => {
    if (e.key === 'Enter') {
        e.preventDefault();
        const wert = txtRueckgabe.value.trim();
        if (wert.length < 3) {
            alert('Bitte mindestens 3 Zeichen für die Rückgabe eingeben.');
            return;
        }

        const response = await fetch('index.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'return', query: wert })
        });

        const result = await response.json();
        if (result.success) {
            const item = clvListe.querySelector(`[data-id="${result.eintrag.id}"]`);
            if (item) {
                item.classList.add('clv-item-returned');
                const rueckgabeZelle = item.querySelector('.clv-rueckgabe');
                if (rueckgabeZelle) {
                    rueckgabeZelle.textContent = result.eintrag.rueckgabe;
                    rueckgabeZelle.classList.remove('text-gray-400');
                    rueckgabeZelle.classList.add('text-green-600', 'font-medium');
                }
                const btnZelle = item.querySelector('.cell-return-btn');
                if (btnZelle) {
                    btnZelle.innerHTML = '<span class="text-green-600 font-bold">✓</span>';
                }
            }
            txtRueckgabe.value = '';
            bekaempfeSuche();
            txtMaterial.focus();
            
            updateRueckgabeVorschlaege();
        } else {
            alert('Kein passender, offener Eintrag gefunden.');
        }
    }
});

function sortiereListe(spaltenKlasse, aufsteigend) {
    const items = Array.from(document.querySelectorAll('.clv-item'));
    if (items.length === 0) return;

    items.sort((a, b) => {
        const elA = a.querySelector('.' + spaltenKlasse);
        const elB = b.querySelector('.' + spaltenKlasse);

        let textA = elA ? elA.textContent.trim() : '';
        let textB = elB ? elB.textContent.trim() : '';

        if (spaltenKlasse === 'clv-datum') {
            textA = konvertiereDatumFuerSortierung(textA);
            textB = konvertiereDatumFuerSortierung(textB);
        }

        return aufsteigend ? textA.localeCompare(textB) : textB.localeCompare(textA);
    });

    items.forEach(item => clvListe.appendChild(item));

    aktuelleSortierung.spalte = spaltenKlasse;
    aktuelleSortierung.aufsteigend = aufsteigend;

    aktualisiereSortierPfeile(spaltenKlasse, aufsteigend);
}

function konvertiereDatumFuerSortierung(deutschesDatum) {
    const teile = deutschesDatum.split(' - ');
    if (teile.length !== 2) return deutschesDatum;
    const datumTeile = teile[0].split('.');
    if (datumTeile.length !== 3) return deutschesDatum;
    return datumTeile[2] + datumTeile[1] + datumTeile[0] + teile[1].replace(':', '');
}

function aktualisiereSortierPfeile(aktiveKlasse, aufsteigend) {
    document.querySelectorAll('.sort-icon').forEach(el => el.textContent = '');
    let btnId = aktiveKlasse === 'clv-datum' ? 'th_datum' : (aktiveKlasse === 'clv-name' ? 'th_name' : 'th_material');
    const container = document.querySelector(`#${btnId} .sort-icon`);
    if (container) container.textContent = aufsteigend ? ' ▲' : ' ▼';
}

document.getElementById('th_datum').addEventListener('click', () => sortiereListe('clv-datum', aktuelleSortierung.spalte === 'clv-datum' ? !aktuelleSortierung.aufsteigend : true));
document.getElementById('th_name').addEventListener('click', () => sortiereListe('clv-name', aktuelleSortierung.spalte === 'clv-name' ? !aktuelleSortierung.aufsteigend : true));
document.getElementById('th_material').addEventListener('click', () => sortiereListe('clv-material', aktuelleSortierung.spalte === 'clv-material' ? !aktuelleSortierung.aufsteigend : true));

document.querySelectorAll('.btn-scan').forEach(button => {
    button.addEventListener('click', (e) => {
        e.preventDefault();
        const targetId = button.getAttribute('data-target');
        currentTargetInput = document.getElementById(targetId);
        scannerModal.classList.remove('hidden');
        startScanner();
    });
});

function startScanner() {
    if (html5QrcodeScanner) html5QrcodeScanner.clear();
    html5QrcodeScanner = new Html5Qrcode("interactive_scanner");
    html5QrcodeScanner.start({ facingMode: "environment" }, { fps: 15, qrbox: { width: 280, height: 160 } }, onScanSuccess, onScanFailure)
    .catch(err => {
        alert("Kamera-Zugriff verweigert oder nicht verfügbar.");
        closeScanner();
    });
}

function onScanSuccess(decodedText) {
    if (currentTargetInput) {
        currentTargetInput.value = decodedText;
        currentTargetInput.dispatchEvent(new Event('input'));
        if (currentTargetInput.id === 'txt_material') txtName.focus();
        else if (currentTargetInput.id === 'txt_name') btnHinzufuegen.click();
        else if (currentTargetInput.id === 'txt_rueckgabe') {
            txtRueckgabe.dispatchEvent(new KeyboardEvent('keydown', { key: 'Enter' }));
        }
    }
    beepSound.play().catch(err => console.log(err));
    if (navigator.vibrate) navigator.vibrate(100);
    closeScanner();
}

function onScanFailure() {}

function closeScanner() {
    scannerModal.classList.add('hidden');
    if (html5QrcodeScanner) {
        html5QrcodeScanner.stop().then(() => html5QrcodeScanner.clear()).catch(err => console.error(err));
    }
}

btnCloseScanner.addEventListener('click', closeScanner);
scannerModal.addEventListener('click', (e) => { if (e.target === scannerModal) closeScanner(); });

function escapeHtml(str) {
    return str.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
}
</script>
</body>
</html>