<?php
$settingsPath = '/home/pi/Desktop/settings.json';
$schedulePath = '/var/www/html/schedule.json';

$message = '';
$messageClass = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $category = trim($_POST['category'] ?? '');
    $hexColor = strtoupper(ltrim(trim($_POST['color'] ?? ''), '#'));

    if (!empty($category) && preg_match('/^[A-F0-9]{6}$/', $hexColor)) {
        $raw = @file_get_contents($settingsPath);

        if ($raw !== false) {
            // Locate the show_type_colors object body
            $pattern = '/("show_type_colors"\s*:\s*\{)([^}]*?)(\})/s';

            if (preg_match($pattern, $raw, $matches)) {
                $colorsBlock = $matches[2];
                $escapedCat = preg_quote($category, '/');
                $keyPattern = '/"' . $escapedCat . '"\s*:\s*"[A-Fa-f0-9]{6}"/';

                if (preg_match($keyPattern, $colorsBlock)) {
                    // Update existing category color
                    $updatedBlock = preg_replace($keyPattern, '"' . addcslashes($category, '"\\') . '": "' . $hexColor . '"', $colorsBlock);
                } else {
                    // Append new category while maintaining single-line style
                    $trimmed = rtrim($colorsBlock);
                    $hasEntries = preg_match('/"[^"]+"\s*:/', $trimmed);
                    $separator = $hasEntries ? ', ' : ' ';
                    $updatedBlock = $trimmed . $separator . '"' . addcslashes($category, '"\\') . '": "' . $hexColor . '" ';
                }

                $newRaw = preg_replace($pattern, '${1}' . $updatedBlock . '${3}', $raw, 1);

                if (@file_put_contents($settingsPath, $newRaw) !== false) {
                    $message = "Updated '{$category}' to #{$hexColor}";
                    $messageClass = 'success';
                } else {
                    $message = "Error: Unable to write to {$settingsPath}. Check file permissions.";
                    $messageClass = 'error';
                }
            } else {
                $message = 'Error: Could not locate "show_type_colors" block in settings.json.';
                $messageClass = 'error';
            }
        } else {
            $message = "Error: Unable to read {$settingsPath}.";
            $messageClass = 'error';
        }
    } else {
        $message = 'Error: Invalid category or hex color format.';
        $messageClass = 'error';
    }
}

// Read current data
$categories = [];
if (file_exists($schedulePath)) {
    $scheduleData = json_decode(file_get_contents($schedulePath), true);
    if (is_array($scheduleData)) {
        $categories = array_keys($scheduleData);
    }
}

$colors = [];
if (file_exists($settingsPath)) {
    $settingsData = json_decode(file_get_contents($settingsPath), true);
    $colors = $settingsData['web-ui']['show_type_colors'] ?? [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Schedule Color Picker</title>
    <style>
        body { font-family: sans-serif; background: #1a1a1a; color: #eee; padding: 2rem; }
        .container { max-width: 480px; margin: auto; background: #2a2a2a; padding: 1.5rem; border-radius: 6px; }
        .form-group { margin-bottom: 1rem; }
        label { display: block; margin-bottom: 0.5rem; font-weight: bold; }
        select, button { width: 100%; padding: 0.6rem; border-radius: 4px; border: 1px solid #444; background: #1f1f1f; color: #fff; }
        .color-row { display: flex; align-items: center; gap: 0.75rem; }
        input[type="color"] { width: 60px; height: 40px; border: none; cursor: pointer; background: transparent; }
        button { cursor: pointer; background: #0078d4; border-color: #0078d4; font-size: 1rem; font-weight: bold; margin-top: 0.5rem; }
        button:hover { background: #005a9e; }
        .notice { padding: 0.75rem; border-radius: 4px; margin-bottom: 1rem; }
        .notice.success { background: #1b4721; color: #85e89d; }
        .notice.error { background: #5a1e1e; color: #ffa1a1; }
    </style>
</head>
<body>
<div class="container">
    <h2>Schedule Colors</h2>

    <?php if (!empty($message)): ?>
        <div class="notice <?= $messageClass ?>"><?= htmlspecialchars($message) ?></div>
    <?php endif; ?>

    <form method="POST">
        <div class="form-group">
            <label for="category">Category</label>
            <select name="category" id="category" onchange="updatePicker(this.value)">
                <?php foreach ($categories as $cat): ?>
                    <option value="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="form-group">
            <label for="color">Color</label>
            <div class="color-row">
                <input type="color" id="color" name="color" value="#000000">
                <span id="hex-display">#000000</span>
            </div>
        </div>

        <button type="submit">Save Color</button>
    </form>
</div>

<script>
    const colorMap = <?= json_encode($colors) ?>;
    const categorySelect = document.getElementById('category');
    const colorInput = document.getElementById('color');
    const hexDisplay = document.getElementById('hex-display');

    function updatePicker(selectedCategory) {
        let hex = colorMap[selectedCategory] || 'FFFFFF';
        if (!hex.startsWith('#')) {
            hex = '#' + hex;
        }
        colorInput.value = hex;
        hexDisplay.textContent = hex.toUpperCase();
    }

    colorInput.addEventListener('input', (e) => {
        hexDisplay.textContent = e.target.value.toUpperCase();
    });

    if (categorySelect.value) {
        updatePicker(categorySelect.value);
    }
</script>
</body>
</html>