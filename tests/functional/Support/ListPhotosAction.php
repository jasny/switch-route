<?php

declare(strict_types=1);

namespace Jasny\SwitchRoute\FunctionalTests\Support;

/**
 * Test action
 * @internal
 */
class ListPhotosAction
{
    protected $photos = [
        1 => [
            ['id' => 11, 'name' => 'sunrise'],
            ['id' => 12, 'name' => 'sunset'],
        ],
        2 => [
            ['id' => 13, 'name' => 'red'],
            ['id' => 14, 'name' => 'green'],
            ['id' => 15, 'name' => 'blue'],
        ]
    ];

    public function __invoke($id)
    {
        return $this->photos[$id];
    }
}
