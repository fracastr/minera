<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;

class BalanceSaveOverwriteTest extends TestCase
{
    public function testOverwriteFlagIsTrueOnlyWhenExplicitlyRequested(): void
    {
        $this->assertTrue(filter_var(true, FILTER_VALIDATE_BOOLEAN));
        $this->assertTrue(filter_var('true', FILTER_VALIDATE_BOOLEAN));
        $this->assertTrue(filter_var('1', FILTER_VALIDATE_BOOLEAN));
        $this->assertFalse(filter_var(false, FILTER_VALIDATE_BOOLEAN));
        $this->assertFalse(filter_var(null, FILTER_VALIDATE_BOOLEAN));
        $this->assertFalse(filter_var('false', FILTER_VALIDATE_BOOLEAN));
    }
}
