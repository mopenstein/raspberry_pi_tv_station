<?php

class SystemUpdater implements ManageCard {
    private $name = 'Software Updater';
    private $links = [];
    private $html = '';
    private $updater_script = '/home/pi/Desktop/update_station.sh';
    private $version_file = '/home/pi/Desktop/.manifest_version';
    private $remote_manifest_url = 'https://raw.githubusercontent.com/mopenstein/raspberry_pi_tv_station/main/assets/manifest.txt';

    public function __construct() {
        if (isset($_GET['action']) && $_GET['action'] === 'run_station_update') {
            $this->streamUpdateProcess();
            exit;
        }

        $this->renderCard();
    }

    private function getInstalledDate() {
        if (file_exists($this->version_file)) {
            $date = trim(file_get_contents($this->version_file));
            return !empty($date) ? htmlspecialchars($date) : 'Unknown';
        }
        return 'Not Recorded';
    }

    private function getRemoteDate() {
        // Fast, cached curl check to read remote date without stalling page load
        $ctx = stream_context_create([
            'http' => [
                'timeout' => 2, // 2-second ceiling so page never hangs
                'header'  => "User-Agent: PiTV-Updater\r\n"
            ]
        ]);

        $remote_head = @file_get_contents($this->remote_manifest_url, false, $ctx, 0, 512);
        if ($remote_head !== false) {
            if (preg_match('/^date:\s*(.+)$/m', $remote_head, $matches)) {
                return trim($matches[1]);
            }
        }
        return null;
    }

    private function renderCard() {
        $installed_date = $this->getInstalledDate();
        $remote_date = $this->getRemoteDate();

        $status_badge = '';
        if ($remote_date !== null) {
            if ($installed_date !== $remote_date) {
                $status_badge = '<span style="background: #f59e0b; color: #000; padding: 2px 6px; border-radius: 4px; font-size: 11px; font-weight: bold; margin-left: 6px;">Update Available: ' . htmlspecialchars($remote_date) . '</span>';
            } else {
                $status_badge = '<span style="background: #22c55e; color: #000; padding: 2px 6px; border-radius: 4px; font-size: 11px; font-weight: bold; margin-left: 6px;">Up to Date</span>';
            }
        }

        $this->html = '
        <div style="margin:5px; padding:10px;">
            <div style="display: flex; justify-content: space-between; align-items: baseline;">
                <strong>Check & Install Station Updates</strong>
                ' . $status_badge . '
            </div>
            
            <p style="margin: 6px 0 12px 0; color: #94a3b8; font-size: 12px;">
                Installed Version: <strong>' . $installed_date . '</strong>
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