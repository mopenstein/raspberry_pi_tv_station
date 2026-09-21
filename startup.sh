#!/bin/bash

echo "Checking for time synchronization..."

# 1. If Hardware RTC is present, sync immediately and skip NTP wait
if [ -e /dev/rtc ] || [ -e /dev/rtc0 ]; then
    echo "Hardware RTC detected. Syncing system time from RTC..."
    hwclock -s 2>/dev/null
    echo "System clock set from RTC."
else
    # 2. No RTC detected: wait up to 30s for network NTP
    echo "No hardware RTC detected. Waiting for network time sync..."
    TIMEOUT=30
    ELAPSED=0

    while ! timedatectl status 2>/dev/null | grep -Eq '(NTP synchronized: yes|System clock synchronized: yes)'; do
        if [ "$ELAPSED" -ge "$TIMEOUT" ]; then
            echo "NTP sync timed out after ${TIMEOUT}s. Proceeding with current clock..."
            break
        fi
        echo "Waiting for NTP sync ($ELAPSED/$TIMEOUT)..."
        sleep 5
        ELAPSED=$((ELAPSED + 5))
    done
fi

echo "Starting script... in 5"
sleep 1
echo "Starting script... in 4"
sleep 1
echo "Starting script... in 3"
sleep 1
echo "Starting script... in 2"
sleep 1
echo "Starting script... in 1"
sleep 1
echo "Starting script..."

export DISPLAY=:0
python /home/pi/Desktop/_station.py