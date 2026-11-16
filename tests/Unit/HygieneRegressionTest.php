<?php

use Crater\Services\SerialNumberFormatter;
use Crater\Space\FilePermissionChecker;

it('keeps random serial fragments at their configured length', function () {
    $formatter = new SerialNumberFormatter();
    $generate = new ReflectionMethod($formatter, 'generateSerialNumber');
    $generate->setAccessible(true);

    expect($generate->invoke($formatter, '{{RANDOM_SEQUENCE:12}}'))->toHaveLength(12);
});

it('reports file permissions as four octal digits', function () {
    $checker = new FilePermissionChecker();
    $getPermission = new ReflectionMethod($checker, 'getPermission');
    $getPermission->setAccessible(true);

    expect($getPermission->invoke($checker, 'storage'))->toMatch('/^[0-7]{4}$/');
});
