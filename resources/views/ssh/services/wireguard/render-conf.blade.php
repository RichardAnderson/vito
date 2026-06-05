KEY="$(sudo cat /etc/wireguard/{{ $iface }}.key)"

sudo tee /etc/wireguard/{{ $iface }}.conf > /dev/null <<'EOF'
[Interface]
Address = {{ $overlayIp }}/{{ $prefix }}
ListenPort = {{ $listenPort }}
MTU = {{ $mtu }}
PrivateKey = __VITO_WG_PRIVATE_KEY__
@foreach ($peers as $peer)

[Peer]
PublicKey = {{ $peer->public_key }}
AllowedIPs = {{ $peer->overlay_ip }}/32
Endpoint = {{ $peer->endpoint }}
PersistentKeepalive = 25
@endforeach
EOF

sudo sed -i "s|__VITO_WG_PRIVATE_KEY__|${KEY}|" /etc/wireguard/{{ $iface }}.conf

sudo chmod 600 /etc/wireguard/{{ $iface }}.conf
