<?php

class SystemUpdater implements ManageCard {
    private $name = 'System & Software Updates';
    private $links = [];
    private $html = '';
    private $updater_script = '/home/pi/Desktop/update_station.sh';

    public function __construct() {
        // Intercept streaming request if triggered via AJAX
        if (isset($_GET['action']) && $_GET['action'] === 'run_station_update') {
            $this->streamUpdateProcess();
            exit; // Stop execution so no dashboard HTML is returned
        }

        $this->renderCard();
    }

    private function renderCard() {
        $this->html = '
        <div style="margin:5px; padding:10px;">
            <strong>Check & Install Station Updates</strong>
            <p style="margin: 8px 0 12px 0; color: #666; font-size: 13px;">
                Fetch the latest scripts, station assets, and fixes directly from the repository.
            </p>

            <button id="btnStartUpdate" class="btn" style="padding: 6px 14px; cursor: pointer;" onclick="executeStationUpdate()">
                Run Station Update
            </button>

            <div id="updateTerminalContainer" style="display:none; margin-top: 14px;">
                <div style="background: #1e1e1e; color: #eee; font-family: monospace; font-size: 12px; padding: 6px 10px; border-radius: 4px 4px 0 0; display:flex; justify-content: space-between;">
                    <span>Console Output</span>
                    <span id="updateStatusText" style="color: #bbb;">Running...</span>
                </div>
                <pre id="updateTerminal" style="margin:0; background: #000; color: #39ff14; font-family: monospace; font-size: 12px; padding: 12px; height: 260px; overflow-y: auto; white-space: pre-wrap; word-break: break-all; border: 1px solid #333; border-top: none;"></pre>
            </div>
        </div>

        <script>
        async function executeStationUpdate() {
            if (!confirm("Start the update process now?")) return;

            const btn = document.getElementById("btnStartUpdate");
            const box = document.getElementById("updateTerminalContainer");
            const term = document.getElementById("updateTerminal");
            const status = document.getElementById("updateStatusText");

            btn.disabled = true;
            btn.style.opacity = "0.5";
            box.style.display = "block";
            term.textContent = "";
            status.textContent = "Connecting to GitHub...";
            status.style.color = "#bbb";

            try {
                // Strip fragments (#Manage) and target the PHP handler cleanly
                const url = window.location.pathname + "?action=run_station_update";
                const response = await fetch(url);
                
                if (!response.ok) {
                    throw new Error("HTTP error " + response.status);
                }

                const reader = response.body.getReader();
                const decoder = new TextDecoder("utf-8");

                while (true) {
                    const { value, done } = await reader.read();
                    if (done) break;
                    term.textContent += decoder.decode(value, { stream: true });
                    term.scrollTop = term.scrollHeight;
                }

                status.textContent = "Finished";
                status.style.color = "#39ff14";
            } catch (err) {
                term.textContent += "\n[Error executing update: " + err.message + "]";
                status.textContent = "Failed";
                status.style.color = "#ff4444";
            } finally {
                btn.disabled = false;
                btn.style.opacity = "1";
            }
        }
        </script>
        ';
    }

    private function streamUpdateProcess() {
        set_time_limit(300);

        @ini_set('output_buffering', 'off');
        @ini_set('zlib.output_compression', false);
        @ini_set('implicit_flush', true);
        ob_implicit_flush(true);

        while (ob_get_level() > 0) {
            ob_end_flush();
        }

        header('Content-Type: text/plain; charset=utf-8');
        header('Cache-Control: no-cache, no-store, must-revalidate');
        header('X-Accel-Buffering: no');

        echo str_repeat(" ", 1024) . "\n";
        echo "=== Initializing Station Update ===\n";
        flush();

        if (!file_exists($this->updater_script)) {
            echo "Error: Updater script not found at " . $this->updater_script . "\n";
            return;
        }

        $cmd = 'sudo ' . escapeshellarg($this->updater_script) . ' 2>&1';
        $descriptors = [
            0 => ['pipe', 'r'],
            1 => ['pipe', 'w'],
            2 => ['pipe', 'w']
        ];

        $process = proc_open($cmd, $descriptors, $pipes);

        if (is_resource($process)) {
            fclose($pipes[0]);

            while (!feof($pipes[1])) {
                $chunk = fgets($pipes[1]);
                if ($chunk !== false) {
                    echo $chunk;
                    flush();
                }
            }

            fclose($pipes[1]);
            fclose($pipes[2]);

            $exit_code = proc_close($process);
            echo "\n===================================\n";
            if ($exit_code === 0) {
                echo "Result: Complete (Exit 0)\n";
            } else {
                echo "Result: Failed (Exit " . $exit_code . ")\n";
            }
            flush();
        } else {
            echo "Error: Unable to fork update process.\n";
        }
    }

    public function name() { return $this->name; }
    public function links() { return $this->links; }
    public function html() { return $this->html; }
}
?>