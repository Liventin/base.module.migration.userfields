<?php

defined('B_PROLOG_INCLUDED') || die;

return [
    'base.module.migration.userfields.option.export.button.service' => [
        'className' => Base\Module\Src\Migration\UserField\OptionExportButtonService::class,
        'constructorParams' => [
            'base.module',
            'base.module.migration.userfields.service',
        ],
    ],
];
