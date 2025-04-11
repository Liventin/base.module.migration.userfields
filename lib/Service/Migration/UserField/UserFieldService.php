<?php

namespace Base\Module\Service\Migration\UserField;

interface UserFieldService
{
    public const SERVICE_CODE = 'base.module.migration.userfield.service';

    public function setFields(array $fields): self;

    public function install(): void;

    public function reInstall(): void;

    public function getProvider(string $type): mixed;
}
