sudo rm -f {!! escapeshellarg('/etc/nginx/sites-enabled/'.$name) !!}

sudo rm -f {!! escapeshellarg('/etc/nginx/sites-available/'.$name) !!}

if ! sudo service nginx reload; then
    echo 'VITO_SSH_ERROR' && exit 1
fi
