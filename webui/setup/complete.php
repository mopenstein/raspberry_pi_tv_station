<?php
$state_file = __DIR__ . '/state.json';
$state = file_exists($state_file) ? json_decode(file_get_contents($state_file), true) : [];

$current_host = trim(shell_exec('hostname'));
$current_ip = trim(shell_exec("hostname -I | awk '{print $1}'"));
$timezone = trim(shell_exec('timedatectl show -p Timezone --value 2>/dev/null')) ?: 'UTC';

// Check credential status from Step 5 state
$password_status = !empty($state['password_updated']) ? 'Custom Password Set' : 'Default / Unchanged';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Setup Complete</title>
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
            padding: 36px 24px;
            box-shadow: 0 12px 32px rgba(0,0,0,0.45);
            text-align: center;
        }
        .success-icon {
            width: 64px;
            height: 64px;
            background: rgba(34, 197, 94, 0.12);
            border: 1px solid rgba(34, 197, 94, 0.3);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 2rem;
            color: #4ade80;
            margin: 0 auto 20px;
        }
        h1 {
            font-size: 1.9rem;
            line-height: 1.25;
            margin: 0 0 8px 0;
            color: #ffffff;
        }
        p.subtitle {
            color: #8b949e;
            font-size: 0.95rem;
            margin: 0 0 28px 0;
        }
        .summary-card {
            background: #202632;
            border: 1px solid #2e3748;
            border-radius: 12px;
            padding: 16px;
            text-align: left;
            margin-bottom: 28px;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .summary-row {
            display: flex;
            justify-content: space-between;
            font-size: 0.88rem;
            border-bottom: 1px solid #28303f;
            padding-bottom: 8px;
        }
        .summary-row:last-child {
            border-bottom: none;
            padding-bottom: 0;
        }
        .summary-label { color: #8b949e; }
        .summary-val { color: #ffffff; font-weight: 600; font-family: monospace; }
        .btn-dashboard {
            display: block;
            width: 100%;
            padding: 18px;
            background: #00d4ff;
            color: #050b14;
            text-decoration: none;
            border-radius: 12px;
            font-size: 1.15rem;
            font-weight: 700;
            box-shadow: 0 4px 18px rgba(0, 212, 255, 0.3);
            transition: background-color 0.15s ease;
        }
        .btn-dashboard:hover { background: #00bce3; }
        .btn-reset {
            display: inline-block;
            margin-top: 20px;
            color: #6e7681;
            font-size: 0.82rem;
            text-decoration: none;
        }
        .btn-reset:hover { color: #ef4444; }
    </style>
</head>
<body>

<div class="container">
    <div class="success-icon">✓</div>
    <h1>Station Configured</h1>
    <p class="subtitle">Your TV broadcast appliance is online and ready for air.</p>

    <div class="summary-card">
        <div class="summary-row">
            <span class="summary-label">Station Hostname</span>
            <span class="summary-val"><?= htmlspecialchars($current_host) ?>.local</span>
        </div>
        <div class="summary-row">
            <span class="summary-label">IP Address</span>
            <span class="summary-val"><?= htmlspecialchars($current_ip ?: 'Offline') ?></span>
        </div>
        <div class="summary-row">
            <span class="summary-label">Timezone</span>
            <span class="summary-val"><?= htmlspecialchars($timezone) ?></span>
        </div>
        <div class="summary-row">
            <span class="summary-label">Storage Target</span>
            <span class="summary-val">/media/pi/drive_*</span>
        </div>
        <div class="summary-row">
            <span class="summary-label">System Credentials</span>
            <span class="summary-val"><?= htmlspecialchars($password_status) ?></span>
        </div>
    </div>

    <a href="/" class="btn-dashboard">Launch Station Interface</a>
    <br>
    <a href="index.php?action=reset" class="btn-reset" onclick="return confirm('Run setup wizard again?');">Rerun Setup Wizard</a>
</div>

</body>
</html>