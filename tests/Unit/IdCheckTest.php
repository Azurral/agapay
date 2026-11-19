<?php

use App\Support\IdCheck;

it('finds the name on an ID even when the text reader slips by a letter', function () {
    $text = "REPUBLIKA NG PILIPINAS\nVOTER'S ID\nDELA CRUZ, JUAN ABENOJA\nVIN 1234-5678";

    expect(IdCheck::nameFound($text, 'Juan', 'Dela Cruz'))->toBeTrue()
        ->and(IdCheck::nameFound(str_replace('JUAN', 'JUAM', $text), 'Juan', 'Dela Cruz'))->toBeTrue()          // one misread letter
        ->and(IdCheck::nameFound("FAGYAN, ANA\nRSBSA 14-44-01-001", 'Ana', 'Fagyan'))->toBeTrue()
        ->and(IdCheck::nameFound('Peña, Rosario', 'Rosario', 'Pena'))->toBeTrue()                             // ñ / n
        ->and(IdCheck::nameFound($text, 'Pedro', 'Dela Cruz'))->toBeFalse()                                  // another person
        ->and(IdCheck::nameFound($text, 'Juan', 'Santos'))->toBeFalse()
        ->and(IdCheck::nameFound('', 'Juan', 'Dela Cruz'))->toBeFalse();
});

it('finds the ID number on an ID, ignoring dashes, spaces and letters misread as digits', function () {
    $text = "VOTER'S ID\nVIN: 1234 - 5678\nDELA CRUZ, JUAN";

    expect(IdCheck::numberFound($text, 'VIN-1234-5678'))->toBeTrue()
        ->and(IdCheck::numberFound('No. 14-44-O1-OO1', '14-44-01-001'))->toBeTrue()   // O read for 0
        ->and(IdCheck::numberFound($text, 'VIN-1234-9999'))->toBeFalse()
        ->and(IdCheck::numberFound($text, ''))->toBeFalse();
});
