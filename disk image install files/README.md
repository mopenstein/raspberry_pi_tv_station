# Premade Disk Image

Download a disk image of my running raspberry pi tv station: *** coming soon ***

Requires at least a 16GB micro SD card, though bigger is better since the MYSQL database is stored on the SD card.

Designed and tested on a Raspberry Pi 3b+ (runs absolutely perfectly). Also tested on a Raspberry Pi B+ v1.2 (runs slowly but does function)

You'll need to supply your own video files and you'll have to edit the settings.json file to reflect the location of those video files.

Also need to edit the cmdline.txt (see below) file on the sd card after imaging to restore it to the default settings. This is so we can get to the terminal.

Hooked composite out to TV so I could see what was going on. Plugged in ethernet wire, usb keyboard, and usb hard drive. Booted to terminal.

At the terminal, you'll need to kill python because the script auto executes on boot: sudo pkill python

You'll need to change the static IP address and Wifi settings. Edit fstab to automount your USB drive.

Once you have a IP address and are connected to your local network you can navigate your browser to the pi's IP ADDRESS and edit the settings file 'Settings Editor'.

You'll also need to create a .channel file and give it global read/write permissions. Do this even if you don't plan on using the channel feature.

Also accessible from the Web UI is a link to phpMyAdmin from where you'll want to truancate the "errors", "commercials", and "played" databases so you can start fresh.

# cmdline.txt

Those that install from the disk image will want to replace the cmdline.txt file on the newly flashed SD card with cmdline.old.txt (renamed to cmdline.txt) before booting for the first time in order to get access to the terminal. Doing this from Windows is not simple. Access to a linux PC will make life easier.

After accessing the new Raspberry Pi and making the necessary changes replace /boot/cmdline.txt with the cmdline.txt file in this Github folder. Should be easy to do while still on the Raspberry Pi. This step is required so the Pi will boot to a blank screen with no splash or console text.

# settings.json

The settings.json file in this folder is a bare bones example to getting video playing. The settings.json file included in the Disk Image is quite complex.

You'll have to edit the location of the "drive" to match your local machine.

You'll also need to make a copy of the google spreadsheet that contains the tv station programming and edit the URL in the "web-ui"->"tv_schedule_link" setting. Your version of the spreadsheet needs to be shared publicly but it's have to be editable. Example that I use is here: https://docs.google.com/spreadsheets/d/1QADkcJlcQRP1PPGCcgFtUiBjNtF-gjDE1SO4lcrBosk/
