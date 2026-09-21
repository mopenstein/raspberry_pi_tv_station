#!/bin/bash

# Ensure root privileges
if [ "$EUID" -ne 0 ]; then
    exec sudo "$0" "$@"
fi

# Update these two variables with your repository details
GITHUB_USER="mopenstein"
REPO_NAME="raspberry_pi_tv_station"
BRANCH="main"

GITHUB_RAW="https://raw.githubusercontent.com/$GITHUB_USER/$REPO_NAME/$BRANCH"
TMP_MANIFEST=$(mktemp)

echo "Checking network connectivity to GitHub..."
if ! curl -s --connect-timeout 4 -I "https://raw.githubusercontent.com" >/dev/null 2>&1; then
    echo "Error: Cannot reach GitHub. Check your network connection. Aborting."
    rm -f "$TMP_MANIFEST"
    exit 1
fi

echo "Fetching latest update manifest..."
if ! curl -s -f -L "$GITHUB_RAW/assets/manifest.txt" -o "$TMP_MANIFEST"; then
    echo "Error: Could not download manifest.txt from repository. Aborting."
    rm -f "$TMP_MANIFEST"
    exit 1
fi

echo "Applying updates..."

# Process the manifest line by line
while IFS= read -r line || [ -n "$line" ]; do
    # Strip carriage returns and leading/trailing whitespace
    line=$(echo "$line" | tr -d '\r' | xargs)

    # Skip empty lines and comment lines
    [[ -z "$line" || "$line" =~ ^# ]] && continue

    # Generic status or release message
    if [[ "$line" =~ ^msg: ]]; then
        echo "${line#msg:}"
        continue
    fi

    # 1. Handle file deletions (remove:/path/to/file)
    if [[ "$line" =~ ^remove: ]]; then
        TARGET="${line#remove:}"
        if [ -e "$TARGET" ]; then
            echo "Removing: $TARGET"
            rm -rf "$TARGET"
        fi
        continue
    fi

    # 2. Handle file sync/downloads with hash/byte check
    if [[ "$line" =~ ^sync: ]] || [[ "$line" =~ ^update: ]] || [[ "$line" =~ ^create: ]]; then
        PAYLOAD="${line#*:}"
        REMOTE_SRC="${PAYLOAD%%->*}"
        LOCAL_DEST="${PAYLOAD##*->}"

        # Ensure target directory tree exists
        mkdir -p "$(dirname "$LOCAL_DEST")"

        TMP_DOWNLOAD=$(mktemp)
        if curl -s -f -L "$GITHUB_RAW/$REMOTE_SRC" -o "$TMP_DOWNLOAD"; then
            # Compare temp download against existing destination file
            if [ -f "$LOCAL_DEST" ] && cmp -s "$TMP_DOWNLOAD" "$LOCAL_DEST"; then
                echo "Unchanged: $LOCAL_DEST"
                rm -f "$TMP_DOWNLOAD"
            else
                echo "Updating: $REMOTE_SRC -> $LOCAL_DEST"
                mv "$TMP_DOWNLOAD" "$LOCAL_DEST"
            fi
        else
            echo "Warning: Failed to fetch $REMOTE_SRC"
            rm -f "$TMP_DOWNLOAD"
        fi
        continue
    fi

    # 3. Handle shell/CLI execution (run:command)
    if [[ "$line" =~ ^run: ]]; then
        CMD="${line#run:}"
        echo "Running task: $CMD"
        eval "$CMD"
        continue
    fi

done < "$TMP_MANIFEST"

# Clean up temporary manifest file
rm -f "$TMP_MANIFEST"

# Enforce file permissions and web server ownership
chmod +x /home/pi/Desktop/*.py /home/pi/Desktop/*.sh /home/pi/*.sh 2>/dev/null
chown -R www-data:www-data /var/www/html/ 2>/dev/null

echo "Update process finished successfully."