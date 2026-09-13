<?php

class MountedDrivesManager implements ManageCard {
    private $name = 'Mounted Drives';
    private $links = [];
    private $html = '';

    public function __construct() {
        $this->loadDriveData();
    }

    private function formatBytes($bytes, $precision = 1) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, $precision) . ' ' . $units[$pow];
    }

    private function loadDriveData() {
        // Query all block devices in JSON format
        $raw_json = shell_exec('lsblk -J -b -o NAME,SIZE,TYPE,MOUNTPOINT,FSTYPE,LABEL,UUID 2>/dev/null');
        $data = json_decode($raw_json, true);
        $drives = [];

        if (!empty($data['blockdevices'])) {
            foreach ($data['blockdevices'] as $dev) {
                // Ignore loopback devices and virtual memory (zram)
                if (strpos($dev['name'], 'loop') === 0 || strpos($dev['name'], 'zram') === 0) {
                    continue;
                }

                // Check partitions with active mountpoints
                if (!empty($dev['children'])) {
                    foreach ($dev['children'] as $child) {
                        if (!empty($child['mountpoint'])) {
                            $drives[] = $child;
                        }
                    }
                } elseif (!empty($dev['mountpoint'])) {
                    $drives[] = $dev;
                }
            }
        }

        if (empty($drives)) {
            $this->html = '<div style="margin:5px; padding:10px; color:#888;">No mounted storage devices detected.</div>';
            return;
        }

        $this->html = '<div style="margin:5px; padding:10px; font-size:13px;">';
        $this->html .= '<strong>Active Mounted Drives</strong><br><br>';
        $this->html .= '<table style="width:100%; border-collapse:collapse; text-align:left;">';
        $this->html .= '<tr style="border-bottom:1px solid rgba(255,255,255,0.15); color:#888; font-size:11px; text-transform:uppercase;">';
        $this->html .= '<th style="padding:6px 8px;">Device</th>';
        $this->html .= '<th style="padding:6px 8px;">Mount Point</th>';
        $this->html .= '<th style="padding:6px 8px;">FS</th>';
        $this->html .= '<th style="padding:6px 8px;">Usage</th>';
        $this->html .= '<th style="padding:6px 8px; text-align:right;">Free / Total</th>';
        $this->html .= '</tr>';

        foreach ($drives as $drive) {
            $mount = $drive['mountpoint'];
            $devName = '/dev/' . $drive['name'];
            $fsType = strtoupper($drive['fstype'] ?: 'auto');
            $label = !empty($drive['label']) ? htmlspecialchars($drive['label']) : '';

            // Query filesystem capacity and free space on this mount
            $totalSpace = @disk_total_space($mount);
            $freeSpace = @disk_free_space($mount);

            if ($totalSpace !== false && $freeSpace !== false && $totalSpace > 0) {
                $usedSpace = $totalSpace - $freeSpace;
                $usedPercent = round(($usedSpace / $totalSpace) * 100);
                $freeFormatted = $this->formatBytes($freeSpace);
                $totalFormatted = $this->formatBytes($totalSpace);
            } else {
                $usedPercent = 0;
                $freeFormatted = '--';
                $totalFormatted = $this->formatBytes($drive['size'] ?? 0);
            }

            // Usage bar styling
            $barColor = '#22c55e';
            if ($usedPercent >= 90) {
                $barColor = '#ef4444';
            } elseif ($usedPercent >= 75) {
                $barColor = '#f59e0b';
            }

            $this->html .= '<tr style="border-bottom:1px solid rgba(255,255,255,0.07);">';
            
            // Device column
            $this->html .= '<td style="padding:8px; white-space:nowrap;">';
            $this->html .= '<strong>' . htmlspecialchars($devName) . '</strong>';
            if ($label !== '') {
                $this->html .= '<br><span style="font-size:11px; color:#888;">' . $label . '</span>';
            }
            $this->html .= '</td>';

            // Mount point column
            $this->html .= '<td style="padding:8px; font-family:monospace; font-size:12px;">';
            $this->html .= htmlspecialchars($mount);
            $this->html .= '</td>';

            // Filesystem type
            $this->html .= '<td style="padding:8px; font-size:11px; color:#aaa;">' . htmlspecialchars($fsType) . '</td>';

            // Usage progress bar
            $this->html .= '<td style="padding:8px; min-width:90px;">';
            $this->html .= '<div style="background:rgba(255,255,255,0.1); border-radius:4px; height:8px; width:100%; overflow:hidden;">';
            $this->html .= "<div style=\"background:{$barColor}; width:{$usedPercent}%; height:100%;\"></div>";
            $this->html .= '</div>';
            $this->html .= "<span style=\"font-size:10px; color:#aaa;\">{$usedPercent}% used</span>";
            $this->html .= '</td>';

            // Capacity summary
            $this->html .= '<td style="padding:8px; text-align:right; font-family:monospace; font-size:11px; white-space:nowrap;">';
            $this->html .= "{$freeFormatted} / {$totalFormatted}";
            $this->html .= '</td>';

            $this->html .= '</tr>';
        }

        $this->html .= '</table>';
        $this->html .= '<div style="margin-top:10px; text-align:right;">';
        $this->html .= '<a href="#" onclick="location.reload();" style="font-size:11px; color:#888; text-decoration:none;">↻ Refresh Status</a>';
        $this->html .= '</div>';
        $this->html .= '</div>';
    }

    public function name() { return $this->name; }
    public function links() { return $this->links; }
    public function html() { return $this->html; }
}

?>