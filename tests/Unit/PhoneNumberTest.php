<?php

namespace Tests\Unit;

use App\Support\PhoneNumber;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

class PhoneNumberTest extends TestCase
{
    #[Test]
    public function it_normalizes_peruvian_mobile_numbers(): void
    {
        $this->assertSame('51953540705', PhoneNumber::international('953540705'));
        $this->assertSame('51953540705', PhoneNumber::international('+51 953 540 705'));
    }
}
