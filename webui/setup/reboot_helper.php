<?php
// Shared reboot handler & UI generator

function render_emergency_reset() {
    ?>
    <div style="margin-top: 32px; padding-top: 20px; border-top: 1px solid #232a37; text-align: center;">
        <a href="index.php?action=reset" 
           onclick="return confirm('Emergency Reset: This will clear setup progress and start over at Step 1. Are you sure?');" 
           style="display: inline-block; font-size: 0.8rem; color: #ef4444; text-decoration: none; padding: 8px 14px; border: 1px solid rgba(239, 68, 68, 0.25); border-radius: 8px; background: rgba(239, 68, 68, 0.05); transition: all 0.15s ease;">
            ⚠️ Reset Setup Process
        </a>
    </div>
    <?php
}

function handle_reboot_logic($next_step_url, $target_host = null) {
    // 1. Background trigger executed by fetch()
    if (isset($_POST['action']) && $_POST['action'] === 'execute_reboot') {
        shell_exec('sudo /sbin/reboot');
        echo json_encode(['status' => 'rebooting']);
        exit;
    }

    // 2. Healthcheck ping endpoint
    if (isset($_GET['action']) && $_GET['action'] === 'ping') {
        header('Content-Type: application/json');
        header('Access-Control-Allow-Origin: *');
        echo json_encode(['status' => 'ok']);
        exit;
    }
}

function render_reboot_screen($next_step_url, $target_host = null) {
    if (!$target_host) {
        $target_host = trim(shell_exec('hostname'));
    }
    ?>
    <div class="reboot-view">
        <div class="badge">System Restart</div>
        <h1 id="rebootTitle">Restarting Station</h1>
        <p class="subtitle" id="rebootSubtitle">Applying changes and rebooting...</p>

        <div class="spinner" id="spinner"></div>

        <div class="countdown" id="timer">30</div>
        <div class="status-msg" id="statusMessage"></div>

        <div class="reboot-meta" id="rebootMeta">
            Target Address: <br>
            <strong style="color: #ffffff;">http://<?= htmlspecialchars($target_host) ?>.local/setup/</strong>
        </div>

        <div class="bailout-card" id="bailoutCard">
            <h3>Device Reconnect Timed Out</h3>
            <p>The Pi took longer than expected to report back online.</p>
            <p><strong>Next Steps:</strong></p>
            <ul style="font-size: 0.85rem; color: #8b949e; padding-left: 20px; margin: 0 0 16px 0; line-height: 1.6;">
                <li>Try opening the link directly: <a href="<?= htmlspecialchars($next_step_url) ?>" style="color: #00d4ff;"><?= htmlspecialchars($next_step_url) ?></a></li>
                <li>Or access the portal via IP: <code>http://&lt;pi-ip&gt;/setup/</code></li>
            </ul>
            <a href="<?= htmlspecialchars($next_step_url) ?>" class="btn-submit" style="font-size: 1rem; padding: 14px;">Try Direct Link</a>
        </div>
    </div>

    <script>
        // Trigger reboot via background POST
        fetch(window.location.pathname, {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=execute_reboot'
        });

        let countdownSec = 30;
        let pingCount = 0;
        let isRedirecting = false;
        const maxPingSeconds = 30;

        const nextUrl = "<?= $next_step_url ?>";
        const pingUrl = window.location.pathname + '?action=ping&nocache=' + Date.now();

        const timerElem = document.getElementById('timer');
        const statusElem = document.getElementById('statusMessage');
        const rebootTitle = document.getElementById('rebootTitle');
        const spinner = document.getElementById('spinner');
        const bailoutCard = document.getElementById('bailoutCard');

        // Phase 1: 30-Second Hardware Reboot Timer
        const rebootInterval = setInterval(() => {
            countdownSec--;
            timerElem.innerText = countdownSec;

            if (countdownSec <= 0) {
                clearInterval(rebootInterval);
                timerElem.style.display = 'none';
                rebootTitle.innerText = 'Connecting to Station';
                startHealthCheck();
            }
        }, 1000);

        // Phase 2: Active Ping Loop
        function startHealthCheck() {
            const pingInterval = setInterval(async () => {
                if (isRedirecting) {
                    clearInterval(pingInterval);
                    return;
                }

                pingCount++;
                statusElem.innerText = `Awaiting connection... (${pingCount})`;

                try {
                    const controller = new AbortController();
                    const timeoutId = setTimeout(() => controller.abort(), 1800);

                    await fetch(pingUrl, {
                        method: 'GET',
                        mode: 'no-cors',
                        cache: 'no-store',
                        signal: controller.signal
                    });

                    clearTimeout(timeoutId);

                    isRedirecting = true;
                    clearInterval(pingInterval);
                    statusElem.innerText = 'Connected! Loading next step...';
                    window.location.href = nextUrl;
                } catch (e) {
                    // Waiting for HTTP listener
                }

                // Phase 3: Bailout after 30 attempts (60s elapsed)
                if (pingCount >= maxPingSeconds && !isRedirecting) {
                    clearInterval(pingInterval);
                    spinner.style.display = 'none';
                    statusElem.innerText = 'Connection taking longer than expected.';
                    statusElem.style.color = '#f87171';
                    bailoutCard.style.display = 'block';
                }
            }, 1000);
        }
    </script>
    <?php
}