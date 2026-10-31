<?php

namespace Tests\Unit;

use App\Support\PhoneNumber;
use PHPUnit\Framework\TestCase;

class PhoneNumberTest extends TestCase
{
    public function test_it_normalizes_peruvian_mobile_numbers(): void
    {
        $this->assertSame('51953540705', PhoneNumber::peru('953 540-705'));
        $this->assertSame('51953540705', PhoneNumber::peru('+51 953 540 705'));
        $this->assertNull(PhoneNumber::peru(''));
    }
}
