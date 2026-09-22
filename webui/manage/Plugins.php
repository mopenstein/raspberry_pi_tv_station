<?php

class Plugins implements ManageCard {
    private $name = 'Plugins';
    private $links = [];
    private $html = '';
    private $settings_file = "/home/pi/Desktop/settings.json";
    private $plugins_dir;
    private $status_msg = '';

    function getPluginMetadata(string $filepath): array {
        $metadata = [];
        $handle = @fopen($filepath, 'r');
        if (!$handle) {
            return $metadata;
        }

        $inBlock = false;
        $linesRead = 0;
        $currentKey = null;
        $lineBreakPending = false;

        while (($line = fgets($handle)) !== false) {
            $linesRead++;
            $trimmed = trim($line);

            if ($linesRead > 60) {
                break;
            }

            if (strcasecmp($trimmed, '# MetaData') === 0) {
                $inBlock = true;
                continue;
            }

            if (strcasecmp($trimmed, '# EndMetaData') === 0) {
                break;
            }

            if ($inBlock && $trimmed !== '' && $trimmed[0] === '#') {
                $rawContent = ltrim($trimmed, "# \t");

                if ($rawContent === '') {
                    continue;
                }

                $hasContinuation = (substr(rtrim($rawContent), -1) === '\\');
                if ($hasContinuation) {
                    $rawContent = rtrim(substr(rtrim($rawContent), 0, -1));
                }

                if (strpos($rawContent, ':') !== false && !$lineBreakPending) {
                    list($key, $val) = explode(':', $rawContent, 2);
                    $currentKey = strtolower(trim($key));
                    $metadata[$currentKey] = trim($val);
                } elseif ($currentKey !== null) {
                    $separator = $lineBreakPending ? "\n" : " ";
                    $metadata[$currentKey] .= $separator . trim($rawContent);
                }

                $lineBreakPending = $hasContinuation;
            }
        }

        fclose($handle);
        return $metadata;
    }

	private function ensureDirectoryWritable(string $dir): bool {
		if (!is_dir($dir)) {
			return false;
		}

		if (!is_writable($dir)) {
			// Attempt native PHP chmod to 0777
			@chmod($dir, 0777);
			clearstatcache(true, $dir);

			// Fallback: use shell execution if native chmod was blocked by ownership/umask
			if (!is_writable($dir)) {
				@exec('sudo chmod 0777 ' . escapeshellarg($dir));
				clearstatcache(true, $dir);
			}
		}

		return is_writable($dir);
	}

	private function handleUpload() {
		if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['plugin_file'])) {
			if (empty($this->plugins_dir) || !is_dir($this->plugins_dir)) {
				$this->status_msg = 'Error: Invalid plugins directory.';
				return;
			}

			// Verify and adjust folder permissions prior to write
			if (!$this->ensureDirectoryWritable($this->plugins_dir)) {
				$this->status_msg = 'Error: Cannot write to plugins directory (permissions lock).';
				return;
			}

			$file = $_FILES['plugin_file'];
			if ($file['error'] !== UPLOAD_ERR_OK) {
				$this->status_msg = 'Upload failed with code: ' . $file['error'];
				return;
			}

			$rawFilename = basename($file['name']);
			if (substr($rawFilename, -3) !== '.py') {
				$this->status_msg = 'Error: Only .py plugin files are permitted.';
				return;
			}

			$dest = rtrim($this->plugins_dir, '/') . '/' . $rawFilename;
			$disabledTarget = $dest . '.disabled';

			// Overwrite disabled file directly if existing plugin is toggled off
			if (file_exists($disabledTarget)) {
				$dest = $disabledTarget;
			}

			// If target file already exists, ensure it is also writable before overwrite
			if (file_exists($dest) && !is_writable($dest)) {
				@chmod($dest, 0777);
				@exec('sudo chmod 0777 ' . escapeshellarg($dest));
			}

			if (move_uploaded_file($file['tmp_name'], $dest)) {
				@chmod($dest, 0755);
				@exec('sudo chmod 0755 ' . escapeshellarg($dest));
				$this->status_msg = 'Successfully installed/updated: ' . htmlspecialchars($rawFilename);
			} else {
				$this->status_msg = 'Error: Failed to save file to ' . htmlspecialchars($dest);
			}
		}
	}

    private function handleToggleAction() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['toggle_plugin'])) {
            $filename = basename($_POST['toggle_plugin']);
            $currentPath = rtrim($this->plugins_dir, '/') . '/' . $filename;

            if (file_exists($currentPath)) {
                if (substr($filename, -9) === '.disabled') {
                    // Enable: strip .disabled and ensure executable bit persists
                    $newPath = substr($currentPath, 0, -9);
                    if (@rename($currentPath, $newPath)) {
                        @chmod($newPath, 0755);
                        @exec('chmod +x ' . escapeshellarg($newPath));
                    }
                } elseif (substr($filename, -3) === '.py') {
                    // Disable: append .disabled
                    $newPath = $currentPath . '.disabled';
                    @rename($currentPath, $newPath);
                }
            }
        }
    }

    public function __construct() {
        global $json_response;
        $this->plugins_dir = $json_response[0]["plugins directory"] ?? null;
        
        $this->handleUpload();
        $this->handleToggleAction();

        $plugins = [];

        if (!empty($this->plugins_dir) && is_dir($this->plugins_dir)) {
            $files = glob($this->plugins_dir . '/*.{py,disabled}', GLOB_BRACE);
            if ($files) {
                foreach ($files as $file) {
                    if (substr($file, -3) !== '.py' && substr($file, -9) !== '.disabled') {
                        continue;
                    }

                    $meta = $this->getPluginMetadata($file);
                    $base = basename($file);

                    $meta['filename'] = $base;
                    $meta['is_enabled'] = (substr($base, -9) !== '.disabled');
                    $plugins[] = $meta;
                }
            }
        }

        $this->html = $this->renderPluginCards($plugins);
    }

    private function renderPluginCards(array $plugins): string {
        $out = '
        <style type="text/css">
            .plugin-stack {
                display: flex;
                flex-direction: column;
                gap: 8px;
                padding: 4px 0;
                font-family: inherit;
            }

            .plugin-upload-bar {
                background: rgba(0, 0, 0, 0.35);
                border: 1px dashed rgba(255, 255, 255, 0.15);
                border-radius: 4px;
                padding: 8px 10px;
                margin-bottom: 8px;
                display: flex;
                align-items: center;
                justify-content: space-between;
                gap: 8px;
                flex-wrap: wrap;
            }

            .plugin-upload-bar form {
                display: flex;
                align-items: center;
                gap: 8px;
                margin: 0;
                width: 100%;
            }

            .plugin-upload-bar input[type="file"] {
                font-size: 0.75rem;
                color: #aaa;
                max-width: 220px;
            }

            .plugin-status-msg {
                font-size: 0.75rem;
                color: #4ec9b0;
                margin-bottom: 6px;
                font-family: monospace;
            }

            .plugin-row {
                background: rgba(0, 0, 0, 0.25);
                border: 1px solid rgba(255, 255, 255, 0.07);
                box-shadow: inset 0 1px 3px rgba(0, 0, 0, 0.4);
                border-radius: 4px;
                overflow: hidden;
                transition: border-color 0.15s ease;
            }

            .plugin-row:hover {
                border-color: rgba(255, 255, 255, 0.14);
            }

            .plugin-row.is-disabled {
                opacity: 0.5;
            }

            .plugin-summary {
                display: flex;
                align-items: center;
                justify-content: space-between;
                padding: 8px 10px;
                cursor: pointer;
                user-select: none;
                list-style: none;
            }

            .plugin-summary::-webkit-details-marker {
                display: none;
            }

            .plugin-lead {
                display: flex;
                align-items: center;
                gap: 8px;
                min-width: 0;
            }

            .plugin-arrow {
                display: inline-block;
                width: 12px;
                height: 12px;
                opacity: 0.45;
                transition: transform 0.2s ease, opacity 0.2s ease;
                flex-shrink: 0;
            }

            .plugin-row[open] .plugin-arrow {
                transform: rotate(90deg);
                opacity: 0.85;
            }

            .plugin-row:hover .plugin-arrow {
                opacity: 0.8;
            }

            .plugin-name {
                font-size: 0.85rem;
                font-weight: 600;
                letter-spacing: 0.2px;
                color: #ffffff;
                white-space: nowrap;
                overflow: hidden;
                text-overflow: ellipsis;
            }

            .plugin-controls {
                display: flex;
                align-items: center;
                gap: 8px;
                flex-shrink: 0;
            }

            .plugin-badge {
                font-size: 0.72rem;
                font-family: monospace;
                opacity: 0.6;
            }

            .plugin-btn {
                background: rgba(255, 255, 255, 0.08);
                border: 1px solid rgba(255, 255, 255, 0.15);
                box-shadow: 0 1px 2px rgba(0, 0, 0, 0.3);
                color: #dcdce0;
                padding: 2px 7px;
                font-size: 0.72rem;
                border-radius: 3px;
                cursor: pointer;
                font-family: inherit;
            }

            .plugin-btn:hover {
                background: rgba(255, 255, 255, 0.16);
                color: #ffffff;
                border-color: rgba(255, 255, 255, 0.25);
            }

            .plugin-drawer {
                padding: 8px 10px 10px 30px;
                border-top: 1px solid rgba(255, 255, 255, 0.05);
                font-size: 0.78rem;
                color: rgba(255, 255, 255, 0.75);
            }

            .plugin-subline {
                display: flex;
                justify-content: space-between;
                font-family: monospace;
                font-size: 0.72rem;
                opacity: 0.6;
                margin-bottom: 6px;
            }

            .plugin-desc {
                line-height: 1.4;
                white-space: pre-wrap;
                font-size: 0.76rem;
                opacity: 0.85;
            }
        </style>
        <div class="plugin-stack">';

        // Upload control bar
        $out .= '
        <div class="plugin-upload-bar">
            <form method="POST" enctype="multipart/form-data">
                <input type="file" name="plugin_file" accept=".py" required>
                <button type="submit" class="plugin-btn">Upload / Update</button>
            </form>
        </div>';

        if (!empty($this->status_msg)) {
            $out .= '<div class="plugin-status-msg">' . $this->status_msg . '</div>';
        }

        if (empty($plugins)) {
            $out .= '<div style="padding: 12px; opacity: 0.6; font-size: 0.85rem;">No plugins detected.</div>';
        } else {
            foreach ($plugins as $plugin) {
                $name      = htmlspecialchars($plugin['name'] ?? $plugin['filename']);
                $version   = htmlspecialchars($plugin['version'] ?? '—');
                $date      = htmlspecialchars($plugin['version date'] ?? '');
                $file      = htmlspecialchars($plugin['filename']);
                $desc      = nl2br(htmlspecialchars($plugin['description'] ?? 'No description provided.'));
                $isEnabled = $plugin['is_enabled'];

                $statusClass = $isEnabled ? '' : 'is-disabled';
                $btnLabel    = $isEnabled ? 'Disable' : 'Enable';

                $out .= '
                <details class="plugin-row ' . $statusClass . '">
                    <summary class="plugin-summary">
                        <div class="plugin-lead">
                            <svg class="plugin-arrow" viewBox="0 0 24 24" fill="currentColor">
                                <path d="M8.59 16.59L13.17 12 8.59 7.41 10 6l6 6-6 6-1.41-1.41z"/>
                            </svg>
                            <span class="plugin-name">' . $name . '</span>
                        </div>
                        <div class="plugin-controls">
                            <span class="plugin-badge">v' . $version . '</span>
                            <form method="POST" style="display:inline; margin:0; padding:0;" onclick="event.stopPropagation();">
                                <input type="hidden" name="toggle_plugin" value="' . $file . '">
                                <button type="submit" class="plugin-btn">' . $btnLabel . '</button>
                            </form>
                        </div>
                    </summary>
                    <div class="plugin-drawer">
                        <div class="plugin-subline">
                            <span>' . $file . '</span>
                            ' . ($date ? '<span>' . $date . '</span>' : '') . '
                        </div>
                        <div class="plugin-desc">' . $desc . '</div>
                    </div>
                </details>';
            }
        }

        $out .= '</div>';
        return $out;
    }

    public function name() { return $this->name; }
    public function links() { return $this->links; }
    public function html() { return $this->html; }
}
?>