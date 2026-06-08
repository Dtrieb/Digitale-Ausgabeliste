<?php
// Einfache Datei-Datenbank simulieren
$dataFile = 'liste_daten.json';

// Daten laden
$eintraege = [];
if (file_exists($dataFile)) {
    $eintraege = json_decode(file_get_contents($dataFile), true) ?? [];
}

// Wenn ein POST-Request kommt (Eintrag löschen oder hinzufügen via AJAX)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $input = json_decode(file_get_contents('php://input'), true);

    if (isset($input['action'])) {
        if ($input['action'] === 'add') {
            $name = trim($input['name'] ?? '');
            $material = trim($input['material'] ?? '');

            if ($name !== '' && $material !== '') {
                // Semikolon-Logik wie im B4X-Code
                if (strpos($name, ';') !== false) {
                    $teile = explode(';', $name);
                    if (count($teile) >= 2) {
                        $nachname = mb_convert_case(trim($teile[0]), MB_CASE_TITLE, "UTF-8");
                        $vorname = mb_convert_case(trim($teile[1]), MB_CASE_TITLE, "UTF-8");
                        $name = $nachname . ", " . $vorname;
                    }
                }

                // Aktuelles Datum und Uhrzeit generieren
                $datumUhrzeit = date('d.m.Y - H:i');
                
                // Eindeutige ID vergeben
                $id = uniqid('row_', true);

                $neuereintrag = ['id' => $id, 'datum' => $datumUhrzeit, 'name' => $name, 'material' => $material];
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
    <title>Ausgabeliste</title>
    <link rel="icon" type="image/webp" href="barcode.webp">
    <script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
    <script src="https://unpkg.com/html5-qrcode"></script>
    <style>
        .highlight {
            background-color: rgb(255, 235, 150) !important;
        }
    </style>
</head>
<body class="bg-gray-100 min-h-screen p-4 md:p-8 font-sans">

    <div class="max-w-3xl mx-auto bg-white p-6 rounded-xl shadow-md">
        <h1 class="text-2xl font-bold mb-6 text-gray-800">Digitale - Ausgabeliste</h1>

        <div class="space-y-4 mb-6">
            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-medium text-gray-700 mb-1">Material</label>
                    <div class="flex gap-2">
                        <input type="text" id="txt_material" class="w-full border border-gray-300 rounded-md p-2 focus:ring-2 focus:ring-blue-500 focus:outline-none" placeholder="z.B. Schlüssel">
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

        <div class="mb-4">
            <label class="block text-sm font-medium text-gray-700 mb-1">Suche nach (ab 3 Zeichen)</label>
            <div class="flex gap-2">
                <input type="text" id="txt_suche" class="w-full border border-gray-300 rounded-md p-2 focus:ring-2 focus:ring-yellow-500 focus:outline-none" placeholder="Datum, Name oder Material">
                <button type="button" class="btn-scan p-2 bg-gray-200 hover:bg-gray-300 rounded-md transition flex items-center justify-center cursor-pointer" data-target="txt_suche" title="Barcode/QR-Code scannen">
                    <img src="barcode.webp" alt="Scan" class="w-6 h-6 object-contain">
                </button>
            </div>
        </div>

        <div class="grid grid-cols-[minmax(90px,110px)_1fr_1.5fr] md:grid-cols-[170px_1fr_1.5fr] bg-gray-200 text-sm font-bold text-gray-700 rounded-t-md border border-gray-300 divide-x divide-gray-300">
            <button type="button" id="th_datum" class="p-3 text-left hover:bg-gray-300 transition flex justify-between items-center outline-none cursor-pointer select-none gap-1">
                <span class="block md:hidden">Datum</span>
                <span class="hidden md:block">Datum / Uhrzeit</span>
                <span class="sort-icon text-xs text-gray-600"></span>
            </button>
            <button type="button" id="th_name" class="p-3 text-left hover:bg-gray-300 transition flex justify-between items-center outline-none cursor-pointer select-none gap-2">
                <span>Name</span><span class="sort-icon text-xs text-gray-600"></span>
            </button>
            <button type="button" id="th_material" class="p-3 text-left hover:bg-gray-300 transition flex justify-between items-center outline-none cursor-pointer select-none gap-2">
                <span>Material</span><span class="sort-icon text-xs text-gray-600"></span>
            </button>
        </div>

        <div id="clv_liste" class="border-x border-b border-gray-300 rounded-b-md max-h-96 overflow-y-auto bg-white divide-y divide-gray-300">
            <?php foreach ($eintraege as $index => $eintrag): 
                $rowId = $eintrag['id'] ?? (string)$index;
            ?>
                <div class="clv-item grid grid-cols-[minmax(90px,110px)_1fr_1.5fr] md:grid-cols-[170px_1fr_1.5fr] hover:bg-gray-50 cursor-pointer transition divide-x divide-gray-300" data-id="<?php echo htmlspecialchars($rowId); ?>">
                    <div class="p-3 text-gray-600 font-mono text-xs md:text-sm clv-datum md:whitespace-nowrap break-all md:break-normal flex items-center"><?php echo htmlspecialchars($eintrag['datum'] ?? '-'); ?></div>
                    <div class="p-3 font-medium text-gray-900 text-xs md:text-sm clv-name break-words flex items-center"><?php echo htmlspecialchars($eintrag['name']); ?></div>
                    <div class="p-3 text-gray-600 text-xs md:text-sm clv-material break-words flex items-center"><?php echo htmlspecialchars($eintrag['material']); ?></div>
                </div>
            <?php endforeach; ?>
        </div>
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
        const clvListe = document.getElementById('clv_liste');
        const scannerModal = document.getElementById('scanner_modal');
        const btnCloseScanner = document.getElementById('btn_close_scanner');

        let html5QrcodeScanner = null;
        let currentTargetInput = null; 
        let aktuelleSortierung = { spalte: null, aufsteigend: true };
        const beepSound = new Audio('beep.ogg');

        txtMaterial.focus();

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

        // Hinzufügen
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
                const newItem = document.createElement('div');
                newItem.className = 'clv-item grid grid-cols-[minmax(90px,110px)_1fr_1.5fr] md:grid-cols-[170px_1fr_1.5fr] hover:bg-gray-50 cursor-pointer transition divide-x divide-gray-300';
                newItem.setAttribute('data-id', result.eintrag.id);
                newItem.innerHTML = `
                    <div class="p-3 text-gray-600 font-mono text-xs md:text-sm clv-datum md:whitespace-nowrap break-all md:break-normal flex items-center">${escapeHtml(result.eintrag.datum)}</div>
                    <div class="p-3 font-medium text-gray-900 text-xs md:text-sm clv-name break-words flex items-center">${escapeHtml(result.eintrag.name)}</div>
                    <div class="p-3 text-gray-600 text-xs md:text-sm clv-material break-words flex items-center">${escapeHtml(result.eintrag.material)}</div>
                `;
                clvListe.appendChild(newItem);

                txtMaterial.value = "";
                txtName.value = "";
                txtMaterial.focus();
                
                txtSuche.value = "";
                txtSuche.dispatchEvent(new Event('input'));

                if (aktuelleSortierung.spalte) {
                    sortiereListe(aktuelleSortierung.spalte, aktuelleSortierung.aufsteigend);
                }
            }
        });

        // Absolut klicksichere Lösch-Erkennung über den Event-Pfad (Composed Path)
        clvListe.addEventListener('click', async (e) => {
            const path = e.composedPath() || e.path;
            let targetItem = null;

            for (let element of path) {
                if (element.classList && element.classList.contains('clv-item')) {
                    targetItem = element;
                    break;
                }
            }

            if (!targetItem) return;

            const id = targetItem.getAttribute('data-id');
            if (!id) return;

            if (confirm("Soll der ausgewählte Eintrag gelöscht werden?")) {
                const response = await fetch('index.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ action: 'delete', id: id })
                });

                const result = await response.json();
                if (result.success) {
                    targetItem.remove();
                }
            }
        });

        // Live-Suche
        txtSuche.addEventListener('input', () => {
            const suchBegriff = txtSuche.value.trim().toLowerCase();
            const items = document.querySelectorAll('.clv-item');

            items.forEach(item => item.classList.remove('highlight'));

            if (suchBegriff.length < 3) {
                if (items.length > 0) clvListe.scrollTop = 0;
                return;
            }

            const suchTeile = suchBegriff.split(' ').filter(p => p !== '');
            let ersterTreffer = null;

            items.forEach(item => {
                const datumText = item.querySelector('.clv-datum').textContent.toLowerCase();
                const nameText = item.querySelector('.clv-name').textContent.toLowerCase();
                const materialText = item.querySelector('.clv-material').textContent.toLowerCase();
                const zeilenInhaltGesamt = `${datumText} ${nameText} ${materialText}`;

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
            } else {
                clvListe.scrollTop = 0;
            }
        });

        // Sortierung
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

        // Scanner Engine
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