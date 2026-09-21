#!/bin/bash
echo "Shutting down TV Station..."
# Kill Python processes
sudo pkill python
# Wait for 1 second
sleep 1
# Kill omxplayer processes
sudo pkill omxplayer
echo "Done."
