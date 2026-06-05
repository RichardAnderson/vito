<?php

namespace Tests\Unit\Support;

use App\Support\Cidr;
use PHPUnit\Framework\TestCase;

class CidrTest extends TestCase
{
    public function test_validates_private_cidr(): void
    {
        $this->assertTrue(Cidr::isValidPrivateV4('10.88.0.0/24'));
        $this->assertTrue(Cidr::isValidPrivateV4('192.168.5.0/24'));
        $this->assertTrue(Cidr::isValidPrivateV4('172.16.0.0/12'));

        $this->assertFalse(Cidr::isValidPrivateV4('8.8.8.0/24'));
        $this->assertFalse(Cidr::isValidPrivateV4('10.0.0.0/4'));
        $this->assertFalse(Cidr::isValidPrivateV4('10.0.0.0/31'));
        $this->assertFalse(Cidr::isValidPrivateV4('not-a-cidr'));
        $this->assertFalse(Cidr::isValidPrivateV4('10.0.0.0'));
    }

    public function test_network_address(): void
    {
        $this->assertEquals('10.88.0.0', Cidr::networkAddress('10.88.0.5/24'));
        $this->assertEquals('10.88.0.128', Cidr::networkAddress('10.88.0.130/25'));
    }

    public function test_next_free_ip_skips_taken(): void
    {
        $this->assertEquals('10.88.0.1', Cidr::nextFreeIp('10.88.0.0/24', []));
        $this->assertEquals('10.88.0.3', Cidr::nextFreeIp('10.88.0.0/24', ['10.88.0.1', '10.88.0.2']));
    }

    public function test_next_free_ip_returns_null_when_exhausted(): void
    {
        $taken = Cidr::hosts('10.0.0.0/30');

        $this->assertNull(Cidr::nextFreeIp('10.0.0.0/30', $taken));
    }

    public function test_hosts_excludes_network_and_broadcast(): void
    {
        $this->assertEquals(['10.0.0.1', '10.0.0.2'], Cidr::hosts('10.0.0.0/30'));
    }

    public function test_overlaps(): void
    {
        $this->assertTrue(Cidr::overlaps('10.88.0.0/24', '10.88.0.128/25'));
        $this->assertTrue(Cidr::overlaps('10.0.0.0/8', '10.88.0.0/24'));
        $this->assertFalse(Cidr::overlaps('10.88.0.0/24', '10.89.0.0/24'));
    }

    public function test_suggest_subnet_returns_first_free_block(): void
    {
        $this->assertEquals('10.0.0.0/24', Cidr::suggestSubnet([]));
        $this->assertEquals('10.1.0.0/24', Cidr::suggestSubnet(['10.0.0.0/24']));
        $this->assertEquals('10.2.0.0/24', Cidr::suggestSubnet(['10.0.0.0/24', '10.1.0.0/16']));
    }
}
