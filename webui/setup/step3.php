<?php
$state_file = __DIR__ . '/state.json';
require_once __DIR__ . '/reboot_helper.php';

// Handle reboot & ping healthcheck requests
handle_reboot_logic('step4.php');

// Gatekeeper: Ensure user belongs on Step 3
if (!isset($_GET['rebooting'])) {
    if (file_exists($state_file)) {
        $state = json_decode(file_get_contents($state_file), true);
        $active_step = $state['step'] ?? 3;
        if ($active_step < 3) {
            header("Location: step{$active_step}.php");
            exit;
        } elseif ($active_step > 3) {
            header("Location: step{$active_step}.php");
            exit;
        }
    }
}

$error = '';

// Handle Skip Action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['skip_step'])) {
    $state = file_exists($state_file) ? json_decode(file_get_contents($state_file), true) : [];
    $state['step'] = 4;
    $state['skipped_step3'] = true;
    $state['updated_at'] = time();
    file_put_contents($state_file, json_encode($state, JSON_PRETTY_PRINT));

    header('Location: step4.php');
    exit;
}

// Function to scan USB partitions & assign dynamic letter targets
function get_usb_partitions() {
    $raw_json = shell_exec('lsblk -J -b -o NAME,SIZE,TYPE,MOUNTPOINT,FSTYPE,LABEL,UUID 2>/dev/null');
    $data = json_decode($raw_json, true);
    $partitions = [];

    if (!empty($data['blockdevices'])) {
        foreach ($data['blockdevices'] as $dev) {
            // Filter internal micro-SD, eMMC, zram, and loop devices
            if (strpos($dev['name'], 'mmcblk') === 0 || strpos($dev['name'], 'loop') === 0 || strpos($dev['name'], 'zram') === 0) {
                continue;
            }

            if (!empty($dev['children'])) {
                foreach ($dev['children'] as $child) {
                    if ($child['type'] === 'part' && !empty($child['uuid'])) {
                        $partitions[] = $child;
                    }
                }
            } elseif ($dev['type'] === 'part' && !empty($dev['uuid'])) {
                $partitions[] = $dev;
            }
        }
    }

    // Assign /media/pi/drive_A through drive_Z based on discovery order
    $letters = range('A', 'Z');
    foreach ($partitions as $idx => &$part) {
        $part_letter = $letters[$idx] ?? ('Z' . $idx);
        $part['target_mount'] = "/media/pi/drive_{$part_letter}";
    }

    return $partitions;
}

$partitions = get_usb_partitions();

// Handle Multi-Drive Selection & Persistent Mount
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['mount_drives'])) {
    $selected_uuids = $_POST['selected_partitions'] ?? [];

    if (empty($selected_uuids) || !is_array($selected_uuids)) {
        $error = 'Please select at least one partition to mount, or use Skip.';
    } else {
        $mount_records = [];
        $fstab_lines_to_add = [];
        $current_fstab = file_get_contents('/etc/fstab');
        $filtered_fstab_lines = [];

        // Build list of targets to sanitize from current fstab
        $targets_to_clean = [];
        $uuids_to_clean = $selected_uuids;

        foreach ($partitions as $part) {
            if (in_array($part['uuid'], $selected_uuids)) {
                $targets_to_clean[] = $part['target_mount'];
            }
        }

        // Clean out existing fstab references for selected UUIDs or mount points
        foreach (explode("\n", $current_fstab) as $line) {
            $skip_line = false;
            foreach ($uuids_to_clean as $u) {
                if (strpos($line, $u) !== false) {
                    $skip_line = true;
                    break;
                }
            }
            if (!$skip_line) {
                foreach ($targets_to_clean as $t) {
                    if (strpos($line, $t) !== false) {
                        $skip_line = true;
                        break;
                    }
                }
            }
            if (!$skip_line && trim($line) !== '') {
                $filtered_fstab_lines[] = $line;
            }
        }

        // Process each selected partition
        foreach ($partitions as $part) {
            if (!in_array($part['uuid'], $selected_uuids)) {
                continue;
            }

            $uuid = $part['uuid'];
            $fstype = $part['fstype'] ?: 'auto';
            $mount_target = $part['target_mount'];

            // 1. Create directory structure
            shell_exec('sudo mkdir -p ' . escapeshellarg($mount_target));
            shell_exec('sudo chown -R pi:www-data ' . escapeshellarg($mount_target));
            shell_exec('sudo chmod 775 ' . escapeshellarg($mount_target));

            // 2. Determine mount flags based on filesystem
            if (in_array($fstype, ['vfat', 'fat', 'exfat', 'ntfs'])) {
                $fstab_opts = 'defaults,nofail,x-systemd.device-timeout=5,uid=1000,gid=33,umask=0002';
            } else {
                $fstab_opts = 'defaults,nofail,x-systemd.device-timeout=5,noatime';
            }

            $filtered_fstab_lines[] = "UUID={$uuid}  {$mount_target}  {$fstype}  {$fstab_opts}  0  2";

            $mount_records[] = [
                'uuid' => $uuid,
                'mount_point' => $mount_target,
                'fstype' => $fstype,
                'label' => $part['label'] ?? 'STORAGE'
            ];
        }

        // 3. Write back to /etc/fstab safely
        $new_fstab_content = implode("\n", $filtered_fstab_lines) . "\n";
        file_put_contents('/tmp/fstab.tmp', $new_fstab_content);
        shell_exec('cat /tmp/fstab.tmp | sudo tee /etc/fstab > /dev/null');
        @unlink('/tmp/fstab.tmp');

        // 4. Reload systemd mount engine and test
        shell_exec('sudo systemctl daemon-reload');
        shell_exec('sudo mount -a 2>/dev/null');

        // 5. Advance State Machine
        $state = file_exists($state_file) ? json_decode(file_get_contents($state_file), true) : [];
        $state['step'] = 4;
        $state['drives'] = $mount_records;
        $state['updated_at'] = time();
        file_put_contents($state_file, json_encode($state, JSON_PRETTY_PRINT));

        // 6. Restart to verify persistent mounting on boot
        header('Location: step3.php?rebooting=1');
        exit;
    }
}

function format_bytes($bytes) {
    if ($bytes >= 1073741824) return round($bytes / 1073741824, 1) . ' GB';
    if ($bytes >= 1048576) return round($bytes / 1048576, 1) . ' MB';
    return $bytes . ' B';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Step 3: Media Storage</title>
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
            max-width: 540px;
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
        .drive-list {
            display: flex;
            flex-direction: column;
            gap: 14px;
            margin-bottom: 24px;
        }
        .drive-card {
            background: #202632;
            border: 2px solid #2e3748;
            border-radius: 12px;
            padding: 16px;
            display: flex;
            align-items: flex-start;
            gap: 14px;
            cursor: pointer;
            transition: all 0.15s ease;
        }
        .drive-card:hover {
            border-color: #3b475d;
        }
        .drive-card input[type="checkbox"] {
            width: 22px;
            height: 22px;
            accent-color: #00d4ff;
            margin-top: 3px;
            cursor: pointer;
            flex-shrink: 0;
        }
        .drive-content {
            flex-grow: 1;
        }
        .drive-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 4px;
            flex-wrap: wrap;
            gap: 6px;
        }
        .drive-title {
            font-size: 1.05rem;
            font-weight: 600;
            color: #ffffff;
        }
        .drive-target {
            font-family: monospace;
            font-size: 0.82rem;
            color: #00d4ff;
            background: rgba(0, 212, 255, 0.1);
            border: 1px solid rgba(0, 212, 255, 0.25);
            padding: 2px 8px;
            border-radius: 6px;
        }
        .drive-sub {
            font-size: 0.82rem;
            color: #8b949e;
            line-height: 1.4;
            margin-top: 2px;
        }
        .drive-sub code {
            font-family: monospace;
            background: #141720;
            padding: 2px 5px;
            border-radius: 4px;
            color: #79c0ff;
        }
        .mounted-tag {
            display: inline-block;
            margin-top: 6px;
            font-size: 0.78rem;
            color: #e2c044;
            background: rgba(234, 179, 8, 0.12);
            padding: 2px 8px;
            border-radius: 4px;
            border: 1px solid rgba(234, 179, 8, 0.3);
        }
        .empty-drives {
            text-align: center;
            padding: 28px 16px;
            background: #202632;
            border: 1px dashed #364154;
            border-radius: 12px;
            margin-bottom: 24px;
        }
        .empty-icon {
            font-size: 2rem;
            margin-bottom: 8px;
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
    <?php render_reboot_screen('step4.php'); ?>
<?php else: ?>
    <div class="badge">Step 3 of 5</div>
    <h1>Media Storage</h1>
    <p class="subtitle">Select attached USB storage to mount under <code>/media/pi/drive_*</code>.</p>

    <div class="notice-card">
        <div class="notice-icon">⚡</div>
        <div class="notice-text">
            <strong>Reboot Notice:</strong> Registering persistent mounts in <code>/etc/fstab</code> requires a restart (~30s) to verify systemd boot mounting.
        </div>
    </div>

    <?php if (!empty($error)): ?>
        <div class="error-card"><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <form method="POST">
        <?php if (!empty($partitions)): ?>
            <div class="drive-list">
                <?php foreach ($partitions as $part): ?>
                    <?php 
                    $label = !empty($part['label']) ? $part['label'] : 'USB Drive (' . $part['name'] . ')';
                    $size = format_bytes($part['size']);
                    $fs = strtoupper($part['fstype'] ?: 'UNKNOWN');
                    $is_mounted = !empty($part['mountpoint']);
                    ?>
                    <label class="drive-card">
                        <input type="checkbox" name="selected_partitions[]" value="<?= htmlspecialchars($part['uuid']) ?>" checked>
                        <div class="drive-content">
                            <div class="drive-header">
                                <span class="drive-title"><?= htmlspecialchars($label) ?> (<?= $size ?>)</span>
                                <span class="drive-target"><?= htmlspecialchars($part['target_mount']) ?></span>
                            </div>
                            <div class="drive-sub">
                                Format: <code><?= htmlspecialchars($fs) ?></code> &bull; 
                                Dev: <code>/dev/<?= htmlspecialchars($part['name']) ?></code>
                            </div>

                            <?php if ($is_mounted): ?>
                                <div class="mounted-tag">
                                    Currently mounted at: <strong><?= htmlspecialchars($part['mountpoint']) ?></strong>
                                </div>
                            <?php endif; ?>
                        </div>
                    </label>
                <?php endforeach; ?>
            </div>

            <button type="submit" name="mount_drives" value="1" class="btn-submit">Mount Selected & Restart to Step 4</button>
        <?php else: ?>
            <div class="empty-drives">
                <div class="empty-icon">🔌</div>
                <strong style="color: #ffffff; display: block; margin-bottom: 6px;">No USB Drives Detected</strong>
                <p style="font-size: 0.85rem; color: #8b949e; margin: 0 0 16px 0;">
                    Plug your USB drive(s) into the Pi and refresh.
                </p>
                <a href="step3.php" class="btn-skip" style="border-color: #3b475d; display: inline-block; width: auto; padding: 8px 18px;">Refresh List</a>
            </div>
        <?php endif; ?>

        <button type="submit" name="skip_step" value="1" class="btn-skip" formnovalidate>
            <?= empty($partitions) ? 'Skip This Step (No USB Drives)' : 'Keep Current Storage / Skip' ?>
        </button>
    </form>

    <?php render_emergency_reset(); ?>
<?php endif; ?>

</div>

</body>
</html>