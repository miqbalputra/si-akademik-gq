import test from 'node:test';
import assert from 'node:assert/strict';
import { ScoreSaveQueue } from '../../resources/js/diniyyah-score-editor.js';

const ids = { enrollment_id: 1, component_id: 2 };
const ack = changes => ({ saved: changes.map(c => ({ ...ids, score: c.score })) });

test('typing during a pending save is serialized and uses the acknowledged original', async () => {
    const requests = [];
    let release;
    const queue = new ScoreSaveQueue(async changes => {
        requests.push(changes);
        if (requests.length === 1) await new Promise(resolve => { release = resolve; });
        return ack(changes);
    });
    queue.track('1-2', '80', '', true, ids);
    const saving = queue.flush();
    queue.track('1-2', '90', '', true, ids);
    assert.equal(queue.flush(), saving);
    release();
    await saving;
    assert.equal(requests.length, 2);
    assert.equal(requests[1][0].original, '80.00');
    assert.equal(requests[1][0].score, '90.00');
    assert.equal(queue.dirty, false);
});

test('reverting to the original during a save still persists the revert', async () => {
    let release;
    const requests = [];
    const queue = new ScoreSaveQueue(async changes => {
        requests.push(changes);
        if (requests.length === 1) await new Promise(resolve => { release = resolve; });
        return ack(changes);
    });
    queue.track('1-2', 80, '', true, ids);
    const saving = queue.flush();
    queue.track('1-2', '', '', true, ids);
    release();
    await saving;
    assert.equal(requests.length, 2);
    assert.equal(requests[1][0].score, null);
    assert.equal(requests[1][0].original, '80.00');
    assert.equal(queue.dirty, false);
});

test('network errors retain dirty entries for explicit retry', async () => {
    let fail = true;
    const queue = new ScoreSaveQueue(async changes => {
        if (fail) throw new Error('offline');
        return ack(changes);
    });
    queue.track('1-2', 0, '', true, ids);
    await assert.rejects(queue.flush(), /offline/);
    assert.equal(queue.dirty, true);
    fail = false;
    await queue.flush();
    assert.equal(queue.dirty, false);
});

test('invalid numbers stay dirty and never get sent as empty or zero', async () => {
    let count = 0;
    const queue = new ScoreSaveQueue(async changes => { count++; return ack(changes); });
    queue.track('1-2', '', '', false, ids);
    await queue.flush();
    assert.equal(count, 0);
    assert.equal(queue.dirty, true);
    queue.track('1-2', '0', '', true, ids);
    await queue.flush();
    assert.equal(count, 1);
    assert.equal(queue.dirty, false);
});
