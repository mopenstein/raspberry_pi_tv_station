<?php
$requested_buttons = isset($_GET['buttons']) ? array_map('trim', explode(',', strtolower($_GET['buttons']))) : [];
$autoplay = isset($_GET['autoplay']) && $_GET['autoplay'] === 'true';

$show_play = in_array('play', $requested_buttons);
$show_stop = in_array('stop', $requested_buttons);
$show_fullscreen = in_array('fullscreen', $requested_buttons);
$has_ui = $show_play || $show_stop || $show_fullscreen;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Station Player Core</title>
    <style>
        html, body {
            margin: 0;
            padding: 0;
            width: 100%;
            height: 100%;
            background-color: #000;
            overflow: hidden;
            font-family: 'Courier New', Courier, monospace;
        }

        video {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            object-fit: cover;
            opacity: 0;
            transition: opacity 0.3s ease;
            filter: contrast(1.1) brightness(0.9) saturate(0.8) sepia(0.1);
        }
        
        #main-video { z-index: 1; }
        #override-video { z-index: 2; }

        .player-controls {
            position: absolute;
            bottom: 10px;
            right: 10px;
            z-index: 20;
            display: flex;
            gap: 10px;
            background: rgba(0, 0, 0, 0.6);
            padding: 5px;
            border-radius: 4px;
            border: 1px solid #333;
            /* Add these two lines: */
            transition: opacity 0.3s ease;
            opacity: 1;
        }

        .btn {
            background: #222;
            color: #aaa;
            border: 1px solid #444;
            padding: 5px 10px;
            font-family: inherit;
            font-size: 12px;
            cursor: pointer;
            border-radius: 2px;
        }

        .btn:hover { background: #333; color: #fff; }
        .btn:active { background: #111; }
    </style>
</head>
<body class="<?= $autoplay ? 'is-on' : '' ?>">

    <video id="main-video" playsinline></video>
    <video id="override-video" playsinline></video>

    <?php if ($has_ui): ?>
    <div class="player-controls" id="player-controls">
        <?php if ($show_play): ?><button class="btn" id="btn-play">PLAY</button><?php endif; ?>
        <?php if ($show_stop): ?><button class="btn" id="btn-stop">STOP</button><?php endif; ?>
        <?php if ($show_fullscreen): ?><button class="btn" id="btn-fullscreen">FULL</button><?php endif; ?>
    </div>
    <?php endif; ?>

    <script>

    <?php if ($has_ui): ?>
        const controlsContainer = document.getElementById('player-controls');
        let hideTimeout;

        function wakeControls() {
            controlsContainer.style.opacity = '1';
            controlsContainer.style.pointerEvents = 'auto'; // Re-enable clicks
            
            clearTimeout(hideTimeout);
            
            hideTimeout = setTimeout(() => {
                controlsContainer.style.opacity = '0';
                controlsContainer.style.pointerEvents = 'none'; // Prevent invisible clicks
            }, 2500); // Fades out after 2.5 seconds of inactivity
        }

        // Listen for user activity across the whole iframe
        document.addEventListener('mousemove', wakeControls);
        document.addEventListener('touchstart', wakeControls);
        document.addEventListener('click', wakeControls);
        
        // Trigger once on load
        wakeControls();
    <?php endif; ?>

        const mainVideo = document.getElementById('main-video');
        const overrideVideo = document.getElementById('override-video');
        
        let isActive = <?= $autoplay ? 'true' : 'false' ?>;
        let pollInterval = null;
        const SYNC_THRESHOLD_SECONDS = 2;
        const submediaPool = {};

        if (isActive) {
            startPlayback();
        }

        window.addEventListener('message', (event) => {
            const data = event.data;
            if (!data || !data.command) return;

            switch (data.command) {
                case 'power':
                    data.state ? startPlayback() : stopPlayback();
                    break;
                case 'fullscreen':
                    toggleFullscreen();
                    break;
            }
        });

        <?php if ($show_play): ?>
        document.getElementById('btn-play').addEventListener('click', startPlayback);
        <?php endif; ?>
        
        <?php if ($show_stop): ?>
        document.getElementById('btn-stop').addEventListener('click', stopPlayback);
        <?php endif; ?>

        <?php if ($show_fullscreen): ?>
        document.getElementById('btn-fullscreen').addEventListener('click', toggleFullscreen);
        <?php endif; ?>

        function startPlayback() {
            if (isActive && pollInterval) return; 
            isActive = true;
            syncPlayer();
            pollInterval = setInterval(syncPlayer, 1000);
            window.parent.postMessage({ status: 'playing' }, '*');
        }

        function stopPlayback() {
            isActive = false;
            if (pollInterval) {
                clearInterval(pollInterval);
                pollInterval = null;
            }
            shutdownAllMedia();
            window.parent.postMessage({ status: 'stopped' }, '*');
        }

        function toggleFullscreen() {
            if (!document.fullscreenElement) {
                document.documentElement.requestFullscreen().catch(err => console.error(err));
            } else {
                document.exitFullscreen();
            }
        }

        function shutdownAllMedia() {
            mainVideo.pause();
            mainVideo.style.opacity = '0';
            delete mainVideo.dataset.currentFile;
            
            overrideVideo.pause();
            overrideVideo.style.opacity = '0';
            delete overrideVideo.dataset.currentFile;
            
            for (const layerId in submediaPool) {
                submediaPool[layerId].pause();
                submediaPool[layerId].remove();
            }
            Object.keys(submediaPool).forEach(k => delete submediaPool[k]);
        }

        async function syncPlayer() {
            if (!isActive) return;

            try {
                const req = await fetch('functions.php?get_live_status=1').catch(() => null);
                if (!req || !req.ok) return;
                
                const data = await req.json();

                syncMediaNode(mainVideo, data.main);

                const overrideKeys = Object.keys(data.overrides || {});
                if (overrideKeys.length > 0) {
                    syncMediaNode(overrideVideo, data.overrides[overrideKeys[0]]);
                } else {
                    overrideVideo.pause();
                    overrideVideo.style.opacity = '0';
                }

                const currentSubKeys = Object.keys(data.submedia || {});
                
                for (const layerId in submediaPool) {
                    if (!currentSubKeys.includes(layerId)) {
                        submediaPool[layerId].pause();
                        submediaPool[layerId].remove();
                        delete submediaPool[layerId];
                    }
                }
                
                for (const layerId of currentSubKeys) {
                    if (!submediaPool[layerId]) {
                        const vid = document.createElement('video');
                        vid.playsInline = true;
                        vid.style.position = 'absolute';
                        vid.style.zIndex = '3'; 
                        vid.style.objectFit = 'fill'; 
                        vid.style.opacity = '0';
                        vid.style.transition = 'opacity 0.3s ease';
                        document.body.appendChild(vid);
                        submediaPool[layerId] = vid;
                    }
                    
                    const layerData = data.submedia[layerId];
                    const vidNode = submediaPool[layerId];
                    
                    syncMediaNode(vidNode, layerData);
                    
                    if (layerData.coords) {
                        const [x1, y1, x2, y2] = layerData.coords.split(' ').map(Number);
                        vidNode.style.left = x1 + 'px';
                        vidNode.style.top = y1 + 'px';
                        vidNode.style.width = (x2 - x1) + 'px';
                        vidNode.style.height = (y2 - y1) + 'px';
                    } else {
                        vidNode.style.left = '0';
                        vidNode.style.top = '0';
                        vidNode.style.width = '100%';
                        vidNode.style.height = '100%';
                    }
                }

            } catch (err) {
                console.error("Playback sync loop error:", err);
            }
        }

        function shutdownAllMedia() {
            mainVideo.pause();
            mainVideo.style.opacity = '0';
            mainVideo.muted = true;
            delete mainVideo.dataset.currentFile;
            
            overrideVideo.pause();
            overrideVideo.style.opacity = '0';
            overrideVideo.muted = true;
            delete overrideVideo.dataset.currentFile;
            
            for (const layerId in submediaPool) {
                submediaPool[layerId].pause();
                submediaPool[layerId].remove();
            }
            Object.keys(submediaPool).forEach(k => delete submediaPool[k]);
        }

        function syncMediaNode(videoEl, mediaData) {
            if (!mediaData || mediaData.state === "offline") {
                videoEl.pause();
                videoEl.style.opacity = '0';
                videoEl.muted = true;
                return;
            }

            const isPaused = mediaData.state === "paused";

            if (!videoEl.dataset.currentFile || videoEl.dataset.currentFile !== mediaData.file) {
                videoEl.style.opacity = '0'; 
                videoEl.muted = true;
                videoEl.src = '/?video=' + mediaData.file;
                videoEl.dataset.currentFile = mediaData.file;
                videoEl.dataset.forceSync = "true"; 
                
                videoEl.onloadedmetadata = () => {
                    videoEl.currentTime = mediaData.position; 
                    if (!isPaused) {
                        videoEl.play().catch(e => console.error("Play error:", e));
                    }
                };
            } else {
                if (videoEl.ended) {
                    videoEl.style.opacity = '0'; 
                    videoEl.muted = true;
                    return; 
                }

                if (videoEl.readyState > 0) {
                    const threshold = (videoEl.dataset.forceSync === "true") ? 0.1 : SYNC_THRESHOLD_SECONDS;
                    if (Math.abs(videoEl.currentTime - mediaData.position) > threshold) {
                        videoEl.currentTime = mediaData.position;
                    }
                    
                    if (videoEl.readyState >= 3 && videoEl.dataset.forceSync === "true") {
                        videoEl.dataset.forceSync = "false";
                    }
                    
                    // Reveal video and unmute once synced
                    if (videoEl.dataset.forceSync === "false" && mediaData.visible !== false) {
                        videoEl.style.opacity = '1';
                        videoEl.muted = false;
                    } else if (mediaData.visible === false) {
                        videoEl.style.opacity = '0';
                        videoEl.muted = true;
                    }
                }
                
                if (isPaused) {
                    if (!videoEl.paused) videoEl.pause();
                } else {
                    if (videoEl.paused) videoEl.play().catch(e => console.error("Play error:", e));
                }
            }

            if (mediaData.volume !== undefined) {
                videoEl.volume = Math.max(0, Math.min(1, mediaData.volume));
            }
        }
    </script>
</body>
</html>