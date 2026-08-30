<?php

defined('B_PROLOG_INCLUDED') || die;

return [
    'base.module.migration.userfields.service' => [
        'className' => Base\Module\Src\Migration\UserField\UserFieldService::class,
        'constructorParams' => [
            'base.module'
        ],
    ],
];
