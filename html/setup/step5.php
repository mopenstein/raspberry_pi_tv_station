<?php
$state_file = __DIR__ . '/state.json';
require_once __DIR__ . '/reboot_helper.php';

// Handle reboot countdown and healthcheck ping routing to completion
handle_reboot_logic('complete.php');

// Gatekeeper check: Ensure user belongs on Step 5
if (!isset($_GET['rebooting'])) {
    if (file_exists($state_file)) {
        $state = json_decode(file_get_contents($state_file), true);
        $active_step = $state['step'] ?? 5;
        if ($active_step < 5) {
            header("Location: step{$active_step}.php");
            exit;
        } elseif ($active_step === 'complete') {
            header("Location: complete.php");
            exit;
        }
    }
}

$error = '';

function update_linux_password(string $username, string $password): bool {
    $descriptor_spec = [
        0 => ["pipe", "r"],
        1 => ["pipe", "w"],
        2 => ["pipe", "w"],
    ];

    $process = proc_open('sudo /usr/sbin/chpasswd', $descriptor_spec, $pipes);

    if (is_resource($process)) {
        fwrite($pipes[0], "{$username}:{$password}\n");
        fclose($pipes[0]);

        stream_get_contents($pipes[1]);
        fclose($pipes[1]);

        $stderr = stream_get_contents($pipes[2]);
        fclose($pipes[2]);

        return proc_close($process) === 0;
    }

    return false;
}

// Handle Skip Action (Keep existing credentials, no reboot)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['skip_step'])) {
    $state = file_exists($state_file) ? json_decode(file_get_contents($state_file), true) : [];
    $state['step'] = 'complete';
    $state['skipped_step5'] = true;
    $state['completed_at'] = time();
    file_put_contents($state_file, json_encode($state, JSON_PRETTY_PRINT));

    header('Location: complete.php');
    exit;
}

// Handle Password Save & System Update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_password'])) {
    $password = $_POST['password'] ?? '';
    $confirm = $_POST['password_confirm'] ?? '';

    if (empty($password)) {
        $error = 'Please enter a password or select Skip.';
    } elseif (strlen($password) < 6) {
        $error = 'Password must be at least 6 characters long.';
    } elseif ($password !== $confirm) {
        $error = 'Passwords do not match.';
    } else {
        if (update_linux_password('pi', $password)) {
            $state = file_exists($state_file) ? json_decode(file_get_contents($state_file), true) : [];
            $state['step'] = 'complete';
            $state['password_updated'] = true;
            $state['completed_at'] = time();
            file_put_contents($state_file, json_encode($state, JSON_PRETTY_PRINT));

            // Trigger reboot cycle to complete
            header('Location: step5.php?rebooting=1');
            exit;
        } else {
            $error = 'Failed to set password. Verify /etc/sudoers.d permissions for chpasswd.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Step 5: System Password</title>
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
        label.input-label {
            display: block;
            font-size: 0.9rem;
            font-weight: 600;
            margin-bottom: 8px;
            color: #c9d1d9;
        }
        .password-wrapper {
            position: relative;
            width: 100%;
            margin-bottom: 18px;
        }
        input[type="password"], input[type="text"] {
            width: 100%;
            padding: 14px;
            padding-right: 48px;
            background: #202632;
            border: 1px solid #364154;
            border-radius: 10px;
            color: #ffffff;
            font-size: 1.05rem;
        }
        input[type="password"]:focus, input[type="text"]:focus {
            border-color: #00d4ff;
            outline: none;
            box-shadow: 0 0 0 3px rgba(0, 212, 255, 0.15);
        }
        .toggle-password {
            position: absolute;
            right: 10px;
            top: 50%;
            transform: translateY(-50%);
            background: transparent;
            border: none;
            cursor: pointer;
            padding: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: #8b949e;
            transition: color 0.15s ease;
            -webkit-tap-highlight-color: transparent;
        }
        .toggle-password:hover { color: #00d4ff; }
        .toggle-password svg { width: 22px; height: 22px; fill: none; stroke: currentColor; stroke-width: 2; }
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
            margin-top: 10px;
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
        /* Reboot Helper Styles */
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
    <?php render_reboot_screen('complete.php'); ?>
<?php else: ?>
    <div class="badge">Step 5 of 5</div>
    <h1>System Password</h1>
    <p class="subtitle">Set a secure password for the standard Linux console and SSH account (<code>pi</code>).</p>

    <div class="notice-card">
        <div class="notice-icon">⚡</div>
        <div class="notice-text">
            <strong>Reboot Notice:</strong> Applying system credential changes reboots the Pi (~30s) to finalize configuration.
        </div>
    </div>

    <?php if (!empty($error)): ?>
        <div class="error-card"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
        <label class="input-label" for="passField">New Password</label>
        <div class="password-wrapper">
            <input 
                type="password" 
                id="passField" 
                name="password" 
                required 
                autocomplete="new-password"
                autocorrect="off"
                autocapitalize="none"
                spellcheck="false"
            >
            <button type="button" class="toggle-password" onclick="togglePass('passField', this)" aria-label="Toggle password">
                <svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
            </button>
        </div>

        <label class="input-label" for="confirmField">Confirm New Password</label>
        <div class="password-wrapper">
            <input 
                type="password" 
                id="confirmField" 
                name="password_confirm" 
                required 
                autocomplete="new-password"
                autocorrect="off"
                autocapitalize="none"
                spellcheck="false"
            >
            <button type="button" class="toggle-password" onclick="togglePass('confirmField', this)" aria-label="Toggle password">
                <svg viewBox="0 0 24 24"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path><circle cx="12" cy="12" r="3"></circle></svg>
            </button>
        </div>

        <button type="submit" name="save_password" value="1" class="btn-submit">Set Password & Restart to Finish</button>
        <button type="submit" name="skip_step" value="1" class="btn-skip" formnovalidate>Keep Default Password (Skip)</button>
    </form>

    <?php render_emergency_reset(); ?>

    <script>
        function togglePass(fieldId, btn) {
            const input = document.getElementById(fieldId);
            const isPassword = input.getAttribute('type') === 'password';
            input.setAttribute('type', isPassword ? 'text' : 'password');
            btn.style.color = isPassword ? '#00d4ff' : '#8b949e';
        }
    </script>
<?php endif; ?>

</div>

</body>
</html>