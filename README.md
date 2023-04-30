# raspberry_pi_tv_station

Python2 and PHP scripts to emulate and automate a fully functional broadcast TV station

Designed and tested only on the Rasperry PI 3b+ using A/V out. 

This script is for use with the Raspberry Pi utilizing OMXPlayer and DBus to play tv shows with commercial interruptions at set points in time.

I use this to play old 80's tv shows along with 80's tv commercials for that authentic 80's vibe.

I also wrote a companion program in C# for Windows: https://github.com/mopenstein/VideoSplit

It relies on https://github.com/willprice/python-omxplayer-wrapper to play videos

# Premade disk image

Download a disk image of the project here: *** coming soon *** I just tried to do this and it was not a simple task

Requires at least a 16GB micro SD card, though bigger is better since the MYSQL database is store on the SD card.

Tested working on a Raspberry Pi 3b+

You'll need to supply your own video files and you'll have to edit the settings.json file to reflect the location of those video files.

I had to edit cmdline.txt file on the sd card after imaging to restore it to the default settings. This is so we can get to the terminal.

Hooked composite out to TV so I could see what was going on. Plugged in ethernet wire, usb keyboard, and usb hard drive. Booted to terminal.

At the terminal, you'll need to kill python because the script auto executes on boot: sudo pkill python

You'll need to change the static IP address and Wifi settings. Edit fstab to automount your USB drive.

Once you have a IP address and are connectect to your local network you can navigate your browser to the pi's IP ADDRESS and edit the settings file 'Settings Editor'.

You'll also need to create a .channel file and give it global read/write permissions.
