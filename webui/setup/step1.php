<?php
$state_file = __DIR__ . '/state.json';
require_once __DIR__ . '/reboot_helper.php';

// Intercept background reboot trigger and healthcheck ping requests
handle_reboot_logic('step2.php');

// Gatekeeper check: Ensure user belongs on step 1 (bypassed if rebooting)
if (!isset($_GET['rebooting'])) {
    if (file_exists($state_file)) {
        $state = json_decode(file_get_contents($state_file), true);
        $active_step = $state['step'] ?? 1;
        if ($active_step > 1) {
            header("Location: step{$active_step}.php");
            exit;
        }
    }
}

$error = '';
$current_host = trim(shell_exec('hostname'));

// Handle Skip Action (No reboot needed)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['skip_step'])) {
    file_put_contents($state_file, json_encode([
        'step' => 2,
        'hostname' => $current_host,
        'skipped_step1' => true,
        'updated_at' => time()
    ], JSON_PRETTY_PRINT));

    header('Location: step2.php');
    exit;
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_hostname'])) {
    $input_host = trim($_POST['hostname'] ?? '');
    $clean_host = strtolower(preg_replace('/[^a-zA-Z0-9\-]/', '', $input_host));
    $clean_host = trim($clean_host, '-');

    if (empty($clean_host)) {
        $error = 'Please enter a valid hostname using only letters, numbers, or hyphens.';
    } elseif (strlen($clean_host) > 63) {
        $error = 'Hostname must be 63 characters or fewer.';
    } elseif ($clean_host === $current_host) {
        file_put_contents($state_file, json_encode([
            'step' => 2,
            'hostname' => $clean_host,
            'updated_at' => time()
        ], JSON_PRETTY_PRINT));
        header('Location: step2.php');
        exit;
    } else {
        // 1. Update /etc/hostname
        file_put_contents('/tmp/hostname.tmp', $clean_host . "\n");
        shell_exec('cat /tmp/hostname.tmp | sudo tee /etc/hostname > /dev/null');
        @unlink('/tmp/hostname.tmp');

        // 2. Update /etc/hosts
        $hosts_content = file_get_contents('/etc/hosts');
        $hosts_content = str_replace($current_host, $clean_host, $hosts_content);
        file_put_contents('/tmp/hosts.tmp', $hosts_content);
        shell_exec('cat /tmp/hosts.tmp | sudo tee /etc/hosts > /dev/null');
        @unlink('/tmp/hosts.tmp');

        // 3. Advance state machine to Step 2
        file_put_contents($state_file, json_encode([
            'step' => 2,
            'hostname' => $clean_host,
            'updated_at' => time()
        ], JSON_PRETTY_PRINT));

        // 4. Redirect into reboot countdown mode
        header('Location: step1.php?rebooting=1&new_host=' . urlencode($clean_host));
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Step 1: Station Identity</title>
    <style>
        * { box-sizing: border-box; }
        body {
            margin: 0;
            padding: 24px 16px;
            background: #0f1117;
            color: #f0f3f6;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
        }
        .container {
            width: 100%;
            max-width: 520px;
            background: #181c24;
            border: 1px solid #28303f;
            border-radius: 16px;
            padding: 32px 24px;
            box-shadow: 0 12px 32px rgba(0,0,0,0.45);
        }
        .badge {
            display: inline-block;
            background: rgba(0, 212, 255, 0.12);
            color: #00d4ff;
            border: 1px solid rgba(0, 212, 255, 0.3);
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 0.78rem;
            font-weight: 700;
            letter-spacing: 1px;
            text-transform: uppercase;
            margin-bottom: 16px;
        }
        h1 {
            font-size: 1.85rem;
            line-height: 1.25;
            margin: 0 0 8px 0;
            color: #ffffff;
            letter-spacing: -0.5px;
        }
        p.subtitle {
            color: #8b949e;
            font-size: 0.95rem;
            line-height: 1.5;
            margin: 0 0 24px 0;
        }
        .notice-card {
            background: rgba(234, 179, 8, 0.08);
            border: 1px solid rgba(234, 179, 8, 0.25);
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 24px;
            display: flex;
            align-items: flex-start;
            gap: 12px;
        }
        .notice-icon {
            font-size: 1.3rem;
            line-height: 1;
            flex-shrink: 0;
        }
        .notice-text {
            font-size: 0.88rem;
            line-height: 1.45;
            color: #e2c044;
        }
        .notice-text strong {
            color: #ffd84d;
        }
        .error-card {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #f87171;
            padding: 14px;
            border-radius: 10px;
            font-size: 0.9rem;
            margin-bottom: 20px;
        }
        label {
            display: block;
            font-size: 0.9rem;
            font-weight: 600;
            margin-bottom: 8px;
            color: #c9d1d9;
        }
        input[type="text"] {
            width: 100%;
            padding: 14px;
            background: #202632;
            border: 1px solid #364154;
            border-radius: 10px;
            color: #ffffff;
            font-size: 1.05rem;
            font-weight: 500;
            margin-bottom: 12px;
        }
        input[type="text"]:focus {
            border-color: #00d4ff;
            outline: none;
            box-shadow: 0 0 0 3px rgba(0, 212, 255, 0.15);
        }
        .url-box {
            background: #202632;
            border: 1px dashed #3a475d;
            border-radius: 10px;
            padding: 14px;
            margin-bottom: 24px;
        }
        .url-box-header {
            font-size: 0.78rem;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: #8b949e;
            font-weight: 700;
            margin-bottom: 6px;
        }
        .future-url-link {
            display: inline-block;
            font-family: monospace;
            font-size: 1.05rem;
            color: #00d4ff;
            text-decoration: underline;
            word-break: break-all;
            padding: 4px 0;
            cursor: pointer;
        }
        .future-url-link:hover {
            color: #4fe3ff;
        }
        .bookmark-note {
            font-size: 0.82rem;
            color: #8b949e;
            margin-top: 8px;
            line-height: 1.4;
        }
        .bookmark-note span {
            color: #c9d1d9;
        }
        .btn-submit {
            display: block;
            width: 100%;
            padding: 18px;
            background: #00d4ff;
            color: #050b14;
            border: none;
            border-radius: 12px;
            font-size: 1.15rem;
            font-weight: 700;
            text-align: center;
            cursor: pointer;
            box-shadow: 0 4px 18px rgba(0, 212, 255, 0.3);
            transition: transform 0.1s ease, background-color 0.15s ease;
            -webkit-tap-highlight-color: transparent;
        }
        .btn-submit:active {
            transform: scale(0.98);
            background: #00bce3;
        }
        .btn-skip {
            display: block;
            width: 100%;
            padding: 14px;
            margin-top: 12px;
            background: transparent;
            color: #8b949e;
            border: 1px solid #30363d;
            border-radius: 12px;
            font-size: 0.95rem;
            font-weight: 600;
            text-align: center;
            cursor: pointer;
            transition: background 0.15s ease, color 0.15s ease, border-color 0.15s ease;
            -webkit-tap-highlight-color: transparent;
        }
        .btn-skip:hover {
            background: #21262d;
            color: #c9d1d9;
            border-color: #484f58;
        }
        .btn-skip:active {
            transform: scale(0.98);
        }
        /* Reboot Helper View Styles */
        .reboot-view { text-align: center; padding: 16px 0; }
        .spinner { margin: 20px auto 28px; width: 52px; height: 52px; border: 4px solid #232a37; border-top: 4px solid #00d4ff; border-radius: 50%; animation: spin 1s linear infinite; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        .countdown { font-size: 2.8rem; font-weight: 800; color: #00d4ff; margin: 12px 0; font-variant-numeric: tabular-nums; }
        .status-msg { font-size: 1.15rem; font-weight: 600; color: #00d4ff; margin: 14px 0; min-height: 28px; }
        .reboot-meta { font-size: 0.88rem; color: #8b949e; line-height: 1.5; margin-top: 16px; }
        .bailout-card { display: none; background: rgba(239, 68, 68, 0.08); border: 1px solid rgba(239, 68, 68, 0.25); border-radius: 12px; padding: 20px 16px; margin-top: 24px; text-align: left; }
        .bailout-card h3 { color: #f87171; margin: 0 0 8px 0; font-size: 1rem; }
        .bailout-card p { font-size: 0.85rem; color: #c9d1d9; line-height: 1.5; margin: 0 0 14px 0; }
    </style>
</head>
<body>

<div class="container">

<?php if (isset($_GET['rebooting'])): ?>
    <?php
    $target_host = $_GET['new_host'] ?? $current_host;
    $destination = 'http://' . $target_host . '.local/setup/step2.php';
    render_reboot_screen($destination, $target_host);
    ?>
<?php else: ?>
    <div class="badge">Step 1 of 4</div>
    <h1>Station Identity</h1>
    <p class="subtitle">Choose a unique local name for this broadcast unit.</p>

    <div class="notice-card">
        <div class="notice-icon">⚡</div>
        <div class="notice-text">
            <strong>Reboot Notice:</strong> Changing the hostname requires a restart (~30s). If you like the current name, use the skip button below.
        </div>
    </div>

    <?php if (!empty($error)): ?>
        <div class="error-card"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
        <label for="hostname">Station Hostname</label>
        <input 
            type="text" 
            id="hostname" 
            name="hostname" 
            value="<?= htmlspecialchars($current_host) ?>" 
            required 
            maxlength="63"
            autocomplete="off"
            autocorrect="off"
            autocapitalize="none"
            spellcheck="false"
        >

        <div class="url-box">
            <div class="url-box-header">Future Local Address</div>
            <a id="futureLink" class="future-url-link" href="http://<?= htmlspecialchars($current_host) ?>.local/setup/" target="_blank" rel="noopener noreferrer">
                http://<?= htmlspecialchars($current_host) ?>.local/setup/
            </a>
            <div class="bookmark-note">
                📌 <span>Save or copy this link:</span> You will use it to access this setup portal and the station once rebooted.
            </div>
        </div>

        <button type="submit" name="save_hostname" value="1" class="btn-submit">Save & Restart to Step 2</button>
        <button type="submit" name="skip_step" value="1" class="btn-skip" formnovalidate>Keep Current Hostname (Skip)</button>
    </form>

    <?php render_emergency_reset(); ?>

    <script>
        const input = document.getElementById('hostname');
        const futureLink = document.getElementById('futureLink');

        input.addEventListener('input', () => {
            const sanitized = input.value.toLowerCase().replace(/[^a-z0-9\-]/g, '');
            const targetHost = sanitized || 'station';
            const targetUrl = 'http://' + targetHost + '.local/setup/';
            futureLink.innerText = targetUrl;
            futureLink.href = targetUrl;
        });
    </script>
<?php endif; ?>

</div>

</body>
</html>