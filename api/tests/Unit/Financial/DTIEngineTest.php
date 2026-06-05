<?php

uses()->group('financial');

use App\Modules\Financial\DTIEngine;

it('returns safe status when DTI is below 30%', function () {
    // 15_000 payment / 60_000 income = 25%
    $result = DTIEngine::evaluate(15_000, 0, 60_000, 6.5, 240);
    expect($result['status'])->toBe('safe')
        ->and($result['ratio'])->toBeLessThanOrEqual(0.30);
});

it('returns caution status when DTI is between 30% and 35%', function () {
    // 19_000 / 60_000 = 31.7%
    $result = DTIEngine::evaluate(19_000, 0, 60_000, 6.5, 240);
    expect($result['status'])->toBe('caution');
});

it('returns warning status when DTI is between 35% and 40%', function () {
    // 22_000 / 60_000 = 36.7%
    $result = DTIEngine::evaluate(22_000, 0, 60_000, 6.5, 240);
    expect($result['status'])->toBe('warning');
});

it('returns critical status when DTI exceeds 40%', function () {
    // 30_000 / 60_000 = 50%
    $result = DTIEngine::evaluate(30_000, 0, 60_000, 6.5, 240);
    expect($result['status'])->toBe('critical');
});

it('includes existing obligations in the DTI ratio', function () {
    // 10_000 proposed + 10_000 existing = 20_000 / 60_000 = 33.3% (caution)
    $result = DTIEngine::evaluate(10_000, 10_000, 60_000, 6.5, 240);
    expect($result['status'])->toBe('caution');
});

it('returns critical for zero income', function () {
    $result = DTIEngine::evaluate(20_000, 0, 0, 6.5, 240);
    expect($result['status'])->toBe('critical');
});

it('recommended_max_loan is positive for safe borrower', function () {
    $result = DTIEngine::evaluate(10_000, 0, 80_000, 6.5, 240);
    expect($result['recommended_max_loan'])->toBeGreaterThan(0);
});

it('max_monthly_payment caps at safe DTI threshold minus existing obligations', function () {
    // 30% of 60_000 = 18_000 max monthly
    $result = DTIEngine::evaluate(10_000, 0, 60_000, 6.5, 240);
    expect($result['max_monthly_payment'])->toEqual(18_000.0);
});
