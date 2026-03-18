<?php

declare(strict_types=1);

require __DIR__ . '/../app/Helpers/CursorPagination.php';

use App\Helpers\CursorPagination;

function assertSame($expected, $actual, string $label): void
{
    if ($expected !== $actual) {
        fwrite(STDERR, "FAIL: {$label}\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true) . "\n");
        exit(1);
    }
}

$cursor = '2026-03-18 12:34:56|123';
assertSame(['2026-03-18 12:34:56', 123], CursorPagination::decode($cursor), 'decode valid cursor');
assertSame($cursor, CursorPagination::encode('2026-03-18 12:34:56', 123), 'encode valid cursor');

assertSame(null, CursorPagination::decode(''), 'decode empty');
assertSame(null, CursorPagination::decode('invalid'), 'decode invalid format');
assertSame(null, CursorPagination::decode('2026-03-18 12:34:56|0'), 'decode invalid id');
assertSame(null, CursorPagination::decode('not-a-date|10'), 'decode invalid date');

assertSame(null, CursorPagination::encode('', 10), 'encode empty date');
assertSame(null, CursorPagination::encode('2026-03-18 12:34:56', 'x'), 'encode invalid id');
assertSame(null, CursorPagination::encode('2026-03-18 12:34:56', 0), 'encode id zero');

fwrite(STDOUT, "OK\n");
