sudo bash -c 'wg syncconf {{ $iface }} <(wg-quick strip {{ $iface }})'
