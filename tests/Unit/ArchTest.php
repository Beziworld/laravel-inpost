<?php

declare(strict_types=1);

arch('every source file declares strict types')
    ->expect('Beziworld\InPost')
    ->toUseStrictTypes();

arch('no debugging statements are left behind')
    ->expect(['dd', 'dump', 'ray', 'var_dump', 'print_r', 'die'])
    ->not->toBeUsed();

arch('data objects are immutable')
    ->expect('Beziworld\InPost\Data')
    ->toBeReadonly();

arch('exceptions share the package base exception')
    ->expect('Beziworld\InPost\Exceptions')
    ->toExtend('Beziworld\InPost\Exceptions\InPostException')
    ->ignoring('Beziworld\InPost\Exceptions\InPostException');
