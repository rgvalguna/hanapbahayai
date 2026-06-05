<?php

uses()->group('financial');

use App\Modules\Financial\Amortization;
use InvalidArgumentException;

it('computes monthly payment with the PMT formula', function () {
    // PHP 5,000,000 at 6.5% for 20 years
    $monthly = Amortization::monthlyPayment(5_000_000, 6.5, 240);

    // PMT = 5_000_000 * [0.00541667 * (1.00541667)^240] / [(1.00541667)^240 - 1]
    expect($monthly)->toBeFloat()->toBeBetween(37_200.0, 37_400.0);
});

it('returns principal/term for zero-interest loans', function () {
    $monthly = Amortization::monthlyPayment(1_200_000, 0, 120);
    expect($monthly)->toEqual(10_000.0);
});

it('throws for non-positive principal', function () {
    Amortization::monthlyPayment(0, 6.5, 120);
})->throws(InvalidArgumentException::class);

it('generates schedule with correct first payment structure', function () {
    $schedule = Amortization::schedule(1_000_000, 6.0, 12);

    expect($schedule)->toHaveCount(12);

    $first = $schedule[0];
    expect($first['month'])->toBe(1);
    expect($first['interest'])->toEqual(round(1_000_000 * (6.0 / 100 / 12), 2));
    expect($first['balance'])->toBeFloat();
});

it('schedule ends at zero balance', function () {
    $schedule = Amortization::schedule(2_000_000, 7.0, 60);

    expect(last($schedule)['balance'])->toEqual(0.0);
});

it('sum of principal in schedule equals original loan', function () {
    $principal = 3_000_000;
    $schedule  = Amortization::schedule($principal, 6.5, 120);

    $sumPrincipal = array_sum(array_column($schedule, 'principal'));
    expect(abs($sumPrincipal - $principal))->toBeLessThan(1.0); // within 1 PHP rounding
});

it('total interest is positive for interest-bearing loan', function () {
    $total = Amortization::totalInterest(1_000_000, 6.0, 120);
    expect($total)->toBeGreaterThan(0);
});
