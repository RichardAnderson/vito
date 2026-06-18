echo "Starting atomic archive restore..."
echo "Archive path: {{ $backupPath }}"
echo "Restore path: {{ $restorePath }}"
echo "Owner: {{ $owner ?? 'vito:vito' }}"

if ! test -f '{{ $backupPath }}'; then
    echo 'VITO_SSH_ERROR: Archive does not exist' && exit 1
fi

DEST_DIR=$(dirname '{{ $restorePath }}')
if ! sudo mkdir -p "$DEST_DIR"; then
    echo 'VITO_SSH_ERROR: Failed to create destination directory' && exit 1
fi

# Stage extraction on the same filesystem as the target so the final swap is atomic
STAGING="$DEST_DIR/.deploy-restore-$$"
sudo rm -rf "$STAGING"
if ! sudo mkdir -p "$STAGING"; then
    echo 'VITO_SSH_ERROR: Failed to create staging directory' && exit 1
fi

if ! sudo tar -xzf '{{ $backupPath }}' -C "$STAGING"; then
    sudo rm -rf "$STAGING"
    echo 'VITO_SSH_ERROR: Failed to extract archive' && exit 1
fi

# Archive always contains exactly one top-level item
ITEM_COUNT=$(ls -A "$STAGING" | wc -l)
if [ "$ITEM_COUNT" -ne 1 ]; then
    sudo rm -rf "$STAGING"
    echo 'VITO_SSH_ERROR: Archive must contain exactly one top-level item' && exit 1
fi
ITEM=$(ls -A "$STAGING")

OLD="{{ $restorePath }}.deploy-old-$$"
if test -e '{{ $restorePath }}'; then
    if ! sudo mv '{{ $restorePath }}' "$OLD"; then
        sudo rm -rf "$STAGING"
        echo 'VITO_SSH_ERROR: Failed to move existing path aside' && exit 1
    fi
fi

if ! sudo mv "$STAGING/$ITEM" '{{ $restorePath }}'; then
    sudo mv "$OLD" '{{ $restorePath }}' 2>/dev/null
    sudo rm -rf "$STAGING"
    echo 'VITO_SSH_ERROR: Failed to move restored item into place' && exit 1
fi

sudo rm -rf "$STAGING" "$OLD"

if ! sudo chown -R '{{ $owner ?? 'vito:vito' }}' '{{ $restorePath }}'; then
    echo 'VITO_SSH_ERROR: Failed to set owner' && exit 1
fi

@if($permissions)
if ! sudo chmod '{{ $permissions }}' '{{ $restorePath }}'; then
    echo 'VITO_SSH_ERROR: Failed to set permissions' && exit 1
fi
@endif

echo "Atomic archive restore completed successfully!"
echo "Restored to: {{ $restorePath }}"
