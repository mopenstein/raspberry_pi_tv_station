<?php

class Workers implements ManageCard {
    private $name = 'Background Workers';
    private $links = [];
    private $html = '';
    private $settings = null;
    private $workers_dir = '/var/www/html/workers';
    private $settingsPath = '/home/pi/Desktop/settings.json';
    private $status_msg = '';

    public function priority() {
        return 180;
    }
    
    public function name() { 
        return $this->name; 
    }

    public function links() { 
        return $this->links; 
    }

    public function html() { 
        return $this->html; 
    }

    public function setSettings($json_settings) {
        $this->settings = $json_settings;
        $this->processPost();
        $this->generateHtml();
    }

    private function getTemplates() {
        $templates = [];
        if (!is_dir($this->workers_dir)) {
            return $templates;
        }

        $files = glob($this->workers_dir . '/*.php');
        foreach ($files as $filepath) {
            $meta = [
                'file' => 'workers/' . basename($filepath),
                'name' => basename($filepath),
                'description' => 'No description provided.',
                'params' => []
            ];

            $handle = @fopen($filepath, 'r');
            if ($handle) {
                $inBlock = false;
                while (($line = fgets($handle)) !== false) {
                    $trimmed = trim($line);
                    if (strcasecmp($trimmed, '# MetaData') === 0) { $inBlock = true; continue; }
                    if (strcasecmp($trimmed, '# EndMetaData') === 0) { break; }

                    if ($inBlock && strpos($trimmed, '#') === 0) {
                        $content = trim(substr($trimmed, 1));
                        if (stripos($content, 'Name:') === 0) {
                            $meta['name'] = trim(substr($content, 5));
                        } elseif (stripos($content, 'Description:') === 0) {
                            $meta['description'] = trim(substr($content, 12));
                        } elseif (stripos($content, 'Param:') === 0) {
                            $paramStr = trim(substr($content, 6));
                            $parts = array_map('trim', explode('|', $paramStr));
                            if (count($parts) >= 5) {
                                $meta['params'][] = [
                                    'key' => $parts[0],
                                    'type' => strtolower($parts[1]),
                                    'default' => $parts[2],
                                    'constraints' => $parts[3],
                                    'label' => $parts[4]
                                ];
                            }
                        }
                    }
                }
                fclose($handle);
            }
            $templates[$meta['file']] = $meta;
        }
        return $templates;
    }

    private function saveWorkersToSettings($new_workers_array) {
        $raw = @file_get_contents($this->settingsPath);
        if ($raw === false) {
            $this->status_msg = '<div class="worker-notice error">Error reading settings.json</div>';
            return;
        }

        $new_block_content = "";
        if (!empty($new_workers_array)) {
            $new_block_content = "\n\t\t\"" . implode("\",\n\t\t\"", $new_workers_array) . "\"\n\t";
        }

        $pattern_array = '/("workers"\s*:\s*\[)(.*?)(\])/s';
        $has_key = preg_match('/"workers"\s*:/', $raw);

        if (preg_match($pattern_array, $raw)) {
            $newRaw = preg_replace($pattern_array, '${1}' . $new_block_content . '${3}', $raw, 1);
        } elseif (!$has_key) {
            $injection = ",\n\t\"workers\": [" . $new_block_content . "]\n}";
            $newRaw = preg_replace('/\}\s*$/', $injection, $raw);
        } else {
            $this->status_msg = '<div class="worker-notice error">The "workers" entry is malformed in settings.json. Please open the text editor and remove the broken line so it can be automatically rebuilt.</div>';
            return;
        }

        $postData = http_build_query([
            'api_save' => 1,
            'payload'  => $newRaw
        ]);

        $options = [
            'http' => [
                'method'  => 'POST',
                'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
                'content' => $postData,
                'timeout' => 5
            ]
        ];
        $context = stream_context_create($options);
        $response_raw = @file_get_contents('http://127.0.0.1/settings.php', false, $context);

        if ($response_raw !== false) {
            $response = json_decode($response_raw, true);
            if ($response && isset($response['success']) && $response['success'] === true) {
                $this->status_msg = '<div class="worker-notice success">Workers updated successfully!</div>';
                $this->settings['workers'] = $new_workers_array;
            } else {
                $err = $response['error'] ?? 'Unknown API error';
                $this->status_msg = '<div class="worker-notice error">API Error: ' . htmlspecialchars($err) . '</div>';
            }
        } else {
            $this->status_msg = '<div class="worker-notice error">Failed to connect to local 127.0.0.1 settings API.</div>';
        }
    }

    private function processPost() {
        if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['workers_action'])) {
            return;
        }

        $active_workers = (isset($this->settings['workers']) && is_array($this->settings['workers'])) 
            ? $this->settings['workers'] 
            : [];

        if ($_POST['workers_action'] === 'save_all') {
            $new_workers = [];
            if (isset($_POST['worker']) && is_array($_POST['worker'])) {
                foreach ($_POST['worker'] as $wData) {
                    $file = $wData['file'];
                    $params = $wData['params'] ?? [];
                    $query = http_build_query($params);
                    $new_workers[] = $file . (!empty($query) ? '?' . $query : '');
                }
            }
            $this->saveWorkersToSettings($new_workers);
        } elseif ($_POST['workers_action'] === 'add') {
            $new_file = $_POST['new_worker_file'] ?? '';
            if (!empty($new_file)) {
                $templates = $this->getTemplates();
                $query_parts = [];
                if (isset($templates[$new_file])) {
                    foreach ($templates[$new_file]['params'] as $p) {
                        if ($p['default'] !== '') {
                            $query_parts[$p['key']] = $p['default'];
                        }
                    }
                }
                $query = http_build_query($query_parts);
                $active_workers[] = $new_file . (!empty($query) ? '?' . $query : '');
                $this->saveWorkersToSettings($active_workers);
            }
        } elseif ($_POST['workers_action'] === 'delete') {
            $del_index = (int)$_POST['delete_index'];
            if (isset($active_workers[$del_index])) {
                array_splice($active_workers, $del_index, 1);
                $this->saveWorkersToSettings($active_workers);
            }
        }
    }

    private function generateHtml() {
        $templates = $this->getTemplates();
        
        $active_workers = (isset($this->settings['workers']) && is_array($this->settings['workers'])) 
            ? $this->settings['workers'] 
            : [];

        $out = '
        <style type="text/css">
            .worker-stack { display: flex; flex-direction: column; gap: 8px; font-family: inherit; }
            .worker-notice { padding: 10px 14px; border-radius: 4px; margin-bottom: 8px; font-size: 0.8rem; }
            .worker-notice.success { background: rgba(78, 201, 176, 0.1); border: 1px solid rgba(78, 201, 176, 0.3); color: #4ec9b0; }
            .worker-notice.error { background: rgba(220, 20, 60, 0.1); border: 1px solid rgba(220, 20, 60, 0.3); color: #ff6b6b; }
            .worker-row { background: rgba(0, 0, 0, 0.25); border: 1px solid rgba(255, 255, 255, 0.07); box-shadow: inset 0 1px 3px rgba(0,0,0,0.4); border-radius: 4px; overflow: hidden; }
            .worker-summary { display: flex; align-items: center; justify-content: space-between; padding: 10px; cursor: pointer; user-select: none; }
            .worker-summary::-webkit-details-marker { display: none; }
            .worker-lead { display: flex; align-items: center; gap: 8px; font-weight: 600; font-size: 0.85rem; color: #fff; }
            .worker-arrow { width: 12px; height: 12px; opacity: 0.45; transition: transform 0.2s ease; }
            .worker-row[open] .worker-arrow { transform: rotate(90deg); opacity: 0.85; }
            .worker-drawer { padding: 12px 14px; border-top: 1px solid rgba(255, 255, 255, 0.05); background: rgba(0, 0, 0, 0.15); }
            .worker-desc { font-size: 0.76rem; opacity: 0.75; margin-bottom: 12px; line-height: 1.4; }
            .worker-form-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
            .worker-input-group { display: flex; flex-direction: column; gap: 4px; }
            .worker-input-group label { font-size: 0.72rem; color: #aaa; text-transform: uppercase; letter-spacing: 0.5px; }
            .worker-input-group input[type="text"], .worker-input-group input[type="number"], .worker-input-group select { 
                background: rgba(0, 0, 0, 0.3); border: 1px solid rgba(255, 255, 255, 0.15); color: #fff; padding: 6px 8px; border-radius: 3px; font-size: 0.8rem; font-family: inherit; 
            }
            .worker-checkbox-wrapper { display: flex; align-items: center; gap: 6px; font-size: 0.8rem; color: #ddd; margin-top: 16px; }
            .worker-actions { margin-top: 12px; display: flex; justify-content: flex-end; gap: 8px; }
            .worker-btn { background: rgba(255, 255, 255, 0.08); border: 1px solid rgba(255, 255, 255, 0.15); color: #dcdce0; padding: 4px 10px; font-size: 0.75rem; border-radius: 3px; cursor: pointer; }
            .worker-btn:hover { background: rgba(255, 255, 255, 0.16); color: #fff; }
            .worker-btn.danger:hover { background: rgba(220, 20, 60, 0.4); border-color: rgba(220, 20, 60, 0.6); }
            .worker-btn.primary { background: #007acc; border-color: #005f9e; color: #fff; font-weight: bold; }
            .worker-btn.primary:hover { background: #0062a3; }
            .worker-add-bar { display: flex; align-items: center; gap: 8px; background: rgba(0, 0, 0, 0.35); border: 1px dashed rgba(255, 255, 255, 0.15); border-radius: 4px; padding: 10px; margin-top: 12px; }
            .worker-add-bar select { flex: 1; background: #121212; border: 1px solid #333; color: #fff; padding: 6px; font-size: 0.8rem; }
        </style>';

        $out .= '<div class="worker-stack">';
        if (!empty($this->status_msg)) {
            $out .= $this->status_msg;
        }

        $out .= '<form method="POST">';
        $out .= '<input type="hidden" name="workers_action" value="save_all">';

        if (empty($active_workers)) {
            $out .= '<div style="padding: 12px; opacity: 0.6; font-size: 0.85rem;">No background workers active.</div>';
        } else {
            foreach ($active_workers as $index => $url) {
                $parts = explode('?', $url, 2);
                $file = $parts[0];
                $query = $parts[1] ?? '';
                parse_str($query, $current_params);

                $has_meta = isset($templates[$file]);
                $meta = $has_meta ? $templates[$file] : [
                    'name' => basename($file),
                    'description' => 'Metadata unavailable. Edit raw variables below.',
                    'params' => []
                ];

                $out .= '<details class="worker-row">';
                $out .= '<summary class="worker-summary">';
                $out .= '<div class="worker-lead"><svg class="worker-arrow" viewBox="0 0 24 24" fill="currentColor"><path d="M8.59 16.59L13.17 12 8.59 7.41 10 6l6 6-6 6-1.41-1.41z"/></svg>' . htmlspecialchars($meta['name']) . '</div>';
                
                if (!$has_meta) {
                    $out .= '<div style="font-size: 0.72rem; opacity: 0.5; font-family: monospace;">' . htmlspecialchars($file) . '</div>';
                }
                
                $out .= '</summary>';
                
                $out .= '<div class="worker-drawer">';
                $out .= '<div class="worker-desc">' . htmlspecialchars($meta['description']) . '</div>';
                $out .= '<input type="hidden" name="worker['.$index.'][file]" value="' . htmlspecialchars($file) . '">';
                
                $out .= '<div class="worker-form-grid">';
                
                if (empty($meta['params'])) {
                    foreach ($current_params as $k => $v) {
                        $out .= '<div class="worker-input-group">';
                        $out .= '<label>' . htmlspecialchars($k) . '</label>';
                        $out .= '<input type="text" name="worker['.$index.'][params][' . htmlspecialchars($k) . ']" value="' . htmlspecialchars($v) . '">';
                        $out .= '</div>';
                    }
                } else {
                    foreach ($meta['params'] as $p) {
                        $key = $p['key'];
                        $val = $current_params[$key] ?? $p['default'];
                        $nameAttr = 'worker['.$index.'][params][' . htmlspecialchars($key) . ']';
                        
                        $out .= '<div class="worker-input-group">';
                        if ($p['type'] !== 'boolean') {
                            $out .= '<label>' . htmlspecialchars($p['label']) . '</label>';
                        }

                        if ($p['type'] === 'dropdown') {
                            $opts = array_map('trim', explode(',', $p['constraints']));
                            $out .= '<select name="' . $nameAttr . '">';
                            foreach ($opts as $opt) {
                                $selected = ($val === $opt) ? 'selected' : '';
                                $out .= '<option value="' . htmlspecialchars($opt) . '" ' . $selected . '>' . htmlspecialchars($opt) . '</option>';
                            }
                            $out .= '</select>';
                        } elseif ($p['type'] === 'number') {
                            $minmax = array_map('trim', explode(',', $p['constraints']));
                            $min = $minmax[0] ?? '';
                            $max = $minmax[1] ?? '';
                            $out .= '<input type="number" name="' . $nameAttr . '" value="' . htmlspecialchars($val) . '" min="' . htmlspecialchars($min) . '" max="' . htmlspecialchars($max) . '">';
                        } elseif ($p['type'] === 'boolean') {
                            $checked = (filter_var($val, FILTER_VALIDATE_BOOLEAN)) ? 'checked' : '';
                            $out .= '<label class="worker-checkbox-wrapper">';
                            $out .= '<input type="checkbox" name="' . $nameAttr . '" value="true" ' . $checked . '> ';
                            $out .= htmlspecialchars($p['label']);
                            $out .= '</label>';
                        } else {
                            $out .= '<input type="text" name="' . $nameAttr . '" value="' . htmlspecialchars($val) . '">';
                        }
                        $out .= '</div>';
                    }
                }
                
                $out .= '</div>'; 
                
                $out .= '<div class="worker-actions">';
                $out .= '<button type="submit" formmethod="POST" name="delete_index" value="'.$index.'" formaction="?workers_action=delete" class="worker-btn danger" onclick="this.form.elements[\'workers_action\'].value=\'delete\'; return confirm(\'Remove this worker?\');">Remove</button>';
                $out .= '</div>';
                
                $out .= '</div>'; 
                $out .= '</details>';
            }
        }
        
        if (!empty($active_workers)) {
            $out .= '<div style="margin-top: 14px; margin-right: 12px; margin-bottom: 6px; text-align: right;"><button type="submit" class="worker-btn primary">Save All Changes</button></div>';
        }
        $out .= '</form>'; 

        $out .= '<div class="worker-add-bar">';
        $out .= '<form method="POST" style="display:flex; width:100%; gap:8px; margin:0;">';
        $out .= '<input type="hidden" name="workers_action" value="add">';
        $out .= '<select name="new_worker_file" required>';
        $out .= '<option value="" disabled selected>+ Select worker to add...</option>';
        foreach ($templates as $file => $meta) {
            $out .= '<option value="' . htmlspecialchars($file) . '">' . htmlspecialchars($meta['name']) . '</option>';
        }
        $out .= '</select>';
        $out .= '<button type="submit" class="worker-btn">Add to Stack</button>';
        $out .= '</form>';
        $out .= '</div>';

        $out .= '</div>'; 
        $this->html = $out;
    }
}
?>