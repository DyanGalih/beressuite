<?php

use App\Models\EmailLog;
use App\Models\Estimate;
use App\Models\Invoice;
use App\Models\Payment;
use Hashids\Hashids;

// Document links already sent to customers embed these hashes; changing the
// alphabet, length, or encoder breaks them.
test('document link hashes stay stable', function () {
    $vectors = [
        [Invoice::class, 'baseline-salt-invoice', 'qG1OnYe74gY8Mrb6mg2V'],
        [Estimate::class, 'baseline-salt-estimate', 'kRM6NXYVqGlR1Drlebnv'],
        [Payment::class, 'baseline-salt-payment', 'ZXez1x3KLZBL6VlyrOGB'],
        [EmailLog::class, 'baseline-salt-emaillog', 'AmBReqwQgMAEM2rE8zoY'],
    ];

    foreach ($vectors as [$model, $salt, $expected]) {
        $alphabet = config('hashids.connections.'.$model.'.alphabet');
        $length = (int) config('hashids.connections.'.$model.'.length');

        expect($length)->toBe(20)
            ->and((new Hashids($salt, $length, $alphabet))->encode(1))->toBe($expected);
    }
});
