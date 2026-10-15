<?php
######################################## json settings
require_once("settings.class.inc");

$Settings = new Settings();
$json_settings = $Settings->load("/home/pi/Desktop/settings.json");
if($json_settings[0]==null) die("Settings Error: " . $json_settings[1]);
$json_settings = $json_settings[0];
$root = $json_settings["drive"];
// Ensure $root is an array even if single drive
if (!is_array($root)) { $root = [$root]; }
######################################## /json settings

// API: Check directory against short names
if(isset($_GET["check"])) {
    $directory = $_GET["check"];
    if (is_dir($directory)) {
        $baseUrl = "http://127.0.0.1/?short=1&getshowname=";
        $files = array_diff(scandir($directory), array('.', '..'));
        $last_name = null;
        $colors = ['#FF5733', '#33FF57', '#3357FF', '#F1C40F', '#8E44AD', '#E74C3C', '#2ECC71', '#3498DB'];
        
        foreach ($files as $file) {
            if(pathinfo($file, PATHINFO_EXTENSION) == "commercials") continue;
            $ch = curl_init($baseUrl . urlencode($file));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $response = curl_exec($ch);
            curl_close($ch);

            $style = ($response != $last_name && $last_name != null) ? "color:" . $colors[array_rand($colors)] . "; font-weight: bold;" : "font-weight: bold;";
            echo "<div style='font-family: sans-serif; margin-bottom: 5px;'>";
            echo "<span style='opacity: 0.6;'>Short:</span> <span style='$style'>".htmlspecialchars($response)."</span> ";
            echo "<span style='opacity: 0.4; font-size: 0.8em;'>Checked: ".htmlspecialchars($file)."</span>";
            echo "</div>";
            $last_name = $response;
        }
    } else {
        die("Directory does not exist.");
    }
    die();
}

// API: Delete File
if(isset($_GET["f"], $_GET["delete"]) && $_GET["delete"] === "delete") {
    $file = $_GET["f"];
    if(file_exists($file)) {
        unlink($file);
        die("1|deleted");
    }
    die("file no exist");
}

// API: Move to Movie Trailers
if(isset($_GET["f"], $_GET["movie"]) && $_GET["movie"] !== "") {
    $file = $_GET["f"];
    if(file_exists($file)) {
        $newName = dirname($file) . '/../movie_trailers/' . $_GET["movie"] . "." . pathinfo($file, PATHINFO_EXTENSION);
        rename($file, $newName);
        die("1|movie trailer set");
    }
    die("file no exist");
}

// API: Set Time Metadata (AM/PM/ANY)
if(isset($_GET["f"], $_GET["set"])) {
    $file = $_GET["f"];
    if(file_exists($file)) {
        $tags = ["0" => "%AM%", "1" => "%PM%", "2" => "%ANY%"];
        $tag = $tags[$_GET["set"]] ?? die("set wrong");
        
        $info = pathinfo($file);
        $newfile = $info['dirname'] . "/" . $info['filename'] . $tag . "." . $info['extension'];
        $worked = rename($file, $newfile);
        die($worked . "|" . $newfile);
    }
    die("file no exist");
}

// API: Remote Play (Legacy local trigger)
if(isset($_GET["play"])) {
    $fullPath = $root[0] . "/" . $_GET["play"]; // Simplified for first root
    file_put_contents("/home/pi/Desktop/next.video", $fullPath);
    exec('sudo chmod 777 /home/pi/Desktop/next.video');
    exec('sudo pkill omxplayer');
    die('playing ' . $fullPath);
}

$path = isset($_GET['file']) ? "/" . $_GET['file'] : null;

// Handle direct file serving if necessary
if ($path && is_file($root[0].$path)) {
    $mime = mime_content_type($root[0].$path);
    header("Content-Type: $mime");
    readfile($root[0].$path);
    exit;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Video Browser <?php echo htmlspecialchars($path); ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        :root {
            --bg: #121212;
            --card-bg: #1e1e1e;
            --text: #e0e0e0;
            --accent: #4caf50;
            --danger: #f44336;
        }
        body {
            background: var(--bg);
            color: var(--text);
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            margin: 0;
            padding: 20px;
        }
        .header {
            background: var(--card-bg);
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            position: sticky;
            top: 0;
            z-index: 100;
            box-shadow: 0 4px 6px rgba(0,0,0,0.3);
        }
        .controls-hint {
            font-size: 0.85em;
            color: #888;
            line-height: 1.4;
        }
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 15px;
            margin-bottom: 300px;
        }
        .item {
            background: var(--card-bg);
            padding: 12px;
            border-radius: 8px;
            display: flex;
            flex-direction: column;
            gap: 8px;
            border: 1px solid #333;
            transition: transform 0.1s;
        }
        .item:hover { border-color: #555; }
        .item.folder { border-left: 4px solid #ffc107; }
        .item.video { border-left: 4px solid var(--accent); }
        
        .item-title {
            font-weight: 500;
            font-size: 1.1em;
            word-break: break-all;
            text-decoration: none;
            color: var(--text);
        }
        .item-actions {
            display: flex;
            gap: 5px;
            flex-wrap: wrap;
        }
        button, .btn {
            background: #333;
            color: white;
            border: none;
            padding: 6px 10px;
            border-radius: 4px;
            cursor: pointer;
            font-size: 0.8em;
            text-decoration: none;
            display: inline-block;
        }
        button:hover { background: #444; }
        .btn-play { background: var(--accent); }
        .btn-del { background: #b71c1c; }

        #player-container {
            position: fixed;
            bottom: 20px;
            right: 20px;
            width: 400px;
            background: #000;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 10px 30px rgba(0,0,0,0.5);
            z-index: 1000;
            display: none;
        }
        #vidplayer { width: 100%; display: block; }
        #counter {
            background: rgba(0,0,0,0.7);
            color: #fff;
            padding: 4px 10px;
            font-family: monospace;
            font-size: 12px;
            text-align: center;
        }
        @media (max-width: 600px) {
            #player-container {
                width: calc(100% - 40px);
                bottom: 10px;
                right: 10px;
            }
            .grid { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body>

<div class="header">
    <div style="display:flex; justify-content: space-between; align-items: center;">
        <h2 style="margin:0">
            <?php if ($path): ?>
                <a href="?file=<?php echo urlencode(substr(dirname($path), 1)); ?>" style="color:var(--accent); text-decoration:none;">⬅</a>
            <?php endif; ?>
            /<?php echo htmlspecialchars(basename($path)); ?>
        </h2>
        <div id="counter">00:00:00</div>
    </div>
    <div class="controls-hint">
        Keys: <b>A/D</b>: Skip | <b>QQ/WW/EE</b>: Set AM/PM/Either | <b>XX</b>: Delete | <b>ZZ</b>: Movie
    </div>
</div>

<div id="player-container">
    <video id="vidplayer" controls autoplay></video>
    <button onclick="document.getElementById('player-container').style.display='none'" style="width:100%; border-radius:0;">Close Player</button>
</div>

<div class="grid">
<?php
$ic = 1;
foreach ($root as $r) {
    $fullDir = realpath($r . $path);
    if (!$fullDir) continue;

    $items = glob("$fullDir/*");
    foreach ($items as $item) {
        $basename = basename($item);
        if (strpos(strtolower($basename), ".commercials") !== false) continue;

        $link = ltrim(substr($item, strlen(rtrim($r, '/\\'))), '/\\');
		
        $isDir = is_dir($item);
        $ext = strtolower(pathinfo($item, PATHINFO_EXTENSION));
        $isVideo = in_array($ext, ['avi', 'mp4', 'mkv', 'mov', 'mpg', 'm4v', 'flv', 'mpeg']);

        if ($isDir) {
            echo '<div class="item folder">';
            echo '  <a class="item-title" href="?file='.urlencode($link).'">📁 '.$basename.'</a>';
            echo '  <div class="item-actions"><a href="?check='.urlencode($item).'" class="btn">Check Names</a></div>';
            echo '</div>';
        } elseif ($isVideo) {
            $specialStyle = "";
            if (strpos($item, "_NA_") !== false) $specialStyle = "border: 1px solid #aff;";
            
            echo '<div class="item video" id="row'.$ic.'" style="'.$specialStyle.'">';
            echo '  <span class="item-title">'.$ic.'. '.$basename.'</span>';
            echo '  <div class="item-actions">';
            echo '    <button class="btn btn-play" id="plus'.$ic.'" onclick="playVideo(\'/?video='.urlencode($item).'\', '.$ic.')">▶ Play</button>';
            
            if (!preg_match('/%(AM|PM|ANY)%/', $item)) {
                echo '    <button id="am'.$ic.'" onclick="setAMPM(\''.urlencode($item).'\', 0, this)">AM</button>';
                echo '    <button id="pm'.$ic.'" onclick="setAMPM(\''.urlencode($item).'\', 1, this)">PM</button>';
                echo '    <button id="any'.$ic.'" onclick="setAMPM(\''.urlencode($item).'\', 2, this)">Either</button>';
                echo '    <button id="movie'.$ic.'" onclick="movieVideo(\''.urlencode($item).'\', this)">Movie</button>';
            }
            
            echo '    <button class="btn btn-del" id="del'.$ic.'" onclick="delVideo(\''.urlencode($item).'\', this)">🗑</button>';
            echo '  </div>';
            echo '</div>';
            $ic++;
        }
    }
}
?>
</div>

<script>
let counterStarted = false;
let currID = null;
let lastKey = null;
let lastKeyTime = 0;

function startCounter() {
    if (counterStarted) return;
    counterStarted = true;
    let seconds = 0;
    const el = document.getElementById("counter");
    setInterval(() => {
        seconds++;
        let h = Math.floor(seconds / 3600).toString().padStart(2, '0');
        let m = Math.floor((seconds % 3600) / 60).toString().padStart(2, '0');
        let s = (seconds % 60).toString().padStart(2, '0');
        el.textContent = `${h}:${m}:${s}`;
    }, 1000);
}

function playVideo(url, id) {
    currID = id;
    const container = document.getElementById("player-container");
    const video = document.getElementById("vidplayer");
    container.style.display = "block";
    video.src = url;
    video.play();
    startCounter();
    
    // Highlight current
    document.querySelectorAll('.item').forEach(el => el.style.background = "");
    document.getElementById('row' + id).style.background = "#2e3b2e";
}

function ajax(url, cb) {
    fetch(url).then(r => r.text()).then(txt => {
        if (txt.startsWith("1")) {
            if(cb) cb();
        } else {
            alert("Error: " + txt);
        }
    });
}

function setAMPM(file, type, btn) {
    ajax(`dir.php?f=${file}&set=${type}`, () => {
        btn.closest('.item').style.opacity = "0.3";
        nextVideo();
    });
}

function delVideo(file, btn) {
    if(!confirm("Delete this video?")) return;
    ajax(`dir.php?f=${file}&delete=delete`, () => {
        btn.closest('.item').remove();
    });
}

function movieVideo(file, btn) {
    let name = prompt("Enter movie name:");
    if(!name) return;
    ajax(`dir.php?f=${file}&movie=${encodeURIComponent(name)}`, () => {
        btn.closest('.item').remove();
    });
}

function nextVideo() {
    const nextPlus = document.getElementById('plus' + (currID + 1));
    if (nextPlus) {
        nextPlus.click();
        nextPlus.scrollIntoView({ behavior: 'smooth', block: 'center' });
    }
}

document.onkeydown = function(e) {
    const video = document.getElementById("vidplayer");
    const now = Date.now();
    const isDouble = (e.key === lastKey && (now - lastKeyTime) < 500);
    
    if (e.key === "d") video.currentTime += 5;
    else if (e.key === "a") video.currentTime -= 5;
    else if (isDouble) {
        if (e.key === "q") document.getElementById('am' + currID)?.click();
        if (e.key === "w") document.getElementById('pm' + currID)?.click();
        if (e.key === "e") document.getElementById('any' + currID)?.click();
        if (e.key === "x") document.getElementById('del' + currID)?.click();
        if (e.key === "z") document.getElementById('movie' + currID)?.click();
        lastKey = null;
    } else if (e.key === "r") {
        nextVideo();
    } else {
        lastKey = e.key;
        lastKeyTime = now;
    }
};

// Hide player on mobile if requested by original logic
if (/(android|bb\d+|meego).+mobile|avantgo|bada\/|blackberry|blazer|elaine|fennec|hiptop|iemobile|ip(hone|od)|kindle|lge |maemo|midp|mmp|mobile.+firefox|netfront|opera m(ob|in)i|palm( os)?|phone|p(ixi|re)\/|plucker|pocket|psp|series(4|6)0|symbian|treo|up\.(browser|link)|vodafone|wap|windows ce|xda|xiino/i.test(navigator.userAgent)) {
    // Keep it enabled but maybe smaller? Original code hid it.
    // document.getElementById('player-container').style.display='none';
}
</script>
</body>
</html>