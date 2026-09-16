# Premade Disk Image

Download a disk image of my running raspberry pi tv station: [@ archive.org](https://archive.org/download/tv_station_2026_09_15_v102.3_sv_0.996_plus) (~2.1gb)

Requires at least a 8GB micro SD card, though bigger is better since the MYSQL database is stored on the SD card.

Designed and tested on a Raspberry Pi 2B, 3B, and 3b+.

# Raspberry Pi Broadcast TV Station Setup Guide

The Appliance Initializer guides you through four quick configuration steps to get your broadcast station online. Connect the Raspberry Pi to your local network via an ethernet wire and point your browser to: http://raspi-tv-station.local/ Setup will begin automatically.

## Setup Steps

1. **Station Identity**: Choose a unique local hostname for the broadcast unit (e.g., `raspi-tv-station`). Changing this triggers a quick ~30-second restart and updates your local access URL.
<img src="/assets/tv-station-setup-step.png" width="200" title="Setup Preview 1">

2. **Clock & Timezone**: Select your local region so scheduled broadcasts and bumpers air at the correct time. The wizard detects browser and Pi timezones, allowing you to sync clocks or skip if already matched.
<img src="/assets/tv-station-setup-step-hostname.png" width="200" title="Setup Preview 2">

3. **Media Storage**: Select and mount an attached USB storage drive (e.g., an exFAT drive) to persist your media under `/media/pi/drive_*`. Registers persistent mounts in `/etc/fstab`.
<img src="/assets/tv-station-setup-step-mount.png" width="200" title="Setup Preview 3">

4. **Wi-Fi Connection**: Join a local wireless network or continue using wired Ethernet to finalize your setup and bind services.
<img src="/assets/tv-station-setup-step-wi-fi.png" width="200" title="Setup Preview 4">

5. Once complete, the dashboard confirms your appliance is ready for air, displaying your final hostname, IP address, timezone, and storage target so you can launch the station interface.
<img src="/assets/tv-station-setup-step-complete.png" width="200" title="Setup Preview 5">

# Other

You'll need to supply your own video files and you'll have to edit the settings.json file to reflect the location of those video files. However, 3 small videos are included in the disk image.

Inside the /home/pi/Desktop/videos/station/ directory you will find a short test pattern video that will play "over the air" once the system plugged in.

Inside the /home/pi/Desktop/videos/examples/ directory you will find a short broadcast video that countdowns between commercial breaks that will play when triggered by the condition set in the settings file (default is between 8:00PM and 8:30PM). Also in the directory is the '.commercials' file that defines the times at which a commercial break occurs in the video.

Inside the /home/pi/Desktop/videos/commercials/ directory you will find a short, truncated public domain commercial that will play when the commercial break occurs as defined by the '.commercials' file for the current video playing.

Works with composite or HDMI video out.
