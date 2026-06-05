umask 077

if [ ! -f /etc/wireguard/{{ $iface }}.key ]; then
    wg genkey | sudo tee /etc/wireguard/{{ $iface }}.key > /dev/null
fi

sudo chmod 600 /etc/wireguard/{{ $iface }}.key

sudo cat /etc/wireguard/{{ $iface }}.key | wg pubkey
