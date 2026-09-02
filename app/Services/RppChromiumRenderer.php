<?php

namespace App\Services;

use App\Models\Rpp;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\Process\Process;

/**
 * Renderer Chromium untuk hasil ekspor yang sama dengan tampilan browser.
 * Pada mesin pengembangan tanpa Chromium, caller boleh memilih fallback
 * Dompdf/GD; image produksi menyediakan Chromium lewat Dockerfile.
 */
class RppChromiumRenderer
{
    public function render(Rpp $rpp, string $type): ?string
    {
        $binary = (string) env('RPP_CHROMIUM_BINARY', 'chromium-browser');
        if (! $this->binaryAvailable($binary)) {
            return null;
        }

        $token = 'rpp-render/'.uniqid((string) $rpp->id.'-', true);
        $htmlPath = $token.'.html';
        $output = Storage::disk('local')->path($token.'.'.$type);
        Storage::disk('local')->put($htmlPath, view('rpp.exports.document', ['rpp' => $rpp])->render());
        $input = 'file://'.str_replace(DIRECTORY_SEPARATOR, '/', Storage::disk('local')->path($htmlPath));

        $args = [$binary, '--headless', '--no-sandbox', '--disable-gpu', '--hide-scrollbars'];
        if ($type === 'pdf') {
            $args[] = '--print-to-pdf='.$output;
        } else {
            $args[] = '--screenshot='.$output;
            $args[] = '--window-size=1240,1754';
        }
        $args[] = $input;

        try {
            $process = new Process($args);
            $process->setTimeout(60)->run();
            return $process->isSuccessful() && is_file($output) ? (string) file_get_contents($output) : null;
        } finally {
            Storage::disk('local')->delete([$htmlPath, $token.'.'.$type]);
        }
    }

    private function binaryAvailable(string $binary): bool
    {
        $process = new Process([$binary, '--version']);
        $process->setTimeout(5)->run();
        return $process->isSuccessful();
    }
}
