<?php

namespace Base\Module\Service\Migration\UserField;

/**
 * Контракт сервиса логики кнопки «Экспорт полей».
 */
interface OptionExportButtonService
{
    public const SERVICE_CODE = 'base.module.migration.userfields.option.export.button.service';

    /**
     * @return array{title: string, js: string}
     */
    public function getButton(): array;

    /**
     * @return array<int, \Bitrix\Main\Engine\ActionFilter\Base>
     */
    public function getPreFilters(): array;

    /**
     * @param array<int, class-string> $fieldClasses классы миграций модуля-источника
     * @return array{fields: array<int, array>, providers: array<int, array>}
     */
    public function getListData(array $fieldClasses): array;

    /**
     * @param string $entityId
     * @return string пустая строка, если ядро названия не отдаёт (например, пользователи)
     */
    public function getEntityName(string $entityId): string;
}
