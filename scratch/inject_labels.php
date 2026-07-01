<?php
$map = [
    'SchoolResource' => ['Sekolah', 'Sekolah'],
    'AcademicYearResource' => ['Tahun Ajaran', 'Tahun Ajaran'],
    'AcademicTermResource' => ['Periode Akademik', 'Periode Akademik'],
    'SchoolHolidayResource' => ['Libur Sekolah', 'Libur Sekolah'],
    'SchoolEventResource' => ['Acara Sekolah', 'Acara Sekolah'],
    'StudentResource' => ['Santri', 'Data Santri'],
    'TeacherResource' => ['Guru', 'Data Guru'],
    'GuardianResource' => ['Wali Santri', 'Wali Santri'],
    'ClassroomResource' => ['Kelas (Master)', 'Kelas (Master)'],
    'ClassroomTermResource' => ['Periode Kelas', 'Periode Kelas'],
    'ClassEnrollmentResource' => ['Pendaftaran Kelas', 'Pendaftaran Kelas'],
    'HomeroomAssignmentResource' => ['Wali Kelas', 'Wali Kelas'],
    'DiniyyahSubjectResource' => ['Mata Pelajaran', 'Mata Pelajaran'],
    'DiniyyahAssessmentSetResource' => ['Set Penilaian', 'Set Penilaian'],
    'DiniyyahScoreResource' => ['Nilai Ujian', 'Nilai Ujian'],
    'DiniyyahScoreComponentResource' => ['Komponen Nilai', 'Komponen Nilai'],
    'DiniyyahScoreValidationResource' => ['Validasi Nilai', 'Validasi Nilai'],
    'DiniyyahTeacherAssignmentResource' => ['Penugasan Guru', 'Penugasan Guru'],
    'DiniyyahAssessmentResultResource' => ['Hasil Penilaian', 'Hasil Penilaian'],
    'DiniyyahLedgerSnapshotResource' => ['Leger Nilai', 'Leger Nilai'],
    'ReportCardResource' => ['Rapor Santri', 'Rapor Santri'],
    'TahfidzHalaqahResource' => ['Halaqah', 'Halaqah'],
    'TahfidzWeekResource' => ['Pekan Tahfidz', 'Pekan Tahfidz'],
    'TahfidzUasCategoryResource' => ['Kategori UAS', 'Kategori UAS'],
    'TahfidzUasDayResource' => ['Jadwal UAS', 'Jadwal UAS'],
    'DiniyyahClassSubjectResource' => ['Jadwal Pelajaran', 'Jadwal Pelajaran']
];

foreach (glob(__DIR__ . '/../app/Filament/Resources/*/*Resource.php') as $file) {
    $content = file_get_contents($file);
    $basename = basename($file, '.php');
    if (isset($map[$basename])) {
        $singular = $map[$basename][0];
        $plural = $map[$basename][1];
        
        // Remove existing if any
        $content = preg_replace('/protected static \?string \$modelLabel = [^;]+;/', '', $content);
        $content = preg_replace('/protected static \?string \$pluralModelLabel = [^;]+;/', '', $content);
        
        // Inject after protected static ?string $model
        $inject = "\n    protected static ?string \$modelLabel = '$singular';\n    protected static ?string \$pluralModelLabel = '$plural';\n";
        
        $content = preg_replace('/(protected static \?string \$model = [^;]+;)/', "$1\n$inject", $content);
        
        file_put_contents($file, $content);
        echo "Updated $basename\n";
    }
}
echo "Done!\n";
