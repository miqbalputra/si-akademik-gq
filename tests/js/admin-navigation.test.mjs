import test from 'node:test';
import assert from 'node:assert/strict';
import { menuMatches } from '../../resources/js/admin-navigation.js';

test('menu search matches visible labels or department without case sensitivity', () => {
    assert.equal(menuMatches('Peringkat Semua Kelas', 'Rapor', 'rapor'), true);
    assert.equal(menuMatches('Rapor Diniyyah', 'Rapor', '  RAPOR   DINIYYAH '), true);
    assert.equal(menuMatches('Rekap & Ekspor Tasmi', 'Tahfidz', 'tahfidz'), true);
    assert.equal(menuMatches('Pengguna', 'Khusus Admin', 'rapor'), false);
});

test('empty and unmatched searches have predictable results', () => {
    assert.equal(menuMatches('Halaqah', 'Tahfidz', ''), true);
    assert.equal(menuMatches('Halaqah', 'Tahfidz', '<script>'), false);
    assert.equal(menuMatches('Tásmi', 'Tahfidz', 'tasmi'), true);
});
