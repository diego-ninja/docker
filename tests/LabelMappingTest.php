<?php

use Ninja\Docker\LabelMapping;

it('should convert to a string correctly', function () {
    $mapping = new LabelMapping('type', 'primary');

    expect($mapping)->toEqual('-l type=primary');
});
