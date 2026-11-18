import test from 'node:test';
import assert from 'node:assert/strict';
import { guruMenuMatches, groupIsOpen } from '../../resources/js/guru-navigation.js';

test('search finds current labels, former names and their category', () => {
    assert.equal(guruMenuMatches('Kehadiran Saya Presensi Saya GeoPresensi', 'Riwayat Saya', '  PRESENSI  SAYA '), true);
    assert.equal(guruMenuMatches('Program Semester Saya Promes Saya', 'RPP & Program Semester', 'promes'), true);
    assert.equal(guruMenuMatches('Leger Kelas Saya', 'Rapor', 'rapor'), true);
    assert.equal(guruMenuMatches('Jurnal Mengajar', 'Mengajar', 'rapor'), false);
    assert.equal(guruMenuMatches('Tásmi', 'Tahfidz', 'tasmi'), true);
    assert.equal(guruMenuMatches('Rapor', 'Rapor', '<script>'), false);
    assert.equal(guruMenuMatches('Rapor', 'Rapor', ''), true);
});

test('active category opens despite saved state and other categories restore user preferences', () => {
    assert.equal(groupIsOpen({ key: 'rapor', active: true, defaultOpen: false }, { rapor: false }), true);
    assert.equal(groupIsOpen({ key: 'mengajar', active: false, defaultOpen: true }, {}), true);
    assert.equal(groupIsOpen({ key: 'rapor', active: false, defaultOpen: true }, { rapor: false }), false);
    assert.equal(groupIsOpen({ key: 'riwayat', active: false, defaultOpen: false }, { riwayat: true }), true);
    assert.equal(groupIsOpen({ key: 'riwayat', active: false, defaultOpen: false }, { riwayat: 'invalid' }), false);
});
