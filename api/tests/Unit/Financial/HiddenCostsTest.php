<?php

uses()->group('financial');

use App\Modules\Financial\HiddenCosts;

it('computes DST at 1.5% of loan amount', function () {
    $result = HiddenCosts::breakdown(3_000_000, 2_400_000);
    expect($result['dst'])->toEqual(2_400_000 * 0.015);
});

it('applies city transfer tax at 0.75%', function () {
    $result = HiddenCosts::breakdown(3_000_000, 2_400_000, isCity: true);
    expect($result['transfer_tax'])->toEqual(3_000_000 * 0.0075);
});

it('applies province transfer tax at 0.5%', function () {
    $result = HiddenCosts::breakdown(3_000_000, 2_400_000, isCity: false);
    expect($result['transfer_tax'])->toEqual(3_000_000 * 0.005);
});

it('excludes broker commission when buyer does not pay', function () {
    $result = HiddenCosts::breakdown(3_000_000, 2_400_000, buyerPaysBroker: false);
    expect($result['broker_commission'])->toEqual(0.0);
});

it('includes broker commission at 3% when buyer pays', function () {
    $result = HiddenCosts::breakdown(3_000_000, 2_400_000, buyerPaysBroker: true);
    expect($result['broker_commission'])->toEqual(3_000_000 * 0.03);
});

it('total equals sum of all line items', function () {
    $result = HiddenCosts::breakdown(3_000_000, 2_400_000, isCity: true, buyerPaysBroker: true);
    $sum = $result['dst'] + $result['transfer_tax'] + $result['registration']
        + $result['notarial'] + $result['broker_commission'] + $result['moving'] + $result['assoc_move_in'];
    expect($result['total'])->toEqual(round($sum, 2));
});

it('defaults moving cost to 25,000 PHP', function () {
    $result = HiddenCosts::breakdown(3_000_000, 2_400_000);
    expect($result['moving'])->toEqual(25_000.0);
});

it('includes condo association move-in dues', function () {
    $result = HiddenCosts::breakdown(3_000_000, 2_400_000, condoAssocMoveIn: 50_000);
    expect($result['assoc_move_in'])->toEqual(50_000.0);
});
