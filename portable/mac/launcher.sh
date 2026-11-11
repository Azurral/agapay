#!/bin/bash
# AGAPAY for Mac (the panel's copy): what runs when AGAPAY.app is opened. Built into the app by portable/build-mac.py.
#   Not running: copies the program and the sample records to ~/Library/Application Support/AGAPAY (the first time),
#                starts PHP's web server there in the background and opens AGAPAY in the browser.
#   Running:     asks whether to open AGAPAY, stop it, or start over with fresh sample records.
# macOS's own bash 3.2, osascript (dialogs), open, ditto and xattr only; nothing is installed.

APP_VERSION="__VERSION__"
URL="http://127.0.0.1:8742"
BUNDLED="$(cd "$(dirname "$0")/../Resources" && pwd)"
HOME_DIR="$HOME/Library/Application Support/AGAPAY"
LOG="$HOME_DIR/agapay-server.log"
if [ "$(uname -m)" = "arm64" ]; then PHP="$HOME_DIR/php-arm64"; else PHP="$HOME_DIR/php-intel"; fi
FLAGS="__PHP_FLAGS__"

say() {  # a message box; $1 = text
    osascript -e 'on run argv' -e 'activate' -e 'display dialog (item 1 of argv) with title "AGAPAY" buttons {"OK"} default button "OK"' -e 'end run' "$1" >/dev/null 2>&1
}

running() { curl -s -o /dev/null -m 3 "$URL/login"; }

stop_server() {
    if [ -f "$HOME_DIR/agapay-server.pid" ]; then
        kill "$(cat "$HOME_DIR/agapay-server.pid")" 2>/dev/null
        rm -f "$HOME_DIR/agapay-server.pid"
    fi
    pkill -f "$HOME_DIR/php-" 2>/dev/null
    for _ in 1 2 3 4 5 6 7 8 9 10; do running || return 0; sleep 0.5; done
}

# First start (or a newer AGAPAY): copy the program out of the app, which macOS may keep read-only.
prepare() {
    if [ -f "$HOME_DIR/version-$APP_VERSION" ]; then return 0; fi
    rm -rf "$HOME_DIR"
    mkdir -p "$HOME_DIR" || return 1
    ditto "$BUNDLED/agapay" "$HOME_DIR/agapay" || return 1
    ditto "$BUNDLED/php-arm64" "$HOME_DIR/php-arm64" && ditto "$BUNDLED/php-intel" "$HOME_DIR/php-intel" || return 1
    # Downloaded files are marked "from the internet" and macOS will not run the PHP inside until the mark is gone.
    xattr -dr com.apple.quarantine "$HOME_DIR" 2>/dev/null
    chmod +x "$HOME_DIR/php-arm64" "$HOME_DIR/php-intel"
    touch "$HOME_DIR/version-$APP_VERSION"
}

start_server() {
    prepare || { say "AGAPAY could not be set up in $HOME_DIR."; return 1; }
    cd "$HOME_DIR/agapay/public" || return 1
    # shellcheck disable=SC2086
    nohup "$PHP" $FLAGS -S 127.0.0.1:8742 ../vendor/laravel/framework/src/Illuminate/Foundation/resources/server.php >"$LOG" 2>&1 &
    echo $! >"$HOME_DIR/agapay-server.pid"
    disown 2>/dev/null
    for _ in $(seq 1 40); do running && return 0; sleep 0.5; done
    say "AGAPAY did not start. If another program uses port 8742, close it and open AGAPAY again. Details: $LOG"
    return 1
}

if running; then
    CHOICE=$(osascript -e 'activate' -e 'button returned of (display dialog "AGAPAY is running." with title "AGAPAY" buttons {"Start over with fresh sample records", "Stop AGAPAY", "Open AGAPAY"} default button "Open AGAPAY")' 2>/dev/null)
    case "$CHOICE" in
        "Stop AGAPAY")
            stop_server
            say "AGAPAY has stopped. Open the AGAPAY app again to start it."
            ;;
        "Start over with fresh sample records")
            CONFIRM=$(osascript -e 'activate' -e 'button returned of (display dialog "Delete everything added or changed in AGAPAY and go back to the original sample records?" with title "AGAPAY" buttons {"Cancel", "Start over"} default button "Cancel")' 2>/dev/null)
            if [ "$CONFIRM" = "Start over" ]; then
                stop_server
                rm -rf "$HOME_DIR"
                start_server && open "$URL"
            fi
            ;;
        "Open AGAPAY")
            open "$URL"
            ;;
    esac
    exit 0
fi

start_server && open "$URL"
exit 0
