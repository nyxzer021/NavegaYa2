<?php

namespace Tests\Unit;

use App\Support\CsvCell;
use PHPUnit\Framework\TestCase;

class CsvCellTest extends TestCase
{
    public function test_text_that_excel_would_run_as_a_formula_is_neutralized(): void
    {
        $this->assertSame("'=HYPERLINK(\"https://x.test\")", CsvCell::safe('=HYPERLINK("https://x.test")'));
        $this->assertSame("'+51 965", CsvCell::safe('+51 965'));
        $this->assertSame("'@SUMA(A1)", CsvCell::safe('@SUMA(A1)'));
        $this->assertSame("'-cmd", CsvCell::safe('-cmd'));
    }

    public function test_amounts_names_and_empty_values_stay_as_they_are(): void
    {
        $this->assertSame(['Rosa Quispe', '-5.00', '80.00', '', null, 12], CsvCell::row(['Rosa Quispe', '-5.00', '80.00', '', null, 12]));
    }
}
