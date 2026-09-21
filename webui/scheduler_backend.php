<?php
error_reporting(E_ALL);
ini_set("display_errors", 1);

require_once("settings.class.inc");

$settings_path = "/home/pi/Desktop/settings.json";
$Settings = new Settings();
$json_response = $Settings->load($settings_path);
$json_settings = $json_response[0] ?? [];

$channel_file = $json_settings["channels"]["file"] ?? '';
$show_type_colors = $json_settings["web-ui"]["show_type_colors"] ?? [];

function resolveSchedulePath($channel_file) {
	$chan = "";
	if ($channel_file && file_exists($channel_file)) {
		$c = trim((string)file_get_contents($channel_file));
		if ($c !== "" && $c !== "default") {
			$chan = "." . $c;
		}
	}
	return __DIR__ . DIRECTORY_SEPARATOR . "schedule" . $chan . ".json";
}

$schedule_file = resolveSchedulePath($channel_file);
$backup_file   = $schedule_file . ".bak";

// Handle AJAX: Save Show Category Color without touching file formatting
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_category_color') {
	header('Content-Type: application/json');
	$category = trim($_POST['category'] ?? '');
	$hexColor = strtoupper(ltrim(trim($_POST['color'] ?? ''), '#'));

	if (empty($category) || !preg_match('/^[A-F0-9]{6}$/', $hexColor)) {
		echo json_encode(["status" => "error", "message" => "Invalid category or color."]);
		exit;
	}

	$raw = @file_get_contents($settings_path);
	if ($raw === false) {
		echo json_encode(["status" => "error", "message" => "Unable to read settings.json."]);
		exit;
	}

	$pattern = '/("show_type_colors"\s*:\s*\{)([^}]*?)(\})/s';
	if (!preg_match($pattern, $raw, $matches)) {
		echo json_encode(["status" => "error", "message" => "Could not locate show_type_colors in settings.json."]);
		exit;
	}

	$colorsBlock = $matches[2];
	$escapedCat = preg_quote($category, '/');
	$keyPattern = '/"' . $escapedCat . '"\s*:\s*"[A-Fa-f0-9]{6}"/';

	if (preg_match($keyPattern, $colorsBlock)) {
		$updatedBlock = preg_replace($keyPattern, '"' . addcslashes($category, '"\\') . '": "' . $hexColor . '"', $colorsBlock);
	} else {
		$trimmed = rtrim($colorsBlock);
		$hasEntries = preg_match('/"[^"]+"\s*:/', $trimmed);
		$separator = $hasEntries ? ', ' : ' ';
		$updatedBlock = $trimmed . $separator . '"' . addcslashes($category, '"\\') . '": "' . $hexColor . '" ';
	}

	$newRaw = preg_replace($pattern, '${1}' . $updatedBlock . '${3}', $raw, 1);

	if (@file_put_contents($settings_path, $newRaw) !== false) {
		echo json_encode(["status" => "success", "message" => "Color updated to #{$hexColor}"]);
	} else {
		echo json_encode(["status" => "error", "message" => "Unable to write to settings.json."]);
	}
	exit;
}

// Handle AJAX Save Request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_schedule') {
	header('Content-Type: application/json');
	$raw = $_POST['data'] ?? '';
	$decoded = json_decode($raw, true);

	if (!is_array($decoded)) {
		echo json_encode(["status" => "error", "message" => "Invalid JSON payload."]);
		exit;
	}

	$sanitized = [];
	foreach ($decoded as $cat => $shows) {
		$catTrimmed = trim((string)$cat);
		if ($catTrimmed === '' || $catTrimmed === '_Inactive') continue;
		$sanitized[$catTrimmed] = [];
		if (is_array($shows)) {
			foreach ($shows as $s) {
				$sTrimmed = trim((string)$s);
				if ($sTrimmed !== '') {
					$sanitized[$catTrimmed][] = $sTrimmed;
				}
			}
		}
	}

	if (empty($sanitized)) {
		$jsonOutput = "{\n}\n";
	} else {
		$jsonOutput = json_encode($sanitized, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
	}

	if (file_exists($schedule_file)) {
		@copy($schedule_file, $backup_file);
	}

	$temp_file = $schedule_file . '.' . uniqid('tmp_', true);
	$fp = @fopen($temp_file, 'wb');
	if (!$fp) {
		echo json_encode(["status" => "error", "message" => "Could not open temporary file for writing."]);
		exit;
	}

	flock($fp, LOCK_EX);
	fwrite($fp, $jsonOutput);
	fflush($fp);
	flock($fp, LOCK_UN);
	fclose($fp);

	if (!@rename($temp_file, $schedule_file)) {
		@unlink($temp_file);
		echo json_encode(["status" => "error", "message" => "Atomic rename failed. Check file permissions."]);
		exit;
	}

	echo json_encode(["status" => "success", "message" => "Saved to " . basename($schedule_file)]);
	exit;
}

// Initial Data Fetch
function getActiveScheduleData() {
    global $schedule_file;
    $data = [];
    if (file_exists($schedule_file)) {
        $content = file_get_contents($schedule_file);
        $decoded = json_decode($content, true);
        if (is_array($decoded)) {
            $data = $decoded;
        }
    }
    unset($data['_Inactive']);
    return $data;
}