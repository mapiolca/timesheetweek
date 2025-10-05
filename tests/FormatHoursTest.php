<?php
require_once __DIR__.'/../lib/timesheetweek.lib.php';

$cases = [
    'zero hours' => [0, '00:00'],
    'quarter hour' => [1.25, '01:15'],
    'round up minutes' => [1.999, '02:00'],
    'fifty nine point five minutes' => [59.5 / 60, '01:00'],
    'half hour' => [2.5, '02:30'],
    'over twenty four hours' => [24.5, '24:30'],
    'string input' => ['3.1', '03:06'],
];

$errors = [];
foreach ($cases as $label => [$input, $expected]) {
    $actual = formatHours($input);
    if ($actual !== $expected) {
        $errors[] = sprintf('%s: expected %s, got %s', $label, $expected, $actual);
    }
}

if ($errors) {
    fwrite(STDERR, "formatHours tests failed:\n".implode("\n", $errors)."\n");
    exit(1);
}

echo "All formatHours tests passed.\n";
