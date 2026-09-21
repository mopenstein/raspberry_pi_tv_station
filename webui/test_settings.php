<?php

    /**
     * TV Station Settings Tester
     * Modernized UI - Functionality Preserved
     */

    $pythonScript = '/home/pi/Desktop/test_settings.py';
    $runMax = 1;

    if(isset($_GET["run"])) {
        $runMax = $_GET["run"];
        if(!is_numeric($runMax)) $runMax = 1;
    } else {
        $runMax = 1;
    }

    // Logic to parse incoming GET parameters back into variables for the UI
    $current_date = "";
    $current_time = "";
    if (isset($_GET["test_settings"]) && $_GET["test_settings"] !== "system_time" && $_GET["test_settings"] !== "eval_equation") {
        $timestamp = strtotime($_GET["test_settings"]);
        if ($timestamp) {
            $current_date = date("Y-m-d", $timestamp);
            $current_time = date("H:i", $timestamp);
        }
    }

	function python_list_to_php_array($str) {
		// 1. Remove Python unicode literal prefixes (u' or u")
		$jsonCompatible = preg_replace("/\bu(['\"])/", '$1', $str);

		// 2. Replace single quotes with double quotes for valid JSON
		$jsonCompatible = str_replace("'", '"', $jsonCompatible);

		// 3. Decode into a PHP associative array
		return json_decode($jsonCompatible, true);
	}

    $current_file = isset($_GET["file"]) ? htmlspecialchars($_GET["file"]) : "";
    $current_tag = isset($_GET["tag"]) ? htmlspecialchars($_GET["tag"]) : "";
    $current_channel = isset($_GET["channel"]) ? htmlspecialchars($_GET["channel"]) : "";
    $current_eqn = isset($_GET["equation"]) ? htmlspecialchars($_GET["equation"]) : "";

    // Capture standard error and output for the log
    $results_html = "";

    for($i=0;$i<$runMax;$i++) {
        $py_cmd = "python " . escapeshellarg($pythonScript) . 
                  (isset($_GET["test_settings"]) ? ' -time '.escapeshellarg($_GET["test_settings"]) : '') . 
                  (isset($_GET["file"]) ? ' -file '.escapeshellarg($_GET["file"]) : '') . 
                  (isset($_GET["tag"]) ? ' -tag '.escapeshellarg($_GET["tag"]) : '') . 
                  (isset($_GET["channel"]) ? ' -channel '.escapeshellarg($_GET["channel"]) : '') . 
                  (isset($_GET["equation"]) ? ' -chance '.escapeshellarg($_GET["equation"]) : '');

        $res = shell_exec($py_cmd);
        
        try {
            $output = json_decode($res, true);
        } catch (Exception $e) {
            $results_html .= "<div class='error-box'>Raw Output Error: " . htmlspecialchars($res) . "</div>";
            continue;
        }

        if (!$output) {
             $results_html .= "<div class='error-box'>Failed to decode JSON. Raw output: <pre>" . htmlspecialchars($res) . "</pre></div>";
             continue;
        }

        // Build result block
        $block = '<div class="run-card">';
        $block .= '<div class="run-header">Run #' . ($i+1) . ' of ' . $runMax . ' <span class="cmd-badge">'.htmlspecialchars($py_cmd).'</span></div>';
        
        // Messages
        $block .= '<div class="section-title">Log Messages</div>';
        $block .= '<div class="msg-list">';
        foreach($output["messages"] as $msg) {
            $block .= '<div class="msg-item">';
            if (isset($msg['type'])) $block .= '<span class="type-tag">' . htmlspecialchars($msg["type"]) . '</span>';
            if(isset($msg["input"])) {
                $block .= '<ul class="input-details">';
				$next_one = null;
                foreach($msg["input"] as $k => $v) {
					$color = "default";
					if(strpos($k, "directories")>0 || strpos($v, "directories")>0) {
						$color = "red";
						$next_one = "dir";
						$block .= "<li style='color: $color;'><strong>$k:</strong> $v</li>";
					} else {
						if($next_one == "dir") {
							$color = "default";
							$args = python_list_to_php_array($v);
							if(count($args) == 0) { // directory check is empty
								$block .= "<li style='color: green;'>✔️ None</li>";
							} else {
								$block .= "<ul>";
								foreach($args as $arg_v) {
									for($j=0; $j<count($arg_v); $j+=2) {
										$block .= "<li style='color: $color;'>{$arg_v[$j]}:</li>";
										$block .= "<ul>";
											$block .= "<li style='color: $color;'>{$arg_v[$j + 1]}</li>";
										$block .= "</ul>";
									}
								}
							}
							$next_one = null;
						}
					}

                    
                }
                $block .= "</ul>";
            }
            $block .= '</div>';
        }
        $block .= '</div>';

        // Programming
        $block .= '<div class="section-title">Programming</div>';
        $block .= '<div class="grid-json">';
        foreach($output["programming"] as $k => $msg) {
            $block .= '<div class="json-entry"><strong>'.htmlspecialchars($k) . '</strong><br>' . htmlspecialchars($msg) . '</div>';
        }
        $block .= '</div>';

        // Commercials
        $block .= '<div class="section-title">Commercials</div>';
        $block .= '<div class="grid-json">';
        foreach($output["commercials"] as $k => $msg) {
            $block .= '<div class="json-entry commercial"><strong>'.htmlspecialchars($k) . '</strong><br>' . htmlspecialchars($msg) . '</div>';
        }
        $block .= '</div>';

        $block .= '</div>'; // End run-card
        $results_html .= $block;
    }

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TV Station Test Suite</title>
    <style>
        :root {
            --bg: #0f172a;
            --card-bg: #1e293b;
            --accent: #38bdf8;
            --text: #f1f5f9;
            --text-dim: #94a3b8;
            --border: #334155;
            --success: #22c55e;
            --commercial: #fbbf24;
        }

        body {
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            background-color: var(--bg);
            color: var(--text);
            margin: 0;
            display: flex;
            height: 100vh;
            overflow: hidden;
        }

        /* Sidebar Navigation */
        aside {
            width: 320px;
            background: var(--card-bg);
            border-right: 1px solid var(--border);
            padding: 1.25rem;
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
            overflow-y: auto;
        }

        h1 { font-size: 1.1rem; margin: 0 0 0.5rem 0; color: var(--accent); border-bottom: 1px solid var(--border); padding-bottom: 0.75rem; }
        
        label { display: block; font-size: 0.75rem; margin-bottom: 0.2rem; color: var(--text-dim); font-weight: 600; text-transform: uppercase; letter-spacing: 0.025em; }
        
        input {
            width: 100%;
            padding: 0.5rem;
            background: #0f172a;
            border: 1px solid var(--border);
            border-radius: 4px;
            color: white;
            margin-bottom: 0.25rem;
            box-sizing: border-box;
            font-size: 0.9rem;
        }

        button {
            width: 100%;
            padding: 0.7rem;
            background: var(--accent);
            color: #000;
            border: none;
            border-radius: 4px;
            font-weight: bold;
            cursor: pointer;
            transition: opacity 0.2s;
            margin-top: 0.5rem;
        }

        button:hover { opacity: 0.9; }

        /* Main Content Area */
        main {
            flex: 1;
            padding: 2rem;
            overflow-y: auto;
            background: #0b0f19;
        }

        .run-card {
            background: var(--card-bg);
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 2rem;
            border: 1px solid var(--border);
            box-shadow: 0 4px 6px -1px rgb(0 0 0 / 0.1);
        }

        .run-header {
            font-weight: bold;
            font-size: 1.1rem;
            margin-bottom: 1rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .cmd-badge {
            font-family: monospace;
            font-size: 0.75rem;
            background: #000;
            padding: 0.3rem 0.6rem;
            border-radius: 4px;
            color: #22c55e;
        }

        .section-title {
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: var(--accent);
            margin: 1.5rem 0 0.75rem 0;
            border-left: 3px solid var(--accent);
            padding-left: 0.75rem;
        }

        .msg-list { background: #0f172a; border-radius: 8px; padding: 1rem; }
        .msg-item { border-bottom: 1px solid #334155; padding: 0.5rem 0; }
        .msg-item:last-child { border-bottom: none; }
        
        .type-tag {
            font-size: 0.7rem;
            background: #334155;
            padding: 2px 6px;
            border-radius: 4px;
            margin-right: 10px;
        }

        .input-details { margin: 5px 0 0 0; list-style: none; padding: 0; font-size: 0.85rem; color: var(--text-dim); }

        .grid-json {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 10px;
        }

        .json-entry {
            background: #0f172a;
            padding: 0.8rem;
            border-radius: 6px;
            font-family: monospace;
            font-size: 0.85rem;
            white-space: pre-wrap;
            border-left: 3px solid var(--success);
        }

        .json-entry.commercial { border-left-color: var(--commercial); }

        .error-box { background: #7f1d1d; color: #fecaca; padding: 1rem; border-radius: 8px; margin-bottom: 1rem; }

        .eqn-block { margin-top: 1rem; padding-top: 1rem; border-top: 1px solid var(--border); }
    </style>
</head>
<body>

    <aside>
        <h1>Test Input</h1>
        
        <label for="date">Select a Date</label>
        <input type="date" id="date" value="<?php echo $current_date; ?>" />

        <label for="time">Select a Time</label>
        <input type="time" id="time" value="<?php echo $current_time; ?>" />

        <label for="filename">Filename</label>
        <input type="text" id="filename" placeholder="video.mp4" value="<?php echo $current_file; ?>" />

        <label for="tag">Tag</label>
        <input type="text" id="tag" placeholder="night_rotation" value="<?php echo $current_tag; ?>" />

        <label for="channel">Channel</label>
        <input type="text" id="channel" placeholder="CH-01" value="<?php echo $current_channel; ?>" />

        <label for="run">Number of Runs</label>
        <input type="number" id="run" value="<?php echo $runMax; ?>" min="1" max="10" />

        <button onclick="formatDateTime()">Run System Test</button>

        <div class="eqn-block">
            <label for="equation">Eval Equation (Chance)</label>
            <input type="text" id="equation" placeholder="e.g. 0.5" value="<?php echo $current_eqn; ?>" />
            <button style="background: #64748b; color: white;" onclick="evaluateEquation()">Run Equation</button>
        </div>
    </aside>

    <main>
        <?php 
            if (empty($results_html)) {
                echo '<div style="text-align:center; padding-top:100px; color:var(--text-dim)">
                        <p>Adjust settings in the sidebar and click "Run System Test" to begin.</p>
                      </div>';
            } else {
                echo $results_html;
            }
        ?>
    </main>

    <script>
        function evaluateEquation() {
            const equationInput = document.getElementById('equation').value;
            if(equationInput) {
                document.location.href = '?test_settings=eval_equation&equation=' + encodeURIComponent(equationInput);
            }
        }

        function formatDateTime() {
            const dateInput = document.getElementById('date').value;
            const timeInput = document.getElementById('time').value;
            const filename = document.getElementById('filename').value;
            const tag = document.getElementById('tag').value;
            const channelInput = document.getElementById('channel').value;
            const runInput = document.getElementById('run').value;

            let time = "test_settings=system_time";
            let file = "";
            let stag = "";
            let channel = "";
            let run = "&run=" + (runInput || 1);

            if (dateInput && timeInput) {
                const date = new Date(`${dateInput}T${timeInput}`);
                const options = { month: 'short', day: '2-digit', year: 'numeric' };
                const formattedDate = date.toLocaleDateString('en-US', options).replace(',', '');
                const hours = date.getHours();
                const minutes = date.getMinutes();
                const formattedTime = `${(hours % 12 || 12).toString().padStart(2, '0')}:${minutes.toString().padStart(2, '0')}${hours < 12 ? 'AM' : 'PM'}`;
                
                time = 'test_settings=' + encodeURIComponent(formattedDate + ' ' + formattedTime);
            }
                
            if(filename) file = "&file=" + encodeURIComponent(filename);
            if(tag) stag = "&tag=" + encodeURIComponent(tag);
            if(channelInput) channel = "&channel=" + encodeURIComponent(channelInput);

            document.location.href = '?' + time + file + stag + channel + run;
        }
    </script>
</body>
</html>