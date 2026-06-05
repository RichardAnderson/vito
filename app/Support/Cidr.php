<?php

namespace App\Support;

class Cidr
{
    public static function isValidPrivateV4(string $cidr): bool
    {
        if (! str_contains($cidr, '/')) {
            return false;
        }

        [$ip, $prefix] = explode('/', $cidr, 2);

        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) === false) {
            return false;
        }

        if (! ctype_digit($prefix)) {
            return false;
        }

        $prefix = (int) $prefix;

        if ($prefix < 8 || $prefix > 30) {
            return false;
        }

        $isPrivate = filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_IPV4 | FILTER_FLAG_NO_PRIV_RANGE
        ) === false;

        return $isPrivate;
    }

    public static function networkAddress(string $cidr): string
    {
        [$start] = self::range($cidr);

        return long2ip($start);
    }

    /**
     * @return array{int, int}
     */
    public static function range(string $cidr): array
    {
        [$ip, $prefix] = explode('/', $cidr, 2);
        $prefix = (int) $prefix;
        $ipLong = ip2long($ip);
        $mask = $prefix === 0 ? 0 : (-1 << (32 - $prefix)) & 0xFFFFFFFF;
        $network = $ipLong & $mask;
        $broadcast = $network | (~$mask & 0xFFFFFFFF);

        return [$network, $broadcast];
    }

    /**
     * @return list<string>
     */
    public static function hosts(string $cidr): array
    {
        [$network, $broadcast] = self::range($cidr);

        $hosts = [];
        for ($ip = $network + 1; $ip < $broadcast; $ip++) {
            $hosts[] = long2ip($ip);
        }

        return $hosts;
    }

    /**
     * @param  list<string>  $taken
     */
    public static function nextFreeIp(string $cidr, array $taken): ?string
    {
        $taken = array_flip($taken);

        foreach (self::hosts($cidr) as $host) {
            if (! isset($taken[$host])) {
                return $host;
            }
        }

        return null;
    }

    public static function overlaps(string $a, string $b): bool
    {
        [$aStart, $aEnd] = self::range($a);
        [$bStart, $bEnd] = self::range($b);

        return $aStart <= $bEnd && $bStart <= $aEnd;
    }

    public static function containsIp(string $cidr, string $ip): bool
    {
        $ipLong = ip2long($ip);

        if ($ipLong === false) {
            return false;
        }

        [$start, $end] = self::range($cidr);

        return $ipLong >= $start && $ipLong <= $end;
    }

    /**
     * The lowest 10.x.0.0/24 block that does not overlap any of the given subnets.
     *
     * @param  list<string>  $existing
     */
    public static function suggestSubnet(array $existing): string
    {
        for ($octet = 0; $octet <= 255; $octet++) {
            $candidate = "10.{$octet}.0.0/24";

            foreach ($existing as $subnet) {
                if (self::overlaps($subnet, $candidate)) {
                    continue 2;
                }
            }

            return $candidate;
        }

        return '10.0.0.0/24';
    }
}
