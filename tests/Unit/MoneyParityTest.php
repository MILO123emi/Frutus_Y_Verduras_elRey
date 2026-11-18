<?php

use App\Support\Money;

it('coincide con los 206 casos compartidos de dinero del navegador', function () {
    $cases = json_decode(file_get_contents(__DIR__.'/../fixtures/money-cases.json'), true, flags: JSON_THROW_ON_ERROR);
    expect($cases)->toHaveCount(206);
    foreach ($cases as $case) {
        expect(Money::lineTotal($case['quantity'], $case['price']))->toBe($case['total'])
            ->and(Money::tax($case['total'], '0.15'))->toBe($case['tax15'])
            ->and(Money::tax($case['total'], '0.18'))->toBe($case['tax18']);
    }
});
