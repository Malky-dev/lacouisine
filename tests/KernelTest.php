<?php

namespace App\Tests;

use App\Kernel;
use PHPUnit\Framework\TestCase;

final class KernelTest extends TestCase
{
    public function testKernelConstructs(): void
    {
        $kernel = new Kernel('test', true);
        $this->assertSame('test', $kernel->getEnvironment());
        $this->assertTrue($kernel->isDebug());
    }
}




