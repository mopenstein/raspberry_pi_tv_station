# Raspberry Pi TV Station

Python2 and PHP scripts to emulate and automate a fully functional broadcast TV station

Designed and runs on the Rasperry PI 3b+ using A/V out. 

This script is for use with the Raspberry Pi utilizing OMXPlayer and DBus to play tv shows with commercial interruptions at set points in time.

I use this to play old 80's tv shows along with 80's tv commercials for that authentic 80's vibe.

It relies on https://github.com/willprice/python-omxplayer-wrapper to play videos

I also wrote a companion program in C# for Windows that utilizes FFMPEG to find commercial breaks by splitting video at black frames and also normalizing audio: https://github.com/mopenstein/VideoSplit

As well as a C# for Windows program that is a FFMPEG frontend for simple video editing that allows for precise video splitting and combining: https://github.com/mopenstein/Simple-Video-Editor

# Premade disk image

See the README in this project's "disk image install files" https://github.com/mopenstein/raspberry_pi_tv_station/tree/main/disk%20image%20install%20files

# Fully automated functioning TV Station

This project reproduces a near perfect functioning tv station.

![](https://i.imgur.com/9gi059T.png)
![](https://i.imgur.com/oiWn0el.png)
![](https://i.imgur.com/Ifjcfi7.png)
![](https://i.imgur.com/gV8SHgJ.png)

