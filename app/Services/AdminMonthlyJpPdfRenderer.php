<?php

namespace App\Services;

use Barryvdh\DomPDF\Facade\Pdf;

class AdminMonthlyJpPdfRenderer
{
    /** @param array<string, mixed> $report */
    public function render(array $report): string
    {
        return Pdf::loadView('admin.monthly-jp-report.pdf', ['report' => $this->sanitize($report)])
            ->setPaper('a3', 'landscape')
            ->output();
    }

    private function sanitize(mixed $value): mixed
    {
        if (is_array($value)) {
            return array_map(fn ($item) => $this->sanitize($item), $value);
        }
        if (! is_string($value)) {
            return $value;
        }

        return mb_check_encoding($value, 'UTF-8') ? $value : mb_convert_encoding($value, 'UTF-8', 'UTF-8');
    }
}
