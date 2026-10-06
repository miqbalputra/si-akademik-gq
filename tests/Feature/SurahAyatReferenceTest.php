<?php

namespace Tests\Feature;

use App\Services\SurahAyatReference;
use Tests\TestCase;

class SurahAyatReferenceTest extends TestCase
{
    public function test_it_accepts_valid_named_and_numbered_ranges(): void
    {
        $validator = app(SurahAyatReference::class);

        $this->assertTrue($validator->isValid('An Naas: 1-6'));
        $this->assertTrue($validator->isValid('Al-Falaq: 1 - An-Naas: 5'));
        $this->assertTrue($validator->isValid('2:1-286'));
        $this->assertTrue($validator->isValid('-'));
    }

    public function test_it_rejects_unknown_surahs_invalid_syntax_and_out_of_range_ayahs(): void
    {
        $validator = app(SurahAyatReference::class);

        $this->assertFalse($validator->isValid('An Naas: 1-7'));
        $this->assertFalse($validator->isValid('Al-Fatihah: 1-8'));
        $this->assertFalse($validator->isValid('2:0-5'));
        $this->assertFalse($validator->isValid('114:6-1'));
        $this->assertFalse($validator->isValid('Unknown: 1-2'));
        $this->assertFalse($validator->isValid('1-7'));
    }
}
