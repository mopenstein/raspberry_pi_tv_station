<?php
error_reporting(E_ALL);
ini_set("display_errors", 1);

require_once("settings.class.inc");

$Settings = new Settings();
$json_settings = $Settings->load("/home/pi/Desktop/settings.json");
if ($json_settings[0] == null) die("Settings Error: " . $json_settings[1]);
$json_settings = $json_settings[0];

$drives = is_array($json_settings["drive"]) ? $json_settings["drive"] : [$json_settings["drive"]];

// Backend API Handlers
if (isset($_GET["type"])) {
    header('Content-Type: text/plain');
    
    function getSubDirs($base, $currentPath = "") {
        $dirs = [];
        $fullPath = $base . ($currentPath ? DIRECTORY_SEPARATOR . $currentPath : "");
        if (!is_dir($fullPath)) return [];
        
        $items = scandir($fullPath);
        foreach ($items as $item) {
            if ($item === "." || $item === "..") continue;
            $relItem = $currentPath ? $currentPath . DIRECTORY_SEPARATOR . $item : $item;
            if (is_dir($base . DIRECTORY_SEPARATOR . $relItem)) {
                $dirs[] = $relItem;
                $dirs = array_merge($dirs, getSubDirs($base, $relItem));
            }
        }
        return $dirs;
    }

    if ($_GET["type"] == "get_dirs" && isset($_GET["drive"])) {
        header('Content-Type: application/json');
        $drive = rtrim($_GET["drive"], DIRECTORY_SEPARATOR);
        $allDirs = getSubDirs($drive);
        die(json_encode($allDirs));
    }

    if ($_GET["type"] == "get_files" && isset($_GET["dir"])) {
        header('Content-Type: application/json');
        $dir = str_replace('//', '/', $_GET["dir"]);
        $files = [];
        if (is_dir($dir)) {
            $cdir = scandir($dir);
            if ($cdir !== false) {
                foreach ($cdir as $value) {
                    if (!in_array($value, array(".", ".."))) {
                        $fullPath = $dir . DIRECTORY_SEPARATOR . $value;
                        if (!is_dir($fullPath) && preg_match('/\.(mp4|mkv|avi|mov|wmv|flv|webm)$/i', $value)) {
                            $files[] = $value;
                        }
                    }
                }
            }
        }
        die(json_encode($files));
    }

    if ($_GET["type"] == "delete" && isset($_GET["in"])) {
        if (!file_exists($_GET["in"])) die("Error: File not found");
        unlink($_GET["in"]);
        die("deleted");
    }

    if ($_GET["type"] == "edit" && isset($_GET["in"]) && isset($_GET["out"]) && is_numeric($_GET["start"]) && is_numeric($_GET["end"])) {
        $source = $_GET["in"];
        $tempOut = $_GET["out"];
        if (!file_exists($source)) die("Error: Source not found");

        $duration = $_GET["end"] - $_GET["start"];
        $newTagValue = ceil($duration);
        $cmd = "ffmpeg -ss " . escapeshellarg($_GET["start"]) . " -t " . escapeshellarg($duration) . " -i " . escapeshellarg($source) . " -c:v libx264 -preset superfast -c:a aac " . escapeshellarg($tempOut) . " 2>&1";
        
        exec($cmd, $output, $return_var);
        if ($return_var === 0 && file_exists($tempOut)) {
            $pathInfo = pathinfo($source);
            $cleanName = preg_replace('/%T\(\d+\)%/i', '', $pathInfo['filename']);
            $newName = $pathInfo['dirname'] . DIRECTORY_SEPARATOR . $cleanName . "%T(" . $newTagValue . ")%." . $pathInfo['extension'];
            if (file_exists($source)) unlink($source); 
            rename($tempOut, $newName);
            die("success");
        }
        die("error: " . implode("\n", $output));
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Video Editor</title>
    <style>
        :root {
            --bg: #0f172a; --glass: rgba(30, 41, 59, 0.7); --border: rgba(255, 255, 255, 0.1);
            --text: #f8fafc; --text-muted: #94a3b8; --primary: #3b82f6; --danger: #ef4444;
        }
        body { background-color: var(--bg); color: var(--text); font-family: system-ui, sans-serif; margin: 0; padding: 2rem; }
        .container { max-width: 1200px; margin: 0 auto; }
        header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 2rem; }
        h1 { margin: 0; font-size: 1.8rem; background: linear-gradient(to right, #60a5fa, #818cf8); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .grid { display: grid; grid-template-columns: 1fr 2fr; gap: 2rem; }
        @media (max-width: 900px) { .grid { grid-template-columns: 1fr; } }
        .panel { background: var(--glass); backdrop-filter: blur(12px); border: 1px solid var(--border); border-radius: 1rem; padding: 1.5rem; margin-bottom: 1.5rem; }
        label { display: block; color: var(--text-muted); font-size: 0.7rem; text-transform: uppercase; margin-bottom: 0.25rem; }
        select, input { width: 100%; background: #020617; border: 1px solid #334155; color: white; padding: 0.6rem; border-radius: 0.5rem; margin-bottom: 1rem; box-sizing: border-box; }
        .btn { width: 100%; padding: 0.75rem; border-radius: 0.5rem; border: none; font-weight: bold; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; gap: 0.5rem; }
        .btn-primary { background: var(--primary); color: white; }
        .btn-secondary { background: #334155; color: white; font-size: 0.8rem; padding: 0.5rem; margin-bottom: 1rem; }
        .btn-action { background: #1e293b; color: var(--text); border: 1px solid var(--border); font-size: 0.75rem; padding: 0.4rem; width: auto; min-width: 40px; }
        .btn-danger { background: rgba(239, 68, 68, 0.1); color: var(--danger); border: 1px solid rgba(239, 68, 68, 0.2); }
        video { width: 100%; background: black; border-radius: 0.5rem; }
        
        /* Loading Overlays */
        .overlay { display: none; position: absolute; top: 0; left: 0; right: 0; bottom: 0; background: rgba(15, 23, 42, 0.9); z-index: 100; flex-direction: column; align-items: center; justify-content: center; border-radius: 1rem; }
        #page-loader { display: none; position: fixed; top: 0; left: 0; width: 100%; height: 100%; background: var(--bg); z-index: 999; flex-direction: column; align-items: center; justify-content: center; }
        
        .loader { width: 48px; height: 48px; border: 5px solid #1e293b; border-top: 5px solid var(--primary); border-radius: 50%; animation: spin 1s linear infinite; margin-bottom: 1rem; }
        @keyframes spin { 0% { transform: rotate(0deg); } 100% { transform: rotate(360deg); } }
        
        .precision-controls { display: flex; justify-content: center; gap: 0.5rem; margin-top: 0.5rem; }
        .kbd-hint { font-size: 0.65rem; color: var(--text-muted); text-align: center; margin-top: 0.5rem; }
        .truncate { white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
        .status-pill { display: none; align-items: center; gap: 0.5rem; background: rgba(59, 130, 246, 0.1); color: var(--primary); padding: 0.5rem 1rem; border-radius: 99px; font-size: 0.75rem; border: 1px solid rgba(59, 130, 246, 0.3); }
    </style>
</head>
<body>
    <!-- Initial Page Loader for Deep Links -->
    <div id="page-loader">
        <div class="loader"></div>
        <div style="font-weight: 600; font-size: 1.2rem;">Locating File...</div>
        <div id="loader-path" style="color: var(--text-muted); font-size: 0.8rem; margin-top: 0.5rem; max-width: 80%;"></div>
    </div>

    <div class="container">
        <header>
            <div>
                <h1>Video Editor</h1>
            </div>
            <div id="statusIndicator" class="status-pill">FFMPEG ACTIVE</div>
        </header>

        <div class="grid">
            <div>
                <div class="panel">
                    <label>Drive</label>
                    <select id="drivesel" onchange="drivesel_change()"><?php foreach ($drives as $d) { echo "<option value=\"$d\">" . basename($d) . " ($d)</option>"; } ?></select>
                    <label>Directory</label>
                    <select id="dirsel" onchange="dirsel_change()"><option value="/">Root (/)</option></select>
                    <label>Files</label>
                    <select id="filesel" onchange="filesel_change(this)"></select>
                </div>

                <div class="panel">
                    <div class="grid" style="grid-template-columns: 1fr 1fr; gap: 10px;">
                        <div>
                            <label>Start (s)</label>
                            <input type="number" id="start_time" value="0.000" step="0.001">
                            <button class="btn btn-secondary" onclick="set_marker('start')">Set Start</button>
                        </div>
                        <div>
                            <label>End (s)</label>
                            <input type="number" id="end_time" value="0.000" step="0.001">
                            <button class="btn btn-secondary" onclick="set_marker('end')">Set End</button>
                        </div>
                    </div>
                    <button class="btn btn-primary" onclick="perform_edit()" style="margin-top:1rem;">Process Clip</button>
                </div>

                <button class="btn btn-danger" onclick="delete_file()">Delete File</button>
            </div>

            <div>
                <div class="panel" style="position: relative; padding: 0.5rem;">
                    <video id="mainPlayer" controls><source id="videoSource" src="" type="video/mp4"></video>
                    <div class="precision-controls">
                        <button class="btn btn-action" onclick="step(-1)">-F</button>
                        <button class="btn btn-action" onclick="step(-0.1)">-100ms</button>
                        <button class="btn btn-action" onclick="step(-0.001)">-1ms</button>
                        <button class="btn btn-action" onclick="step(0.001)">+1ms</button>
                        <button class="btn btn-action" onclick="step(0.1)">+100ms</button>
                        <button class="btn btn-action" onclick="step(1)">+F</button>
                    </div>
                    <div class="kbd-hint">Arrows for precision. Shift+Arrows for frames.</div>
                    <div id="overlay" class="overlay"><div class="loader"></div><div id="overlay-text">Converting...</div></div>
                </div>
                <div class="panel">
                    <div id="display_filename" class="truncate" style="font-weight: 600;">No Media</div>
                    <div id="display_path" class="truncate" style="font-size: 0.7rem; color: var(--text-muted);">/path/to/video</div>
                    <div id="display_duration" style="font-family: monospace; font-size: 1.5rem; color: var(--primary); margin-top: 0.5rem;">00:00</div>
                </div>
            </div>
        </div>
    </div>

    <script>
        const player = document.getElementById('mainPlayer');
        const overlay = document.getElementById('overlay');
        const pageLoader = document.getElementById('page-loader');
        const statusInd = document.getElementById('statusIndicator');

        const urlParams = new URLSearchParams(window.location.search);
        let preselectedFile = urlParams.get('file');

        function showPageLoader(path) {
            if (path) {
                document.getElementById('loader-path').innerText = path;
                pageLoader.style.display = 'flex';
            }
        }

        function hidePageLoader() {
            pageLoader.style.opacity = '0';
            setTimeout(() => {
                pageLoader.style.display = 'none';
                pageLoader.style.opacity = '1';
            }, 300);
        }

        function step(seconds) {
            if (Math.abs(seconds) === 1) seconds = (seconds > 0 ? 1 : -1) * (1 / 30);
            player.pause();
            player.currentTime += seconds;
        }

        window.addEventListener('keydown', (e) => {
            if (e.target.tagName === 'INPUT') return;
            if (e.key === 'ArrowLeft') { step(e.shiftKey ? -1 : -0.05); e.preventDefault(); }
            else if (e.key === 'ArrowRight') { step(e.shiftKey ? 1 : 0.05); e.preventDefault(); }
        });

        async function drivesel_change(targetPath = null) {
            const dsel = document.getElementById('drivesel');
            const drive = dsel.value;
            const dirsel = document.getElementById('dirsel');
            dirsel.innerHTML = "<option>Mapping...</option>";
            try {
                const res = await fetch(`videoeditor.php?type=get_dirs&drive=${encodeURIComponent(drive)}`);
                const dirs = await res.json();
                dirsel.innerHTML = '<option value="/">Root (/)</option>';
                dirs.sort().forEach(d => {
                    const opt = document.createElement('option');
                    opt.value = '/' + d; opt.text = '↳ ' + d.replace(/\//g, ' / ');
                    dirsel.appendChild(opt);
                });
                
                if (targetPath) {
                    const driveNormalized = drive.replace(/\/$/, '');
                    const targetNormalized = targetPath.replace(/\/$/, '');
                    let relativeDir = targetNormalized.replace(driveNormalized, '');
                    if (!relativeDir) relativeDir = '/';
                    
                    for (let i = 0; i < dirsel.options.length; i++) {
                        if (dirsel.options[i].value === relativeDir) {
                            dirsel.selectedIndex = i;
                            break;
                        }
                    }
                }
                await dirsel_change();
            } catch (e) { dirsel.innerHTML = "<option value='/'>Error mapping</option>"; }
        }

        async function dirsel_change() {
            const drive = document.getElementById('drivesel').value.replace(/\/$/, '');
            const subDir = document.getElementById('dirsel').value;
            let fullDir = drive + (subDir === '/' ? '' : subDir);
            const fsel = document.getElementById('filesel');
            fsel.innerHTML = "<option>Scanning...</option>";
            try {
                const res = await fetch(`videoeditor.php?type=get_files&dir=${encodeURIComponent(fullDir)}`);
                const files = await res.json();
                fsel.innerHTML = "";
                files.forEach(f => {
                    const opt = document.createElement('option');
                    opt.value = f; opt.text = f; fsel.appendChild(opt);
                });

                if (preselectedFile && !fsel.dataset.loaded) {
                    const targetFile = preselectedFile.split('/').pop();
                    for (let i = 0; i < fsel.options.length; i++) {
                        if (fsel.options[i].value === targetFile) {
                            fsel.selectedIndex = i;
                            fsel.dataset.loaded = "true";
                            break;
                        }
                    }
                }
                
                if(fsel.selectedIndex >= 0) {
                    filesel_change(fsel);
                    // Hide main loader once the file is loaded into the interface
                    if (preselectedFile) hidePageLoader();
                }
            } catch (e) { 
                fsel.innerHTML = "<option value=''>Error reading</option>"; 
                hidePageLoader();
            }
        }

        function filesel_change(el) {
            if(!el.value) return;
            const drive = document.getElementById('drivesel').value.replace(/\/$/, '');
            const subDir = document.getElementById('dirsel').value;
            let cleanedSubDir = subDir === '/' ? '' : subDir;
            const fullPath = `${drive}${cleanedSubDir}/${el.value}`.replace(/\/\//g, '/');
            document.getElementById('videoSource').src = `/?video=${encodeURIComponent(fullPath)}`;
            player.load();
            document.getElementById('display_filename').innerText = el.value;
            document.getElementById('display_path').innerText = fullPath;
            player.onloadedmetadata = () => {
                document.getElementById('start_time').value = "0.000";
                document.getElementById('end_time').value = player.duration.toFixed(3);
                document.getElementById('display_duration').innerText = formatTime(player.duration);
            };
        }

        function set_marker(type) {
            const time = player.currentTime;
            document.getElementById(type === 'start' ? 'start_time' : 'end_time').value = time.toFixed(3);
        }

        async function perform_edit() {
            const drive = document.getElementById('drivesel').value.replace(/\/$/, '');
            const subDir = document.getElementById('dirsel').value;
            const file = document.getElementById('filesel').value;
            if(!file) return;
            const inPath = `${drive}${subDir === '/' ? '' : subDir}/${file}`.replace(/\/\//g, '/');
            const outPath = `${drive}${subDir === '/' ? '' : subDir}/trimmed_${Date.now()}_${file}`.replace(/\/\//g, '/');
            
            document.getElementById('overlay-text').innerText = "Processing Clip...";
            overlay.style.display = 'flex';
            statusInd.style.display = 'flex';
            player.pause(); player.src = "";
            try {
                const res = await fetch(`videoeditor.php?type=edit&in=${encodeURIComponent(inPath)}&out=${encodeURIComponent(outPath)}&start=${document.getElementById('start_time').value}&end=${document.getElementById('end_time').value}`);
                if((await res.text()).trim() === "success") await dirsel_change();
                else alert("FFmpeg Error");
            } catch (e) { alert("Connection Error"); } finally {
                overlay.style.display = 'none'; statusInd.style.display = 'none';
            }
        }

        async function delete_file() {
            const drive = document.getElementById('drivesel').value.replace(/\/$/, '');
            const subDir = document.getElementById('dirsel').value;
            const file = document.getElementById('filesel').value;
            if(!file || !confirm(`Delete ${file}?`)) return;
            const path = `${drive}${subDir === '/' ? '' : subDir}/${file}`.replace(/\/\//g, '/');
            const res = await fetch(`videoeditor.php?type=delete&in=${encodeURIComponent(path)}`);
            if(await res.text() === "deleted") dirsel_change();
        }

        function formatTime(s) {
            if(isNaN(s)) return "00:00";
            const m = Math.floor(s / 60); const sec = (s % 60).toFixed(2);
            return `${m < 10 ? '0'+m : m}:${sec < 10 ? '0'+sec : sec}`;
        }

        window.onload = () => {
            if (preselectedFile) {
                showPageLoader(preselectedFile);
                const dsel = document.getElementById('drivesel');
                let bestDrive = null;
                let longestMatch = 0;

                for (let i = 0; i < dsel.options.length; i++) {
                    const drivePath = dsel.options[i].value;
                    if (preselectedFile.startsWith(drivePath) && drivePath.length > longestMatch) {
                        bestDrive = i;
                        longestMatch = drivePath.length;
                    }
                }

                if (bestDrive !== null) {
                    dsel.selectedIndex = bestDrive;
                    const dirOnly = preselectedFile.substring(0, preselectedFile.lastIndexOf('/'));
                    drivesel_change(dirOnly);
                } else {
                    drivesel_change();
                    hidePageLoader();
                }
            } else {
                drivesel_change();
            }
        };
    </script>
</body>
</html>