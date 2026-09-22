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

echo "Starting script in 30 seconds..."
sleep 10
echo "Starting script in 20 seconds..."
sleep 10
echo "Starting script in 10 seconds..."
sleep 10

echo "We now begin our broadcast day."

export DISPLAY=:0
python /home/pi/Desktop/_station.py