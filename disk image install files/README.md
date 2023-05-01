Those that install from the disk image will want to replace the cmdline.txt file on the newly flashed SD card with cmdline.old.txt (renamed to cmdline.txt) before booting for the first time in order to get access to the terminal. Doing this from Windows is not simple. Access to a linux PC will make life easier.

After accessing the new Raspberry Pi and making the necessary changes replace /boot/cmdline.txt with the cmdline.txt file in this Github folder. Should be easy to do while still on the Raspberry Pi.

This step is required so the Pi will boot to a blank screen with no splash or console text.
