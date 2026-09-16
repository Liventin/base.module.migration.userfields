<?php

namespace Base\Module\Options;

use Base\Module\Service\Options\Tab;
use Bitrix\Main\Localization\Loc;

class TabMigration implements Tab
{
    public static function getId(): string
    {
        return 'migration';
    }

    public static function getName(): string
    {
        return Loc::getMessage('MODULE_TAB_MIGRATION_TITLE');
    }

    public static function getSort(): int
    {
        return 30000;
    }
}