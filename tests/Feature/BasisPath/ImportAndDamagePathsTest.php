<?php

use App\Imports\RowNormalizer;
use App\Services\DamageCalculator;

// Basis path testing (manuscript section 3.4.2) of two small functions.
//
// RowNormalizer::contact, which cleans a contact number from an imported spreadsheet. Decisions:
//   P1 already 09XX-XXX-XXXX?  P2 ten digits starting with 9?  P3 twelve digits starting with 639?  P4 eleven digits starting with 09?
// V(G) = 4 + 1 = 5 independent paths.
//
// DamageCalculator::calculate: loss = (total + factor x partial) x yield, cost = loss x price. No decisions: V(G) = 1.

it('contact path 1: P1 true - a number already in the office format is kept', function () {
    expect(RowNormalizer::contact('0917-123-4567'))->toBe('0917-123-4567');
});

it('contact path 2: P1 false, P2 true - a number that lost its leading 0 gets it back', function () {
    expect(RowNormalizer::contact('9171234567'))->toBe('09171234567');
});

it('contact path 3: P2 false, P3 true - the +63 form becomes 09', function () {
    expect(RowNormalizer::contact('+63 917 123 4567'))->toBe('09171234567');
});

it('contact path 4: P3 false, P4 true - spaces and dots are dropped', function () {
    expect(RowNormalizer::contact('0917.123.4567'))->toBe('09171234567');
});

it('contact path 5: P1 to P4 false - an unusable number is ignored', function () {
    expect(RowNormalizer::contact('12345'))->toBeNull();
});

it('damage path 1: the only path - loss and cost follow the formula', function () {
    expect(DamageCalculator::calculate(1, 1, 0.5, 4, 20000))->toBe(['loss_mt' => 6.0, 'cost' => 120000.0])
        ->and(DamageCalculator::calculate(0.33, 0, 0.5, 4, 20000))->toBe(['loss_mt' => 1.32, 'cost' => 26400.0]);
});
