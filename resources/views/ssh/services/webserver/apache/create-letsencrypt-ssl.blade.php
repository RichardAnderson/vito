if ! sudo certbot certonly --webroot -w {!! escapeshellarg($webroot) !!} --force-renewal --noninteractive --agree-tos --cert-name {!! escapeshellarg((string) $name) !!} -m {!! escapeshellarg($email) !!} {!! $domains !!} --verbose; then
    echo 'VITO_SSH_ERROR' && exit 1
fi
