<?php

namespace Tests\Unit;

use PHPUnit\Framework\TestCase;
use setasign\Fpdi\Fpdi;
use setasign\Fpdi\PdfParser\StreamReader;
use Symfony\Component\Process\Process;

class AdminMonthlyJpPdfRendererTest extends TestCase
{
    public function test_full_month_pdf_renders_within_128_mb_and_remains_a_readable_pdf(): void
    {
        $process = new Process([PHP_BINARY, '-d', 'memory_limit=128M', dirname(__DIR__).'/Fixtures/render-monthly-jp-pdf.php']);
        $process->setTimeout(60)->run();

        $this->assertTrue($process->isSuccessful(), $process->getErrorOutput().substr($process->getOutput(), 0, 1000));
        $content = $process->getOutput();
        $this->assertStringStartsWith('%PDF-', $content);
        $pdf = new Fpdi;
        try {
            $this->assertGreaterThan(3, $pdf->setSourceFile(StreamReader::createByString($content)));
        } finally {
            $pdf->cleanUp(true);
        }
    }
}
