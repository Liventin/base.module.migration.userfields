<?php

namespace Base\Module\Service\Migration\UserField;

interface UserFieldService
{
    public const SERVICE_CODE = 'base.module.migration.userfields.service';

    public function setFields(array $fields): self;

    public function install(): void;

    public function reInstall(): void;

    public function getProvider(string $type): mixed;

    /**
     * @return array<int, array{
     *     class: class-string,
     *     entityId: string,
     *     fieldName: string,
     *     userTypeId: string,
     *     exists: bool,
     *     label: string,
     * }>
     */
    public function getFieldsStatus(): array;
}
