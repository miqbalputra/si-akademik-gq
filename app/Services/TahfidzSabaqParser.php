<?php

namespace App\Services;

class TahfidzSabaqParser
{
    /**
     * Parse sabaq_amount text to normalized baris count.
     * Conversion: 1 muka = 1 halaman = 15 baris.
     *
     * Examples:
     *   "8 Baris" -> 8
     *   "1 Muka 7 Baris" -> 15 + 7 = 22
     *   "2 Muka" -> 30
     *   "1 Juz" -> null (juz varies, cannot normalize)
     *   "Muraja'ah" -> null
     *   "Libur" -> null
     */
    public function parseToBaris(?string $amount): ?int
    {
        if (! $amount || trim($amount) === '') {
            return null;
        }

        $text = strtolower(trim($amount));
        $baris = 0;
        $hasValidUnit = false;

        // Extract muka/halaman count
        if (preg_match('/(\d+(?:\s*[\.,]\d+)?)\s*(?:muka|halaman|hal\b)/', $text, $m)) {
            $mukaCount = (float) str_replace(',', '.', $m[1]);
            $baris += (int) ($mukaCount * 15);
            $hasValidUnit = true;
        }

        // Extract baris count
        if (preg_match('/(\d+(?:\s*[\.,]\d+)?)\s*(?:baris|bar\b)/', $text, $b)) {
            $barisCount = (float) str_replace(',', '.', $b[1]);
            $baris += (int) $barisCount;
            $hasValidUnit = true;
        }

        // If only baris without muka
        if (! $hasValidUnit && preg_match('/^(\d+)\s*$/', $text, $n)) {
            $baris = (int) $n[1];
            $hasValidUnit = true;
        }

        // If text is just a number
        if (! $hasValidUnit && is_numeric($text)) {
            $baris = (int) $text;
            $hasValidUnit = true;
        }

        return $hasValidUnit ? $baris : null;
    }

    /**
     * Format baris count back to human-readable string.
     * Example: 37 -> "2 Muka 7 Baris", 15 -> "1 Muka", 8 -> "8 Baris"
     */
    public function formatFromBaris(int $baris): string
    {
        if ($baris <= 0) {
            return '0 Baris';
        }

        $muka = intdiv($baris, 15);
        $remaining = $baris % 15;

        if ($muka > 0 && $remaining > 0) {
            return "{$muka} Muka {$remaining} Baris";
        }

        if ($muka > 0) {
            return "{$muka} Muka";
        }

        return "{$remaining} Baris";
    }
}