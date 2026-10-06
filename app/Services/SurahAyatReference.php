<?php

namespace App\Services;

class SurahAyatReference
{
    /** Standard Tanzil surah order and ayah totals, used to validate stored references. */
    private const AYAH_COUNTS = [
        7,286,200,176,120,165,206,75,129,109,123,111,43,52,99,128,111,110,98,135,112,78,118,64,77,227,93,88,69,60,
        34,30,73,54,45,83,182,88,75,85,54,53,89,59,37,35,38,29,18,45,60,49,62,55,78,96,29,22,24,13,14,11,11,18,12,
        12,30,52,52,44,28,28,20,56,40,31,50,40,46,42,29,19,36,25,22,17,19,26,30,20,15,21,11,8,8,19,5,8,8,11,11,8,
        3,9,5,4,7,3,6,3,5,4,5,6,
    ];

    private const SURAH_NAMES = [
        'Al-Fatihah', 'Al-Baqarah', "Ali 'Imran", "An-Nisa'", "Al-Ma'idah", "Al-An'am", "Al-A'raf", 'Al-Anfal', 'At-Taubah',
        'Yunus', 'Hud', 'Yusuf', "Ar-Ra'd", 'Ibrahim', 'Al-Hijr', 'An-Nahl', "Al-Isra'", 'Al-Kahf', 'Maryam', 'Taha',
        "Al-Anbiya'", 'Al-Hajj', "Al-Mu'minun", 'An-Nur', 'Al-Furqan', "Asy-Syu'ara'", 'An-Naml', 'Al-Qasas', "Al-'Ankabut",
        'Ar-Rum', 'Luqman', 'As-Sajdah', 'Al-Ahzab', "Saba'", 'Fatir', 'Yasin', 'As-Saffat', 'Sad', 'Az-Zumar', 'Gafir',
        'Fussilat', 'Asy-Syura', 'Az-Zukhruf', 'Ad-Dukhan', 'Al-Jasiyah', 'Al-Ahqaf', 'Muhammad', 'Al-Fath', 'Al-Hujurat', 'Qaf',
        'Az-Zariyat', 'At-Tur', 'An-Najm', 'Al-Qamar', 'Ar-Rahman', "Al-Waqi'ah", 'Al-Hadid', 'Al-Mujadalah', 'Al-Hasyr',
        'Al-Mumtahanah', 'As-Saff', "Al-Jumu'ah", 'Al-Munafiqun', 'At-Tagabun', 'At-Talaq', 'At-Tahrim', 'Al-Mulk', 'Al-Qalam',
        'Al-Haqqah', 'Al-Ma\'arij', 'Nuh', 'Al-Jinn', 'Al-Muzzammil', 'Al-Muddassir', 'Al-Qiyamah', 'Al-Insan', 'Al-Mursalat',
        "An-Naba'", "An-Nazi'at", "'Abasa", 'At-Takwir', 'Al-Infitar', 'Al-Mutaffifin', 'Al-Insyiqaq', 'Al-Buruj', 'At-Tariq',
        "Al-A'la", 'Al-Gasyiyah', 'Al-Fajr', 'Al-Balad', 'Asy-Syams', 'Al-Lail', 'Ad-Duha', 'Asy-Syarh', 'At-Tin', "Al-'Alaq",
        'Al-Qadr', 'Al-Bayyinah', 'Az-Zalzalah', "Al-'Adiyat", "Al-Qari'ah", 'At-Takasur', "Al-'Asr", 'Al-Humazah', 'Al-Fil',
        'Quraisy', "Al-Ma'un", 'Al-Kausar', 'Al-Kafirun', 'An-Nasr', 'Al-Lahab', 'Al-Ikhlas', 'Al-Falaq', 'An-Naas',
    ];

    public function isValid(?string $reference): bool
    {
        if ($reference === null || trim($reference) === '') {
            return true;
        }

        $reference = trim($reference);
        if ($reference === '-') {
            return true;
        }

        if (! preg_match('/^\s*(?<start_surah>[^:]+)\s*:\s*(?<start_ayah>\d+)(?:\s*-\s*(?:(?<end_surah>[^:]+):\s*)?(?<end_ayah>\d+))?\s*$/u', $reference, $matches)) {
            return false;
        }

        $startSurah = $this->resolveSurah($matches['start_surah']);
        $endSurah = isset($matches['end_surah']) && trim($matches['end_surah']) !== ''
            ? $this->resolveSurah($matches['end_surah'])
            : $startSurah;
        $startAyah = (int) $matches['start_ayah'];
        $endAyah = isset($matches['end_ayah']) ? (int) $matches['end_ayah'] : $startAyah;

        if (! $startSurah || ! $endSurah || $startAyah < 1 || $endAyah < 1) {
            return false;
        }

        $counts = self::AYAH_COUNTS;
        if ($startAyah > $counts[$startSurah - 1] || $endAyah > $counts[$endSurah - 1]) {
            return false;
        }

        return [$startSurah, $startAyah] <= [$endSurah, $endAyah];
    }

    private function resolveSurah(string $input): ?int
    {
        $normalized = $this->normalize($input);
        if (ctype_digit($normalized)) {
            $number = (int) $normalized;

            return $number >= 1 && $number <= 114 ? $number : null;
        }

        foreach (self::SURAH_NAMES as $index => $name) {
            $canonical = $this->normalize($name);
            $withoutArticle = preg_replace('/^(al|an|ar|as|at|az|ad|asy)\s+/', '', $canonical);
            $aliases = [$canonical, $withoutArticle];

            if ($index === 113) {
                $aliases[] = 'an nas';
                $aliases[] = 'nas';
            }
            if ($index === 39) {
                $aliases[] = 'ghafir';
            }
            if ($index === 63) {
                $aliases[] = 'taghabun';
            }

            if (in_array($normalized, $aliases, true)) {
                return $index + 1;
            }
        }

        return null;
    }

    private function normalize(string $value): string
    {
        $value = mb_strtolower(trim($value));
        $value = preg_replace('/^(surah|surat)\s+/', '', $value);
        $value = preg_replace('/[^\pL\pN]+/u', ' ', $value);

        return trim(preg_replace('/\s+/', ' ', $value));
    }
}
