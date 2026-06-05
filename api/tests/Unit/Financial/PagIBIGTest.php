<?php

uses()->group('financial');

use App\Modules\Financial\PagIBIG;

// ── Eligibility ────────────────────────────────────────────────────────────────

it('rejects borrowers under 18', function () {
    $result = PagIBIG::simulate(2_000_000, 50_000, 17, true, 24);
    expect($result['is_eligible'])->toBeFalse()
        ->and($result['ineligibility_reason'])->toContain('18');
});

it('rejects inactive Pag-IBIG members', function () {
    $result = PagIBIG::simulate(2_000_000, 50_000, 30, false, 30);
    expect($result['is_eligible'])->toBeFalse()
        ->and($result['ineligibility_reason'])->toContain('active');
});

it('rejects borrowers with fewer than 24 contributions', function () {
    $result = PagIBIG::simulate(2_000_000, 50_000, 30, true, 12);
    expect($result['is_eligible'])->toBeFalse()
        ->and($result['ineligibility_reason'])->toContain('24');
});

it('rejects borrowers at or above max maturity age', function () {
    $result = PagIBIG::simulate(2_000_000, 50_000, 75, true, 30);
    expect($result['is_eligible'])->toBeFalse();
});

// ── Rate tiers ─────────────────────────────────────────────────────────────────

it('applies the lowest rate tier for loans up to 450k', function () {
    $result = PagIBIG::simulate(500_000, 40_000, 30, true, 30, 400_000, 30);
    expect($result['is_eligible'])->toBeTrue()
        ->and($result['applicable_rate_pct'])->toEqual(5.375);
});

it('applies the 6.375% rate tier for loans 450k–750k', function () {
    $result = PagIBIG::simulate(800_000, 40_000, 30, true, 30, 600_000, 30);
    expect($result['applicable_rate_pct'])->toEqual(6.375);
});

it('applies the highest rate tier for loans near 6M', function () {
    $result = PagIBIG::simulate(7_000_000, 80_000, 30, true, 30, 5_500_000, 30);
    expect($result['applicable_rate_pct'])->toEqual(9.500);
});

// ── Loan amount caps ───────────────────────────────────────────────────────────

it('caps loan at PHP 6,000,000 regardless of LTV', function () {
    $result = PagIBIG::simulate(10_000_000, 100_000, 30, true, 30, 8_000_000, 30);
    expect($result['is_eligible'])->toBeTrue()
        ->and($result['loan_amount'])->toEqual(6_000_000.0);
});

it('caps loan at 90% LTV for requested amounts over LTV limit', function () {
    $result = PagIBIG::simulate(2_000_000, 60_000, 30, true, 30, 2_000_000, 30);
    expect($result['loan_amount'])->toEqual(1_800_000.0); // 90% of 2M
});

// ── Term constraint ────────────────────────────────────────────────────────────

it('constrains term by max maturity age', function () {
    $result = PagIBIG::simulate(2_000_000, 60_000, 60, true, 30, 0, 30);
    // 75 - 60 = 15 years max
    expect($result['effective_term_years'])->toEqual(15);
});

// ── Monthly amortization is reasonable ────────────────────────────────────────

it('computes a positive monthly amortization for eligible borrower', function () {
    $result = PagIBIG::simulate(2_500_000, 80_000, 35, true, 30, 2_000_000, 20);
    expect($result['is_eligible'])->toBeTrue()
        ->and($result['monthly_amortization'])->toBeGreaterThan(0);
});
