<?php

/*
	Updated: 2026-03
	Refactored with interactive error navigation and atomic writes.
	Name: settings.php
*/

function validateJsonReferences(string $jsonString): array {
    $data = json_decode($jsonString, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return ['valid' => false, 'errors' => []];
    }

    $errors = [];
    $vars = $data['vars'] ?? [];

    $getPositionData = function($search, $content) {
        $pos = strpos($content, '"' . $search . '"'); 
        if ($pos === false) return null;
        
        // Adjust pos to skip the leading quote for highlighting the actual text
        $targetPos = $pos + 1;
        $before = substr($content, 0, $targetPos);
        $line = substr_count($before, "\n") + 1;
        
        // Find the position of the last newline before this match to calculate column
        $lastNewline = strrpos($before, "\n");
        $column = ($lastNewline === false) ? ($targetPos + 1) : ($targetPos - $lastNewline);
        
        return [
            'line' => $line,
            'col' => $column,
            'offset' => $targetPos,
            'length' => strlen($search)
        ];
    };

    $checkRefs = function($item, $path) use (&$checkRefs, &$errors, $vars, $jsonString, $getPositionData) {
        if (is_array($item)) {
            foreach ($item as $key => $value) {
                $checkRefs($value, $path . " > " . $key);
            }
        } elseif (is_string($item) && strpos($item, '$ref/') === 0) {
            $refPath = str_replace('$ref/', '', $item);
            $parts = explode('/', $refPath);
            $current = $vars;
            $missing = false;
            $traversed = "vars";

            foreach ($parts as $part) {
                if (!isset($current[$part])) {
                    $missing = true;
                    break;
                }
                $current = $current[$part];
                $traversed .= " > " . $part;
            }

            if ($missing) {
                $pos = $getPositionData($item, $jsonString);
                $errors[] = [
                    'label' => "Reference '{$item}' not found in [{$traversed}]",
                    'pos' => $pos
                ];
            }
        }
    };

    if (isset($data['times'])) $checkRefs($data['times'], 'times');
    if (isset($data['commercial_times'])) $checkRefs($data['commercial_times'], 'commercial_times');

    return ['valid' => empty($errors), 'errors' => $errors];
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
$is_writable = is_writable($settings_file);

// Handle Save
if (isset($_POST["settings"]) && isset($_POST["save"])) {
    $raw_json = $_POST["settings"];
    $json_valid = json_validator($raw_json);
    
    if (!$json_valid[0]) {
        die('<div style="background:#5a1d1d;color:#ffbaba;margin:10px;padding:20px;border-radius:4px;font-family:sans-serif;">
                <h3>JSON Syntax Error</h3>
                <p>'.$json_valid[1].'</p>
                <button onclick="window.history.back()" style="padding:10px;cursor:pointer;">Go Back and Fix</button>
             </div>');
    }

    if (file_exists($settings_file)) {
        $backup_dir = dirname($settings_file);
        $base_name = basename($settings_file);
        $timestamp = date('Ymd_His');
        $backup_file = $backup_dir . '/' . $base_name . '.bak_' . $timestamp;
        copy($settings_file, $backup_file);

        $backups = glob($backup_dir . '/' . $base_name . '.bak_*');
        usort($backups, function($a, $b) { return filemtime($b) - filemtime($a); });
        if (count($backups) > 10) {
            foreach (array_slice($backups, 10) as $old) unlink($old);
        }
    }

    $temp_file = $settings_file . '.tmp';
    if (file_put_contents($temp_file, $raw_json) !== false) {
        rename($temp_file, $settings_file);
        header("Location: settings.php?saved=yes");
        exit();
    } else {
        die("Fatal Error: Could not write to disk.");
    }
}

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
            padding: 15px; outline: none; width: 100%; height: 75vh; box-sizing: border-box;
            resize: none; caret-color: #007acc;
        }

        .button-container { display: flex; align-items: center; gap: 10px; margin-bottom: 15px; flex-wrap: wrap; }
        .btn { 
            padding: 8px 14px; border: none; border-radius: 3px; font-weight: 600; cursor: pointer; 
            font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; transition: background 0.2s;
        }
        .btn:disabled { opacity: 0.4; cursor: not-allowed; }
        .btn-save { background-color: #007acc; color: white; }
        .btn-save:hover:not(:disabled) { background-color: #0062a3; }
        .btn-test { background-color: #3e3e3e; color: #ccc; text-decoration: none; display: inline-block; }
        .btn-test:hover { background-color: #4e4e4e; }
        .btn-format { background-color: #2d8a49; color: white; }
        .btn-format:hover { background-color: #236d39; }

        #saved-span { color: #4ec9b0; font-size: 13px; font-weight: bold; animation: fadeOut 3s forwards; }
        @keyframes fadeOut { 0% { opacity: 1; } 70% { opacity: 1; } 100% { opacity: 0; } }
        
        ul { margin: 5px 0 0 20px; padding: 0; }
        li { margin-bottom: 5px; }
        .error-link { 
            color: #ff5c5c; text-decoration: underline; cursor: pointer; font-weight: bold;
            font-family: monospace; margin-right: 8px;
        }
        .error-link:hover { color: #ff9e9e; }
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
            foreach ($ref['errors'] as $err) {
                $pos = $err['pos'];
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
            
            <?php if(isset($_GET["saved"])): ?>
                <span id="saved-span">Changes applied to station player.</span>
            <?php endif; ?>
            
            <div style="flex-grow: 1;"></div>
            
            <a class="btn btn-test" href="settings-doc.html" target="_blank">Documentation</a>
            <button type="button" class="btn btn-test" onclick="testSettings()">Live Test</button>
        </div>

        <div id="status-bar">
        	<span id="cursor-info">Line 1, Col 1</span>    
			<span id="file-info"><?php echo $settings_file; ?></span>
        </div>
        <textarea id="editor" name="settings" spellcheck="false" wrap="off"><?php echo htmlspecialchars($json_data); ?></textarea>
    </form>

    <div style="margin-top: 15px; font-size: 11px; color: #666; font-style: italic;">
        * Backups are automatically rotated (max 10). Integer numbers are preserved. Use the "Prettify" button to clean up formatting before saving.
    </div>

<script>
const editor = document.getElementById("editor");
const cursorInfo = document.getElementById("cursor-info");

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
    
    // Scroll editor to show selection if needed
    const lineHeight = 20.8; 
    const textBefore = editor.value.substring(0, offset);
    const lineNum = textBefore.split("\n").length;
    editor.scrollTop = (lineNum - 5) * lineHeight; 
    
    updateCaret();
}

function formatJson() {
    try {
        const obj = JSON.parse(editor.value);
        editor.value = JSON.stringify(obj, null, "\t"); 
        updateCaret();
    } catch (e) {
        alert("JSON is currently malformed. Please fix syntax errors before formatting.");
    }
}

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