<?php

/*
	Updated: 2026-10
	Name: settings.php
*/

function validateJsonReferences(string $jsonString): array {
    $data = json_decode($jsonString, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return ['valid' => false, 'errors' => []];
    }

    $errors = [];
    $vars =$data['vars'] ?? [];

    $getPositionData = function($search, $content) {$pos = strpos($content, '"' . $search . '"'); 
        if ($pos === false) return null;
        
        $targetPos =$pos + 1;
        $before = substr($content, 0,$targetPos);
        $line = substr_count($before, "\n") + 1;
        
        $lastNewline = strrpos($before, "\n");
        $column = ($lastNewline === false) ? ($targetPos + 1) : ($targetPos -$lastNewline);
        
        return [
            'line' => $line,
            'col' => $column,
            'offset' => $targetPos,
            'length' => strlen($search)
        ];
    };

    $checkRefs = function($item) use (&$checkRefs, &$errors,$vars, $jsonString,$getPositionData) {
		if (is_array($item)) {
			foreach ($item as$value) {
				$checkRefs($value);
			}
		} elseif (is_string($item) && strpos($item, '$ref/') === 0) {
			if (preg_match('/%[^%]+%/', $item)) {
				return; 
			}

			$refPath = substr($item, 5);
			$parts = explode('/',$refPath);
			$current =$vars;
			$missing = false;
			$traversed = "vars";

			foreach ($parts as$part) {
				if (!isset($current[$part])) {$missing = true;
					break;
				}
				$current = $current[$part];
				$traversed .= " > " . $part;
			}

			if ($missing) {$errors[] = [
					'label' => "Reference '{$item}' not found in [{$traversed}]",
					'pos' => $getPositionData($item,$jsonString)
				];
			}
		}
	};

    if (isset($data['times'])) $checkRefs($data['times'], 'times');
    if (isset($data['commercial_times'])) $checkRefs($data['commercial_times'], 'commercial_times');

    return ['valid' => empty($errors), 'errors' =>$errors];
}

function json_error_to_string($id) {
    switch ($id) {
        case JSON_ERROR_NONE: return "No errors";
        case JSON_ERROR_DEPTH: return "Maximum stack depth exceeded";
        case JSON_ERROR_STATE_MISMATCH: return "Invalid or malformed JSON";
        case JSON_ERROR_CTRL_CHAR: return "Control character error";
        case JSON_ERROR_SYNTAX: return "Syntax error";
        case JSON_ERROR_UTF8: return "Malformed UTF-8 characters";
        default: return "Unknown error";
    }
}

function json_validator($data=NULL) {
    if (!empty($data)) {
        @json_decode($data);
        return [(json_last_error() === JSON_ERROR_NONE), json_error_to_string(json_last_error())];
    }
    return [false, "Empty data"];
}

$settings_file = "/home/pi/Desktop/settings.json";
$backup_dir = dirname($settings_file);
$base_name = basename($settings_file);
$is_writable = is_writable($settings_file);

// Helper: create rotating backup
function create_backup($settings_file, $backup_dir,$base_name) {
    if (file_exists($settings_file)) {$timestamp = date('Ymd_His');
        $backup_file =$backup_dir . '/' . $base_name . '.bak_' .$timestamp;
        copy($settings_file,$backup_file);

        $backups = glob($backup_dir . '/' .$base_name . '.bak_*');
        usort($backups, function($a,$b) { return filemtime($b) - filemtime($a); });
        if (count($backups) > 10) {
            foreach (array_slice($backups, 10) as $old) unlink($old);
        }
    }
}

// 1. Export settings.json
if (isset($_GET['action']) &&$_GET['action'] === 'export') {
    if (file_exists($settings_file)) {
        header('Content-Description: File Transfer');
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="settings_' . date('Ymd_His') . '.json"');
        header('Expires: 0');
        header('Cache-Control: must-revalidate');
        header('Pragma: public');
        header('Content-Length: ' . filesize($settings_file));
        readfile($settings_file);
        exit;
    } else {
        die("Error: settings.json does not exist to export.");
    }
}

// 2. Restore from existing server backup
if (isset($_POST["restore_server_backup"]) && !empty($_POST["backup_filename"])) {
    $requested_file = basename($_POST["backup_filename"]);
    $full_backup_path = $backup_dir . '/' .$requested_file;

    if (file_exists($full_backup_path) && strpos($requested_file,$base_name . '.bak_') === 0) {
        $content = file_get_contents($full_backup_path);
        $val = json_validator($content);
        if ($val[0]) {
            create_backup($settings_file, $backup_dir,$base_name);
            file_put_contents($settings_file,$content);
            header("Location: settings.php?msg=restored");
            exit;
        } else {
            die("Backup corrupted: " . $val[1]);
        }
    } else {
        die("Invalid backup target.");
    }
}

// 3. Upload & Restore custom backup
if (isset($_POST["upload_backup"]) && isset($_FILES["backup_file"])) {
    if ($_FILES["backup_file"]["error"] === UPLOAD_ERR_OK) {
        $uploaded_content = file_get_contents($_FILES["backup_file"]["tmp_name"]);
        $val = json_validator($uploaded_content);
        if ($val[0]) {
            create_backup($settings_file, $backup_dir,$base_name);
            file_put_contents($settings_file,$uploaded_content);
            header("Location: settings.php?msg=uploaded");
            exit;
        } else {
            die('<div style="background:#5a1d1d;color:#ffbaba;padding:20px;font-family:sans-serif;">
                    <h3>Invalid JSON File</h3>
                    <p>'.$val[1].'</p>
                    <button onclick="window.history.back()" style="padding:10px;cursor:pointer;">Go Back</button>
                 </div>');
        }
    } else {
        die("Upload failed with error code: " . $_FILES["backup_file"]["error"]);
    }
}

// 4. Handle Save
if (isset($_POST["settings"]) && isset($_POST["save"])) {
    $raw_json =$_POST["settings"];
    $json_valid = json_validator($raw_json);
    
    if (!$json_valid[0]) {
        die('<div style="background:#5a1d1d;color:#ffbaba;margin:10px;padding:20px;border-radius:4px;font-family:sans-serif;">
                <h3>JSON Syntax Error</h3>
                <p>'.$json_valid[1].'</p>
                <button onclick="window.history.back()" style="padding:10px;cursor:pointer;">Go Back and Fix</button>
             </div>');
    }

    create_backup($settings_file, $backup_dir,$base_name);

    $temp_file =$settings_file . '.tmp';
    if (file_put_contents($temp_file,$raw_json) !== false) {
        rename($temp_file,$settings_file);
        header("Location: settings.php?saved=yes");
        exit();
    } else {
        die("Fatal Error: Could not write to disk.");
    }
}

// Gather server backups for UI
$server_backups = glob($backup_dir . '/' .$base_name . '.bak_*');
usort($server_backups, function($a,$b) { return filemtime($b) - filemtime($a); });

$json_data = file_exists($settings_file) ? file_get_contents($settings_file) : "{}";
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Settings Editor</title>
    <style>
        body { background-color: #1a1a1a; color: #eee; font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; margin: 20px; line-height: 1.4; }
        .error-box { background: #4e1414; color: #ffbaba; border-left: 5px solid #ff5c5c; margin-bottom: 20px; padding: 15px; border-radius: 2px; }
        .warning-box { background: #4e3e14; color: #ffebba; border-left: 5px solid #ffcc5c; margin-bottom: 20px; padding: 15px; border-radius: 2px; }
        
        #status-bar { 
            background: #2a2a2a; color: #888; padding: 6px 15px; font-size: 11px; width: 100%; 
            box-sizing: border-box; border: 1px solid #333; border-bottom: none;
            display: flex; justify-content: space-between; font-family: monospace;
        }
        
        #editor {
            background-color: #121212; color: #d4d4d4; border: 1px solid #333;
            font-family: 'Consolas', 'Monaco', 'Courier New', monospace; font-size: 13px; line-height: 1.6;
            padding: 15px; outline: none; width: 100%; height: 73vh; box-sizing: border-box;
            resize: none; caret-color: #007acc;
        }

        .button-container { display: flex; align-items: center; gap: 8px; margin-bottom: 15px; flex-wrap: wrap; }
        .btn { 
            padding: 8px 14px; border: none; border-radius: 3px; font-weight: 600; cursor: pointer; 
            font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; transition: background 0.2s;
            text-decoration: none; color: inherit; display: inline-block;
        }
        .btn:disabled { opacity: 0.4; cursor: not-allowed; }
        .btn-save { background-color: #007acc; color: white; }
        .btn-save:hover:not(:disabled) { background-color: #0062a3; }
        .btn-action { background-color: #3e3e3e; color: #ccc; }
        .btn-action:hover { background-color: #4e4e4e; }
        .btn-danger { background-color: #a63434; color: white; padding: 4px 8px; font-size: 11px; }
        .btn-danger:hover { background-color: #c93b3b; }

        #saved-span { color: #4ec9b0; font-size: 13px; font-weight: bold; animation: fadeOut 3s forwards; }
        @keyframes fadeOut { 0% { opacity: 1; } 70% { opacity: 1; } 100% { opacity: 0; } }
        
        ul { margin: 5px 0 0 20px; padding: 0; }
        li { margin-bottom: 5px; }
        .error-link { 
            color: #ff5c5c; text-decoration: underline; cursor: pointer; font-weight: bold;
            font-family: monospace; margin-right: 8px;
        }
        .error-link:hover { color: #ff9e9e; }

        /* Modal styling */
        .modal {
            display: none; position: fixed; z-index: 100; left: 0; top: 0; width: 100%; height: 100%;
            background-color: rgba(0,0,0,0.75);
        }
        .modal-content {
            background-color: #242424; margin: 6% auto; padding: 25px; border: 1px solid #444;
            width: 720px; max-width: 90%; border-radius: 4px; box-shadow: 0 4px 20px rgba(0,0,0,0.6);
        }
        .modal-header { display: flex; justify-content: space-between; align-items: center; border-bottom: 1px solid #3a3a3a; padding-bottom: 10px; margin-bottom: 20px; }
        .modal-header h3 { margin: 0; font-size: 18px; }
        .modal-close { cursor: pointer; font-size: 20px; font-weight: bold; color: #aaa; }
        .modal-close:hover { color: #fff; }
        .backup-table { width: 100%; border-collapse: collapse; font-size: 12px; margin-bottom: 20px; }
        .backup-table th, .backup-table td { padding: 9px; text-align: left; border-bottom: 1px solid #333; }
        .backup-table th { background-color: #1a1a1a; color: #aaa; }
        .upload-section { background: #1b1b1b; padding: 15px; border-radius: 4px; border: 1px dashed #444; margin-top: 10px; }
    </style>
</head>
<body>

    <?php if (!$is_writable): ?>
        <div class="warning-box"><strong>FILE SYSTEM LOCK:</strong> PHP does not have permission to write to <code>settings.json</code>. Changes cannot be saved.</div>
    <?php endif; ?>

    <?php 
    $val = json_validator($json_data);
    if (!$val[0]) {
        echo '<div class="error-box"><strong>JSON Syntax Error:</strong> '.$val[1].'</div>';
    } else {
        $ref = validateJsonReferences($json_data);
        if (!$ref['valid']) {
            echo '<div class="error-box"><strong>Reference Validation Failed:</strong><ul>';
            foreach ($ref['errors'] as$err) {
                $pos =$err['pos'];
				if ($pos) {
					echo "<li><span class='error-link' onclick='goToPos({$pos['offset']}, {$pos['length']})'>[Line {$pos['line']}, Col {$pos['col']}]</span> {$err['label']}</li>";
				} else {
					echo "<li>{$err['label']}</li>";
				}
            }
            echo '</ul></div>';
        }
    }
    ?>

    <form method="POST" id="settingsForm">
        <div class="button-container">
            <button type="submit" name="save" class="btn btn-save" <?php echo !$is_writable ? 'disabled' : ''; ?>>Save Changes</button>
            <button type="button" class="btn btn-action" onclick="openRestoreModal()">Restore / Backups</button>
            <a class="btn btn-action" href="settings.php?action=export">Export JSON</a>
            
            <?php if(isset($_GET["saved"])): ?>
                <span id="saved-span">Changes saved.</span>
            <?php elseif(isset($_GET["msg"]) && $_GET["msg"] === 'restored'): ?>
                <span id="saved-span">Backup successfully restored.</span>
            <?php elseif(isset($_GET["msg"]) && $_GET["msg"] === 'uploaded'): ?>
                <span id="saved-span">External JSON uploaded and applied.</span>
            <?php endif; ?>
            
            <div style="flex-grow: 1;"></div>
            
            <a class="btn btn-action" href="settings-doc.html" target="_blank">Documentation</a>
            <button type="button" class="btn btn-action" onclick="testSettings()">Live Test</button>
        </div>

        <div id="status-bar">
        	<span id="cursor-info">Line 1, Col 1</span>    
			<span id="file-info"><?php echo $settings_file; ?></span>
        </div>
        <textarea id="editor" name="settings" spellcheck="false" wrap="off"><?php echo htmlspecialchars($json_data); ?></textarea>
    </form>

    <div style="margin-top: 15px; font-size: 11px; color: #666; font-style: italic;">
        * Rotating backups are preserved automatically (up to 10 stored locally).
    </div>

    <!-- Backup & Restore Modal -->
    <div id="restoreModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Backups & Recovery</h3>
                <span class="modal-close" onclick="closeRestoreModal()">&times;</span>
            </div>

            <div style="margin-bottom: 12px; font-size: 13px; font-weight: bold; color: #ccc;">Stored Local Backups:</div>
            <?php if (empty($server_backups)): ?>
                <p style="font-size: 12px; color: #777;">No automatic backups found on disk.</p>
            <?php else: ?>
                <table class="backup-table">
                    <thead>
                        <tr>
                            <th>Date & Time Modified</th>
                            <th>Original Filename</th>
                            <th>Size</th>
                            <th style="text-align: right;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($server_backups as $file):$mtime = filemtime($file);$formatted_date = date("M j, Y — H:i:s", $mtime);
                            $filename = basename($file);
                            $size = round(filesize($file) / 1024, 2) . ' KB';
                        ?>
                        <tr>
                            <td><strong><?php echo $formatted_date; ?></strong></td>
                            <td style="font-family: monospace; color: #999;"><?php echo htmlspecialchars($filename); ?></td>
                            <td><?php echo $size; ?></td>
                            <td style="text-align: right;">
                                <form method="POST" style="display:inline;" onsubmit="return confirm('Restore this version? A backup of your current file will be generated.');">
                                    <input type="hidden" name="backup_filename" value="<?php echo htmlspecialchars($filename); ?>">
                                    <button type="submit" name="restore_server_backup" class="btn btn-danger">Restore</button>
                                </form>
                            </td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>

            <div class="upload-section">
                <div style="font-size: 13px; font-weight: bold; margin-bottom: 8px;">Upload Local Backup File (.json)</div>
                <form method="POST" enctype="multipart/form-data" style="display: flex; gap: 10px; align-items: center;">
                    <input type="file" name="backup_file" accept=".json,text/plain" required style="font-size: 12px;">
                    <button type="submit" name="upload_backup" class="btn btn-action" style="padding: 6px 12px;" onclick="return confirm('Upload and apply this JSON file?');">Upload & Apply</button>
                </form>
            </div>
        </div>
    </div>

<script>
const editor = document.getElementById("editor");
const cursorInfo = document.getElementById("cursor-info");
const modal = document.getElementById("restoreModal");

function updateCaret() {
    const textBefore = editor.value.substring(0, editor.selectionStart);
    const lines = textBefore.split("\n");
    const currentLine = lines.length;
    const currentCol = lines[lines.length - 1].length + 1;
    cursorInfo.innerText = `Line ${currentLine}, Col ${currentCol}`;
}

function goToPos(offset, length) {
    editor.focus();
    editor.setSelectionRange(offset, offset + length);
    const lineHeight = 20.8; 
    const textBefore = editor.value.substring(0, offset);
    const lineNum = textBefore.split("\n").length;
    editor.scrollTop = (lineNum - 5) * lineHeight; 
    updateCaret();
}

function openRestoreModal() { modal.style.display = "block"; }
function closeRestoreModal() { modal.style.display = "none"; }
window.onclick = function(e) { if (e.target === modal) closeRestoreModal(); }

function testSettings() {
    if(confirm('Warning: This triggers a live station test. Continue?')) {
        location.href='index.php?test_settings=1&t=' + Date.now();
    }
}

editor.addEventListener('keyup', updateCaret);
editor.addEventListener('click', updateCaret);
editor.addEventListener('input', updateCaret);
editor.addEventListener('keydown', function(e) {
    if(e.key === 'Tab') {
        e.preventDefault();
        const start = this.selectionStart;
        const end = this.selectionEnd;
        this.value = this.value.substring(0, start) + "\t" + this.value.substring(end);
        this.selectionStart = this.selectionEnd = start + 1;
        updateCaret();
    }
});

window.onload = updateCaret;
</script>
</body>
</html>