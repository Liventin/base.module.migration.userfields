<?php

namespace Base\Module\Service\Migration\UserField;

interface UserFieldEntity
{
    public static function getEntityId(): string;

    public static function getFieldName(): string;

    public static function getUserTypeId(): string;

    public static function getParams(): array;
}
