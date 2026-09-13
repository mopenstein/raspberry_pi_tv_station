<?php
$state_file = __DIR__ . '/state.json';

// Allow manual reset back to step 1
if (isset($_GET['action']) && $_GET['action'] === 'reset') {
    if (file_exists($state_file)) {
        unlink($state_file);
    }
    header('Location: index.php');
    exit;
}

// Resume wizard if a step is already in flight
if (file_exists($state_file)) {
    $state = json_decode(file_get_contents($state_file), true);
    $active_step = $state['step'] ?? null;

    if ($active_step === 1) {
        header('Location: step1.php');
        exit;
    } elseif ($active_step === 2) {
        header('Location: step2.php');
        exit;
    } elseif ($active_step === 3) {
        header('Location: step3.php');
        exit;
    } elseif ($active_step === 4) {
        header('Location: step4.php');
        exit;
    } elseif ($active_step === 'complete') {
        header('Location: complete.php');
        exit;
    }
}

// Initialize state and launch step 1
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['start'])) {
    file_put_contents($state_file, json_encode([
        'step' => 1,
        'started_at' => time()
    ], JSON_PRETTY_PRINT));

    header('Location: step1.php');
    exit;
}

$current_host = trim(shell_exec('hostname'));
$current_ip = trim(shell_exec("hostname -I | awk '{print $1}'"));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>TV Station Setup</title>
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
            margin: 0 0 10px 0;
            color: #ffffff;
            letter-spacing: -0.5px;
        }
        p.subtitle {
            color: #8b949e;
            font-size: 1rem;
            line-height: 1.5;
            margin: 0 0 28px 0;
        }
        .step-list {
            list-style: none;
            padding: 0;
            margin: 0 0 32px 0;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }
        .step-item {
            display: flex;
            align-items: center;
            background: #202632;
            border: 1px solid #2e3748;
            padding: 16px;
            border-radius: 12px;
        }
        .step-num {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: #00d4ff;
            color: #0b0f19;
            font-weight: 800;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.05rem;
            flex-shrink: 0;
            margin-right: 16px;
        }
        .step-info {
            display: flex;
            flex-direction: column;
        }
        .step-title {
            font-size: 1.05rem;
            font-weight: 600;
            color: #ffffff;
        }
        .step-desc {
            font-size: 0.85rem;
            color: #8b949e;
            margin-top: 2px;
        }
        .meta-bar {
            display: flex;
            justify-content: space-between;
            font-size: 0.82rem;
            color: #6e7681;
            padding: 12px 4px;
            border-top: 1px solid #232a37;
            margin-bottom: 24px;
        }
        .btn-start {
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
        .btn-start:active {
            transform: scale(0.98);
            background: #00bce3;
        }
    </style>
</head>
<body>

<div class="container">
    <div class="badge">Appliance Initializer</div>
    <h1>Station Setup</h1>
    <p class="subtitle">Raspberry Pi Broadcast TV Station Setup Wizard.</p>

    <ul class="step-list">
        <li class="step-item">
            <div class="step-num">1</div>
            <div class="step-info">
                <span class="step-title">Station Identity</span>
                <span class="step-desc">Set custom local hostname</span>
            </div>
        </li>
        <li class="step-item">
            <div class="step-num">2</div>
            <div class="step-info">
                <span class="step-title">Clock & Timezone</span>
                <span class="step-desc">Set broadcast region & sync clock</span>
            </div>
        </li>
        <li class="step-item">
            <div class="step-num">3</div>
            <div class="step-info">
                <span class="step-title">Media Storage</span>
                <span class="step-desc">Attach USB drive & persist mount</span>
            </div>
        </li>
        <li class="step-item">
            <div class="step-num">4</div>
            <div class="step-info">
                <span class="step-title">Wi-Fi Connection</span>
                <span class="step-desc">Join local wireless network</span>
            </div>
        </li>
    </ul>

    <div class="meta-bar">
        <span>Current Host: <strong><?= htmlspecialchars($current_host) ?></strong></span>
        <span>IP: <strong><?= htmlspecialchars($current_ip ?: 'No IP') ?></strong></span>
    </div>

    <form method="POST">
        <button type="submit" name="start" value="1" class="btn-start">Begin Setup</button>
    </form>
</div>

</body>
</html>