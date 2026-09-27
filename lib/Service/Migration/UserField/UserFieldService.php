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
     *     type: string,
     *     label: string,
     * }>
     */
    public function getExportableProviders(): array;

    /**
     * @return array<int, array{
     *     entityId: string,
     *     fieldName: string,
     *     userTypeId: string,
     *     label: string,
     * }>
     */
    public function getAvailableFields(): array;

    /**
     * @param string $entityId
     * @param string $fieldName
     * @param string $providerType
     * @return array{
     *     success: bool,
     *     file?: string,
     *     error?: string,
     * }
     */
    public function exportField(string $entityId, string $fieldName, string $providerType): array;

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
