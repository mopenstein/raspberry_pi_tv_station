# Raspberry Pi TV Station

Python2 and PHP scripts to emulate and automate a fully functional broadcast TV station

Designed and runs on the Rasperry PI 3b+ using A/V out. 

This script is for use with the Raspberry Pi utilizing OMXPlayer and DBus to play tv shows with commercial interruptions at set points in time.

I use this to play old 80's tv shows along with 80's tv commercials for that authentic 80's vibe.

It relies on https://github.com/willprice/python-omxplayer-wrapper to play videos

I also wrote a companion program in C# for Windows that utilizes FFMPEG to find commercial breaks by splitting video at black frames and also normalizing audio: https://github.com/mopenstein/VideoSplit

As well as a C# for Windows program that is a FFMPEG frontend for simple video editing that allows for precise video splitting and combining: https://github.com/mopenstein/Simple-Video-Editor

# Fully automated functioning TV Station

This project reproduces a near perfect functioning tv station.

![](https://i.imgur.com/9gi059T.png)
![](https://i.imgur.com/oiWn0el.png)
![](https://i.imgur.com/Ifjcfi7.png)
![](https://i.imgur.com/gV8SHgJ.png)

## Getting Started: The Easy Way vs. The Manual Way

Because this software relies on `omxplayer` (which was deprecated in recent Raspberry Pi OS updates), setting up the environment manually can be difficult.

### Option 1: The Pre-Configured System Image (Fastest) - https://github.com/mopenstein/raspberry_pi_tv_station/tree/main/disk%20image%20install%20files
I have provided a verified system image of a working SD card. 
* Everything is pre-installed (omxplayer, dependencies, Python environment); guaranteed to work on a Pi 3b+.
* This image is a clean install from my workign system withthe necessary project dependencies added. However, if you are uncomfortable using a pre-made image, please use Option 2.

### Option 2: Manual Installation (much harder)
If you prefer to build the system yourself:
1. Flash **Raspberry Pi OS (Legacy) Buster** using the Raspberry Pi Imager.
2. Enable the Legacy GL Driver (FKMS) in `raspi-config`.
3. Install dependencies manually:
   `sudo apt update && sudo apt install omxplayer libdbus-1-3 libdbus-1-dev`
