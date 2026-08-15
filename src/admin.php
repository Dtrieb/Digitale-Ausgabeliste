<?php
session_start();

// Passwort festlegen (Hier anpassen)
define('ADMIN_PASSWORD', 'passwort123');

$materialFile = __DIR__ . '/material.json';
$dataDir = __DIR__ . '/data';

// Login-Abwicklung
if (isset($_POST['action']) && $_POST['action'] === 'login') {
    if (isset($_POST['password']) && $_POST['password'] === ADMIN_PASSWORD) {
        $_SESSION['admin_logged_in'] = true;
    } else {
        $loginError = "Falsches Passwort!";
    }
}

// Logout-Abwicklung
if (isset($_GET['action']) && $_GET['action'] === 'logout') {
    unset($_SESSION['admin_logged_in']);
    header("Location: admin.php");
    exit;
}

$isLoggedIn = !empty($_SESSION['admin_logged_in']);

// JSON AJAX POST-Aktionen (nur verarbeiten, wenn Content-Type JSON ist)
$contentType = $_SERVER['CONTENT_TYPE'] ?? '';
if ($isLoggedIn && $_SERVER['REQUEST_METHOD'] === 'POST' && strpos($contentType, 'application/json') !== false) {
    header('Content-Type: application/json');
    $input = json_decode(file_get_contents('php://input'), true);

    if (isset($input['action'])) {
        
        // --- MATERIAL-AKTIONEN ---
        if ($input['action'] === 'add_material') {
            $neuesMaterial = trim($input['material'] ?? '');
            if ($neuesMaterial !== '') {
                $materialien = file_exists($materialFile) ? json_decode(file_get_contents($materialFile), true) ?? [] : [];
                if (!in_array($neuesMaterial, $materialien)) {
                    $materialien[] = $neuesMaterial; // Unten anfügen
                    file_put_contents($materialFile, json_encode(array_values($materialien), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                }
                echo json_encode(['success' => true]);
                exit;
            }
        }

        if ($input['action'] === 'edit_material') {
            $index = $input['index'] ?? null;
            $neuerWert = trim($input['wert'] ?? '');
            $materialien = file_exists($materialFile) ? json_decode(file_get_contents($materialFile), true) ?? [] : [];

            if ($index !== null && isset($materialien[$index]) && $neuerWert !== '') {
                $materialien[$index] = $neuerWert;
                file_put_contents($materialFile, json_encode(array_values($materialien), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                echo json_encode(['success' => true]);
                exit;
            }
        }

        if ($input['action'] === 'delete_material') {
            $index = $input['index'] ?? null;
            $materialien = file_exists($materialFile) ? json_decode(file_get_contents($materialFile), true) ?? [] : [];

            if ($index !== null && isset($materialien[$index])) {
                array_splice($materialien, $index, 1);
                file_put_contents($materialFile, json_encode(array_values($materialien), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                echo json_encode(['success' => true]);
                exit;
            }
        }

        if ($input['action'] === 'reorder_material') {
            $neueReihenfolge = $input['materialien'] ?? [];
            if (is_array($neueReihenfolge)) {
                file_put_contents($materialFile, json_encode(array_values($neueReihenfolge), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                echo json_encode(['success' => true]);
                exit;
            }
        }

        // --- DATA-DATEIEN LÖSCHEN ---
        if ($input['action'] === 'delete_data_file') {
            $filename = basename($input['filename'] ?? '');
            $filePath = $dataDir . '/' . $filename;

            // Sicherheit: Nur .json Dateien im /data Ordner löschen
            if ($filename !== '' && file_exists($filePath) && pathinfo($filePath, PATHINFO_EXTENSION) === 'json') {
                unlink($filePath);
                echo json_encode(['success' => true]);
                exit;
            }
        }
    }
    
    echo json_encode(['success' => false]);
    exit;
}

// Material-Liste für Anzeige laden
$materialien = file_exists($materialFile) ? json_decode(file_get_contents($materialFile), true) ?? [] : [];

// Data-Dateien für Anzeige laden
$dataFiles = [];
if (is_dir($dataDir)) {
    $scanned = scandir($dataDir);
    foreach ($scanned as $f) {
        if ($f !== '.' && $f !== '..' && pathinfo($f, PATHINFO_EXTENSION) === 'json') {
            $dataFiles[] = [
                'name' => $f,
                'size' => filesize($dataDir . '/' . $f),
                'mtime' => date('d.m.Y H:i', filemtime($dataDir . '/' . $f))
            ];
        }
    }
}
?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin - Ausgabeliste</title>
<script src="https://cdn.jsdelivr.net/npm/@tailwindcss/browser@4"></script>
<script src="https://cdn.jsdelivr.net/npm/sortablejs@1.15.2/Sortable.min.js"></script>
<style>
.sortable-ghost {
    opacity: 0.4;
    background-color: #e0f2fe !important;
}
</style>
</head>
<body class="bg-gray-100 min-h-screen p-4 md:p-8 font-sans">

<div class="max-w-4xl mx-auto bg-white p-6 rounded-xl shadow-md">

<?php if (!$isLoggedIn): ?>
    <!-- Login-Formular -->
    <h1 class="text-2xl font-bold mb-6 text-gray-800 text-center">Admin Bereich Login</h1>
    <?php if (isset($loginError)): ?>
        <div class="mb-4 p-3 bg-red-100 text-red-700 rounded-md text-sm text-center"><?php echo $loginError; ?></div>
    <?php endif; ?>
    <form method="POST" action="admin.php" class="max-w-sm mx-auto space-y-4">
        <input type="hidden" name="action" value="login">
        <div>
            <label class="block text-sm font-medium text-gray-700 mb-1">Passwort</label>
            <input type="password" name="password" required class="w-full border border-gray-300 rounded-md p-2 focus:ring-2 focus:ring-blue-500 focus:outline-none">
        </div>
        <button type="submit" class="w-full bg-blue-600 text-white font-medium p-2 rounded-md hover:bg-blue-700 transition cursor-pointer">Einloggen</button>
    </form>

<?php else: ?>
    <!-- Admin Dashboard -->
    <div class="flex justify-between items-center mb-6 border-b pb-4">
        <h1 class="text-2xl font-bold text-gray-800">Admin-Verwaltung</h1>
        <a href="admin.php?action=logout" class="px-3 py-1 bg-gray-200 hover:bg-gray-300 text-gray-700 rounded-md text-sm font-medium transition">Abmelden</a>
    </div>

    <!-- Tab Navigation -->
    <div class="flex border-b border-gray-200 mb-6">
        <button id="tab_btn_material" onclick="switchTab('material')" class="py-2 px-4 font-medium text-sm border-b-2 border-blue-600 text-blue-600 cursor-pointer focus:outline-none">
            📦 Materialliste (material.json)
        </button>
        <button id="tab_btn_files" onclick="switchTab('files')" class="py-2 px-4 font-medium text-sm border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 cursor-pointer focus:outline-none">
            📁 Dateiverwaltung (/data)
        </button>
    </div>

    <!-- Tab 1: Material-Verwaltung -->
    <section id="tab_content_material">
        <h2 class="text-xl font-semibold mb-1 text-gray-700">Materialliste verwalten</h2>
        <p class="text-xs text-gray-500 mb-4">Reihenfolge per Drag & Drop verschieben. Neues Material wird unten angefügt.</p>
        
        <div class="flex gap-2 mb-4">
            <input type="text" id="txt_new_material" placeholder="Neues Material..." class="flex-1 border border-gray-300 rounded-md p-2 focus:ring-2 focus:ring-blue-500 focus:outline-none">
            <button type="button" id="btn_add_material" class="px-4 bg-blue-600 text-white rounded-md hover:bg-blue-700 transition font-medium cursor-pointer">Hinzufügen</button>
        </div>

        <div class="border border-gray-200 rounded-md overflow-hidden">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-gray-50 border-b border-gray-200 text-sm text-gray-600">
                        <th class="p-3 w-[40px]"></th>
                        <th class="p-3">Bezeichnung</th>
                        <th class="p-3 text-right w-[160px]">Aktionen</th>
                    </tr>
                </thead>
                <tbody id="material_sortable">
                    <?php if (empty($materialien)): ?>
                        <tr id="empty_row"><td colspan="3" class="p-3 text-gray-500 text-sm">Keine Einträge vorhanden.</td></tr>
                    <?php else: ?>
                        <?php foreach ($materialien as $idx => $mat): ?>
                            <tr class="border-b border-gray-100 hover:bg-gray-50 material-item" data-value="<?php echo htmlspecialchars($mat); ?>">
                                <td class="p-3 text-gray-400 cursor-grab drag-handle text-center select-none">☰</td>
                                <td class="p-3 text-sm text-gray-800 font-medium mat-text"><?php echo htmlspecialchars($mat); ?></td>
                                <td class="p-3 text-right space-x-1">
                                    <button onclick="editMaterial(this)" class="text-blue-600 hover:text-blue-800 font-bold text-sm cursor-pointer">✏️ Bearbeiten</button>
                                    <button onclick="deleteMaterial(this)" class="text-red-600 hover:text-red-800 font-bold text-sm cursor-pointer ml-2">🗑️ Löschen</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>

    <!-- Tab 2: Dateiverwaltung -->
    <section id="tab_content_files" class="hidden">
        <h2 class="text-xl font-semibold mb-4 text-gray-700">Datendateien verwalten (/data)</h2>
        <div class="border border-gray-200 rounded-md overflow-hidden">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-gray-50 border-b border-gray-200 text-sm text-gray-600">
                        <th class="p-3">Dateiname</th>
                        <th class="p-3">Zuletzt geändert</th>
                        <th class="p-3 text-right w-[100px]">Aktion</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (empty($dataFiles)): ?>
                        <tr><td colspan="3" class="p-3 text-gray-500 text-sm">Keine JSON-Dateien im Verzeichnis /data gefunden.</td></tr>
                    <?php else: ?>
                        <?php foreach ($dataFiles as $file): ?>
                            <tr class="border-b border-gray-100 hover:bg-gray-50">
                                <td class="p-3 text-sm font-mono text-gray-800"><?php echo htmlspecialchars($file['name']); ?></td>
                                <td class="p-3 text-sm text-gray-500"><?php echo $file['mtime']; ?></td>
                                <td class="p-3 text-right">
                                    <button onclick="deleteDataFile('<?php echo addslashes(htmlspecialchars($file['name'])); ?>')" class="text-red-600 hover:text-red-800 font-bold text-sm cursor-pointer">🗑️ Löschen</button>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </section>
<?php endif; ?>

</div>

<script>
function switchTab(tabName) {
    const tabMaterial = document.getElementById('tab_content_material');
    const tabFiles = document.getElementById('tab_content_files');
    const btnMaterial = document.getElementById('tab_btn_material');
    const btnFiles = document.getElementById('tab_btn_files');

    if (tabName === 'files') {
        tabMaterial.classList.add('hidden');
        tabFiles.classList.remove('hidden');
        
        btnMaterial.className = "py-2 px-4 font-medium text-sm border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 cursor-pointer focus:outline-none";
        btnFiles.className = "py-2 px-4 font-medium text-sm border-b-2 border-blue-600 text-blue-600 cursor-pointer focus:outline-none";
        window.location.hash = 'files';
    } else {
        tabFiles.classList.add('hidden');
        tabMaterial.classList.remove('hidden');

        btnFiles.className = "py-2 px-4 font-medium text-sm border-b-2 border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 cursor-pointer focus:outline-none";
        btnMaterial.className = "py-2 px-4 font-medium text-sm border-b-2 border-blue-600 text-blue-600 cursor-pointer focus:outline-none";
        window.location.hash = 'material';
    }
}

document.addEventListener('DOMContentLoaded', () => {
    if (window.location.hash === '#files') {
        switchTab('files');
    }

    const el = document.getElementById('material_sortable');
    if (el) {
        Sortable.create(el, {
            handle: '.drag-handle',
            animation: 150,
            ghostClass: 'sortable-ghost',
            onEnd: async function () {
                await speichereReihenfolge();
            }
        });
    }
});

async function speichereReihenfolge() {
    const rows = document.querySelectorAll('.material-item');
    const liste = Array.from(rows).map(row => row.getAttribute('data-value'));

    await fetch('admin.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/json'},
        body: JSON.stringify({ action: 'reorder_material', materialien: liste })
    });
}

const txtNewMat = document.getElementById('txt_new_material');
if(txtNewMat) {
    txtNewMat.addEventListener('keydown', (e) => {
        if(e.key === 'Enter') {
            e.preventDefault();
            document.getElementById('btn_add_material').click();
        }
    });
}

const btnAddMat = document.getElementById('btn_add_material');
if(btnAddMat) {
    btnAddMat.addEventListener('click', async () => {
        const val = txtNewMat.value.trim();
        if(!val) return;
        const res = await fetch('admin.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ action: 'add_material', material: val })
        });
        const data = await res.json();
        if(data.success) location.reload();
    });
}

async function editMaterial(btn) {
    const row = btn.closest('.material-item');
    const alterWert = row.getAttribute('data-value');
    const neuerWert = prompt("Materialbezeichnung ändern:", alterWert);
    
    if(neuerWert !== null && neuerWert.trim() !== "" && neuerWert !== alterWert) {
        row.setAttribute('data-value', neuerWert.trim());
        row.querySelector('.mat-text').textContent = neuerWert.trim();
        await speichereReihenfolge();
    }
}

async function deleteMaterial(btn) {
    if(confirm("Möchtest du dieses Material wirklich aus der Liste entfernen?")) {
        const row = btn.closest('.material-item');
        row.remove();
        await speichereReihenfolge();
    }
}

async function deleteDataFile(filename) {
    if(confirm(`Möchtest du die Datei "${filename}" wirklich unwiderruflich löschen?`)) {
        const res = await fetch('admin.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ action: 'delete_data_file', filename: filename })
        });
        const data = await res.json();
        if(data.success) location.reload();
    }
}
</script>
</body>
</html>
