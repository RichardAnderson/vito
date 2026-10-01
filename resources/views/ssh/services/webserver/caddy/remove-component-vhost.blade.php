sudo rm -f {!! escapeshellarg('/etc/caddy/sites-enabled/'.$name) !!}

sudo rm -f {!! escapeshellarg('/etc/caddy/sites-available/'.$name) !!}

if ! sudo service caddy reload; then
    echo 'VITO_SSH_ERROR' && exit 1
fi
