# raspberry_pi_tv_station

Python2 and PHP scripts to emulate and automate a fully functional broadcast TV station

Designed and tested only on the Rasperry PI 3b+ using A/V out. 

This script is for use with the Raspberry Pi utilizing OMXPlayer and DBus to play tv shows with commercial interruptions at set points in time.

I use this to play old 80's tv shows along with 80's tv commercials for that authentic 80's vibe.

I also wrote a companion program in C# for Windows: https://github.com/mopenstein/VideoSplit

It relies on https://github.com/willprice/python-omxplayer-wrapper to play videos

# Premade disk image

Download a disk image of the project here: https://biggles.us/files/tv-station.2023.04.28.unallocated.img.rar

Requires at least a 16GB micro SD card, though bigger is better since the MYSQL database is store on the SD card.

Tested working on a Raspberry Pi 3b+

You'll need to supply your own video files and you'll have to edit the settings.json file to reflect the location of those video files.
