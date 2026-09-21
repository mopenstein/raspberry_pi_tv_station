<?php
$state_file = __DIR__ . '/state.json';
require_once __DIR__ . '/reboot_helper.php';

// Handle reboot & ping healthcheck requests
handle_reboot_logic('step3.php');

// Gatekeeper check: Ensure state belongs on Step 2
if (!isset($_GET['rebooting'])) {
    if (file_exists($state_file)) {
        $state = json_decode(file_get_contents($state_file), true);
        $active_step = $state['step'] ?? 2;
        if ($active_step < 2) {
            header('Location: step1.php');
            exit;
        } elseif ($active_step > 2) {
            header("Location: step{$active_step}.php");
            exit;
        }
    }
}

$error = '';
$current_tz = trim(shell_exec('timedatectl show -p Timezone --value 2>/dev/null'));
if (empty($current_tz)) {
    $current_tz = trim(shell_exec('cat /etc/timezone 2>/dev/null')) ?: 'UTC';
}

// Handle Skip Action (No reboot needed)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['skip_step'])) {
    $state = file_exists($state_file) ? json_decode(file_get_contents($state_file), true) : [];
    $state['step'] = 3;
    $state['timezone'] = $current_tz;
    $state['skipped_step2'] = true;
    $state['updated_at'] = time();
    file_put_contents($state_file, json_encode($state, JSON_PRETTY_PRINT));

    header('Location: step3.php');
    exit;
}

// Handle Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_timezone'])) {
    $tz = trim($_POST['timezone'] ?? '');
    $sync_clock = isset($_POST['sync_clock']);
    $browser_time = trim($_POST['browser_time'] ?? '');

    $valid_timezones = DateTimeZone::listIdentifiers();
    if (!in_array($tz, $valid_timezones)) {
        $error = 'Please select a valid timezone from the list.';
    } elseif ($tz === $current_tz && !$sync_clock) {
        // Nothing changed, skip reboot
        $state = file_exists($state_file) ? json_decode(file_get_contents($state_file), true) : [];
        $state['step'] = 3;
        $state['timezone'] = $tz;
        $state['updated_at'] = time();
        file_put_contents($state_file, json_encode($state, JSON_PRETTY_PRINT));
        header('Location: step3.php');
        exit;
    } else {
        // 1. Update system timezone
        shell_exec('sudo /usr/bin/timedatectl set-timezone ' . escapeshellarg($tz));

        // 2. Sync system clock if requested
        if ($sync_clock && !empty($browser_time) && ctype_digit($browser_time)) {
            shell_exec('sudo /bin/date -s "@' . escapeshellarg($browser_time) . '" > /dev/null');
            shell_exec('sudo /sbin/hwclock -w 2>/dev/null');
        }

        // 3. Advance state machine to Step 3
        $state = file_exists($state_file) ? json_decode(file_get_contents($state_file), true) : [];
        $state['step'] = 3;
        $state['timezone'] = $tz;
        $state['updated_at'] = time();
        file_put_contents($state_file, json_encode($state, JSON_PRETTY_PRINT));

        // 4. Trigger reboot cycle
        header('Location: step2.php?rebooting=1');
        exit;
    }
}

// Group common zones for easy manual scanning
$grouped_timezones = [
    'United States & Canada' => [
        'America/New_York' => 'Eastern Time (New York, Toronto, Miami)',
        'America/Detroit' => 'Eastern Time - Michigan',
        'America/Chicago' => 'Central Time (Chicago, Dallas, Winnipeg)',
        'America/Denver' => 'Mountain Time (Denver, Calgary, Salt Lake)',
        'America/Phoenix' => 'Mountain Time - Arizona (No DST)',
        'America/Los_Angeles' => 'Pacific Time (Los Angeles, Vancouver, Seattle)',
        'America/Anchorage' => 'Alaska Time',
        'Pacific/Honolulu' => 'Hawaii Time (No DST)'
    ],
    'Europe & UK' => [
        'Europe/London' => 'London / GMT / BST',
        'Europe/Dublin' => 'Dublin',
        'Europe/Paris' => 'Paris / Berlin / Rome / Madrid (CET)',
        'Europe/Amsterdam' => 'Amsterdam / Brussels / Vienna',
        'Europe/Athens' => 'Athens / Helsinki / Bucharest (EET)'
    ],
    'Australia & Pacific' => [
        'Australia/Sydney' => 'Sydney / Melbourne / Canberra (AEST)',
        'Australia/Brisbane' => 'Brisbane (AEST - No DST)',
        'Australia/Adelaide' => 'Adelaide (ACST)',
        'Australia/Perth' => 'Perth (AWST)',
        'Pacific/Auckland' => 'Auckland / Wellington (NZST)'
    ],
    'Standard / Universal' => [
        'UTC' => 'Coordinated Universal Time (UTC)'
    ]
];

$all_zones = DateTimeZone::listIdentifiers();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Step 2: Clock & Timezone</title>
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
            margin-bottom: 20px;
            display: flex;
            align-items: flex-start;
            gap: 12px;
        }
        .match-card {
            display: none;
            background: rgba(34, 197, 94, 0.1);
            border: 1px solid rgba(34, 197, 94, 0.3);
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 20px;
            color: #4ade80;
            font-size: 0.88rem;
            line-height: 1.45;
        }
        .match-card strong {
            color: #86efac;
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
        .clock-box {
            background: #202632;
            border: 1px solid #2e3748;
            border-radius: 12px;
            padding: 16px;
            margin-bottom: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .clock-label {
            font-size: 0.82rem;
            color: #8b949e;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
        }
        .clock-display {
            font-family: monospace;
            font-size: 1.15rem;
            color: #00d4ff;
            font-weight: 700;
        }
        label.input-label {
            display: block;
            font-size: 0.9rem;
            font-weight: 600;
            margin-bottom: 8px;
            color: #c9d1d9;
        }
        select {
            width: 100%;
            padding: 14px;
            background: #202632;
            border: 1px solid #364154;
            border-radius: 10px;
            color: #ffffff;
            font-size: 1rem;
            margin-bottom: 20px;
            appearance: none;
            -webkit-appearance: none;
            background-image: url("data:image/svg+xml;charset=UTF-8,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%238b949e' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3e%3cpolyline points='6 9 12 15 18 9'%3e%3c/polyline%3e%3c/svg%3e");
            background-repeat: no-repeat;
            background-position: right 14px center;
            background-size: 16px;
        }
        select:focus {
            border-color: #00d4ff;
            outline: none;
            box-shadow: 0 0 0 3px rgba(0, 212, 255, 0.15);
        }
        .checkbox-container {
            display: flex;
            align-items: center;
            gap: 12px;
            background: #202632;
            border: 1px solid #2e3748;
            padding: 14px;
            border-radius: 10px;
            margin-bottom: 24px;
            cursor: pointer;
        }
        .checkbox-container input[type="checkbox"] {
            width: 20px;
            height: 20px;
            accent-color: #00d4ff;
            cursor: pointer;
            flex-shrink: 0;
        }
        .checkbox-text {
            font-size: 0.88rem;
            color: #c9d1d9;
            line-height: 1.4;
        }
        .checkbox-text small {
            display: block;
            color: #8b949e;
            font-size: 0.78rem;
            margin-top: 2px;
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
            transition: all 0.15s ease;
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
        /* Highlighted skip button style when timezone already matches */
        .btn-skip-recommended {
            border-color: rgba(34, 197, 94, 0.4);
            color: #4ade80;
            background: rgba(34, 197, 94, 0.05);
        }
        .btn-skip-recommended:hover {
            background: rgba(34, 197, 94, 0.12);
            color: #86efac;
            border-color: rgba(34, 197, 94, 0.6);
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
    <?php render_reboot_screen('step3.php'); ?>
<?php else: ?>
    <div class="badge">Step 2 of 5</div>
    <h1>Clock & Timezone</h1>
    <p class="subtitle">Select your local region to ensure scheduled broadcasts and bumpers air at the right time.</p>

    <!-- DYNAMIC MATCH ALERT (Hidden by default, shown via JS if matches) -->
    <div class="match-card" id="matchCard">
        ✓ <strong>Timezone Match Detected:</strong> Your browser and the Raspberry Pi are both set to <span id="matchTzLabel"></span>. We recommend skipping this step to bypass a reboot.
    </div>

    <!-- STANDARD NOTICE -->
    <div class="notice-card" id="noticeCard">
        <div class="notice-icon">⚡</div>
        <div class="notice-text">
            <strong>Reboot Notice:</strong> Updating time and system services requires a restart (~30s). Skip if already correct.
        </div>
    </div>

    <div class="clock-box">
        <div>
            <div class="clock-label">Active Pi Timezone</div>
            <div style="font-size: 0.82rem; color: #8b949e;"><?= htmlspecialchars($current_tz) ?></div>
        </div>
        <div class="clock-display">
            <?= date('H:i:s') ?>
        </div>
    </div>

    <?php if (!empty($error)): ?>
        <div class="error-card"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST" id="tzForm">
        <input type="hidden" name="browser_time" id="browserTime" value="">

        <label class="input-label" for="timezone">Choose Timezone</label>
        <select id="timezone" name="timezone" required>
            <?php foreach ($grouped_timezones as $region => $zones): ?>
                <optgroup label="<?= htmlspecialchars($region) ?>">
                    <?php foreach ($zones as $tz_id => $label): ?>
                        <option value="<?= htmlspecialchars($tz_id) ?>" <?= ($tz_id === $current_tz) ? 'selected' : '' ?>>
                            <?= htmlspecialchars($label) ?>
                        </option>
                    <?php endforeach; ?>
                </optgroup>
            <?php endforeach; ?>

            <optgroup label="All World Timezones (Alphabetical)">
                <?php foreach ($all_zones as $tz_id): ?>
                    <option value="<?= htmlspecialchars($tz_id) ?>" <?= ($tz_id === $current_tz) ? 'selected' : '' ?>>
                        <?= htmlspecialchars($tz_id) ?>
                    </option>
                <?php endforeach; ?>
            </optgroup>
        </select>

        <label class="checkbox-container">
            <input type="checkbox" name="sync_clock" value="1" checked>
            <div class="checkbox-text">
                Sync Pi clock to this device's current time
                <small>Fixes 1970 date reset if the station is currently disconnected from the internet.</small>
            </div>
        </label>

        <button type="submit" name="save_timezone" value="1" class="btn-submit">Save Time & Restart to Step 3</button>
        <button type="submit" name="skip_step" value="1" id="btnSkip" class="btn-skip" formnovalidate>Keep Current Time (Skip)</button>
    </form>

    <?php render_emergency_reset(); ?>

    <script>
        document.getElementById('browserTime').value = Math.floor(Date.now() / 1000);

        const currentPiTz = "<?= addslashes($current_tz) ?>";
        const browserTz = Intl.DateTimeFormat().resolvedOptions().timeZone;
        const matchCard = document.getElementById('matchCard');
        const noticeCard = document.getElementById('noticeCard');
        const btnSkip = document.getElementById('btnSkip');
        const matchLabel = document.getElementById('matchTzLabel');

        // Check if browser timezone matches the Raspberry Pi's current setting
        if (browserTz && currentPiTz && browserTz.toLowerCase() === currentPiTz.toLowerCase()) {
            matchLabel.innerText = currentPiTz;
            matchCard.style.display = 'block';
            noticeCard.style.display = 'none';
            btnSkip.classList.add('btn-skip-recommended');
            btnSkip.innerText = 'Keep ' + currentPiTz + ' (Skip Step)';
        }
    </script>
<?php endif; ?>

</div>

</body>
</html>