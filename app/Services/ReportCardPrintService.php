<?php

namespace App\Services;

use App\Models\DiniyyahLedgerSnapshot;
use App\Models\ReportCard;
use DomainException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Exception\ExceptionInterface;
use Symfony\Component\Process\Process;

class ReportCardPrintService
{
    public function classCards(DiniyyahLedgerSnapshot $snapshot): Collection
    {
        return ReportCard::where('academic_term_id', $snapshot->academic_term_id)->where('classroom_term_id', $snapshot->classroom_term_id)
            ->whereIn('class_enrollment_id', $snapshot->rows()->select('class_enrollment_id'))
            ->where('report_type', 'diniyyah')->whereHas('student', fn ($q) => $q->where('status', 'active'))
            ->with('student', 'classEnrollment')->get()->sortBy(fn ($card) => [$card->classEnrollment?->roll_number ?? PHP_INT_MAX, $card->student?->name])->values();
    }

    public function data(ReportCard $card): ?array
    {
        $data = $card->snapshots()->latest('id')->first()?->snapshot_data['print'] ?? null;
        if ($data && $card->status === 'draft') {
            // Presentation only: never rewrite an existing snapshot while viewing it.
            $data['options']['principal_name'] = config('report-cards.principal_name');
        }
        if ($data && ($data['date_policy'] ?? '') === 'admin-period-v1' && $card->status === 'draft') {
            $data['options']['issue_date'] = $card->academicTerm()->first()?->report_issue_date?->format('Y-m-d');
        }

        return $data;
    }

    public function page(ReportCard $card): array
    {
        $data = $this->data($card);
        if (! $data) {
            throw new DomainException('Rapor lama tetap tersedia melalui cetakan arsip.');
        }

        return app(ReportCardTemplateRenderer::class)->page($data);
    }

    public function readiness(ReportCard $card): array
    {
        try {
            return $this->page($card)['missing'];
        } catch (DomainException $exception) {
            return [$exception->getMessage()];
        }
    }

    public function pdf(array $cards): string
    {
        $pages = [];
        foreach ($cards as $card) {
            $page = $this->page($card);
            if ($page['missing']) {
                throw new DomainException($card->student?->name.': lengkapi '.implode(', ', $page['missing']).'.');
            }
            $pages[] = $page;
        }
        if (! $pages) {
            throw new DomainException('Belum ada rapor untuk dicetak.');
        }

        return $this->renderPdf(view('report-cards.template-print', ['pages' => $pages, 'isPdf' => true])->render());
    }

    public function renderPdf(string $html): string
    {
        $binary = $this->binary();
        $disk = Storage::disk('local');
        $directory = 'rapor-render/'.bin2hex(random_bytes(16));
        $disk->put($directory.'/input.html', $html);
        $output = $disk->path($directory.'/output.pdf');
        $input = 'file://'.(PHP_OS_FAMILY === 'Windows' ? '/' : '').str_replace('\\', '/', $disk->path($directory.'/input.html'));
        try {
            $process = new Process([$binary, '--headless', '--no-sandbox', '--disable-gpu', '--disable-dev-shm-usage', '--no-pdf-header-footer',
                '--user-data-dir='.$disk->path($directory.'/profile'), '--print-to-pdf='.$output, $input]);
            $process->setTimeout(120)->run();
            if (! $process->isSuccessful() || ! is_file($output)) {
                throw new DomainException('PDF rapor gagal dibuat. Periksa konfigurasi Chromium server.');
            }
            $content = file_get_contents($output);
            if (! str_starts_with($content, '%PDF')) {
                throw new DomainException('Hasil PDF rapor tidak valid.');
            }

            return $content;
        } finally {
            $disk->deleteDirectory($directory);
        }
    }

    private function binary(): string
    {
        foreach (array_filter([config('report-cards.chromium_binary'), 'chromium-browser', 'chromium', 'C:/Program Files/Google/Chrome/Application/chrome.exe', 'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe']) as $candidate) {
            try {
                $process = new Process([$candidate, '--version']);
                if ($process->setTimeout(5)->run() === 0) {
                    return $candidate;
                }
            } catch (ExceptionInterface) {
                continue;
            }
        }
        throw new DomainException('Chromium belum tersedia. Atur REPORT_CARD_CHROMIUM_BINARY untuk unduhan PDF F4.');
    }
}
