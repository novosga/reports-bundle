<?php

declare(strict_types=1);

/*
 * This file is part of the Novo SGA project.
 *
 * (c) Rogerio Lino <rogeriolino@gmail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Novosga\ReportsBundle\Tests\Twig;

use DateTime;
use Novosga\ReportsBundle\Twig\ReportExtension;
use PHPUnit\Framework\TestCase;

class ReportExtensionTest extends TestCase
{
    private ReportExtension $extension;

    protected function setUp(): void
    {
        $this->extension = new ReportExtension();
    }

    public function testSecToDateFilterWithInteger(): void
    {
        $seconds = 3661; // 1 hour, 1 minute, 1 second
        $result = $this->extension->secToDateFilter($seconds);

        $this->assertInstanceOf(DateTime::class, $result);
        $this->assertEquals('01:01:01', $result->format('H:i:s'));
    }

    public function testSecToDateFilterWithFloat(): void
    {
        $seconds = 47.25; // Should round to 47 seconds
        $result = $this->extension->secToDateFilter($seconds);

        $this->assertInstanceOf(DateTime::class, $result);
        $this->assertEquals('00:00:47', $result->format('H:i:s'));
    }

    public function testSecToDateFilterWithFloatRounding(): void
    {
        $seconds = 47.75; // Should round to 48 seconds
        $result = $this->extension->secToDateFilter($seconds);

        $this->assertInstanceOf(DateTime::class, $result);
        $this->assertEquals('00:00:48', $result->format('H:i:s'));
    }

    public function testSecToDateFilterWithLargeFloat(): void
    {
        $seconds = 3661.8; // Should round to 3662 seconds (1:01:02)
        $result = $this->extension->secToDateFilter($seconds);

        $this->assertInstanceOf(DateTime::class, $result);
        $this->assertEquals('01:01:02', $result->format('H:i:s'));
    }

    public function testSecToDateFilterWithStringInput(): void
    {
        $seconds = "47.25"; // String representation should work the same
        $result = $this->extension->secToDateFilter($seconds);

        $this->assertInstanceOf(DateTime::class, $result);
        $this->assertEquals('00:00:47', $result->format('H:i:s'));
    }

    public function testSecToDateFilterWithHighPrecisionStringInput(): void
    {
        $seconds = "47.2500000000000000"; // High precision string from original issue
        $result = $this->extension->secToDateFilter($seconds);

        $this->assertInstanceOf(DateTime::class, $result);
        $this->assertEquals('00:00:47', $result->format('H:i:s'));
    }
}
