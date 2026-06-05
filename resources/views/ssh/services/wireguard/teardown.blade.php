sudo systemctl disable --now wg-quick@{{ $iface }} || true

sudo rm -f /etc/wireguard/{{ $iface }}.conf

sudo rm -f /etc/wireguard/{{ $iface }}.key
