for conf in /etc/wireguard/*.conf; do
    [ -e "$conf" ] || continue
    iface=$(basename "$conf" .conf)
    sudo systemctl disable --now "wg-quick@${iface}" || true
done

sudo DEBIAN_FRONTEND=noninteractive apt-get remove -y wireguard wireguard-tools

sudo DEBIAN_FRONTEND=noninteractive apt-get autoremove -y

sudo rm -rf /etc/wireguard
