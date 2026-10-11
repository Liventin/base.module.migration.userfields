<?php

namespace Base\Module\Controller;

use Base\Module\Exception\ModuleException;
use Base\Module\Service\Container;
use Base\Module\Service\Migration\UserField\OptionExportButtonService as IOptionExportButtonService;
use Base\Module\Service\Migration\UserField\UserFieldEntity;
use Base\Module\Service\Migration\UserField\UserFieldService as IUserFieldService;
use Base\Module\Service\Tool\ClassList;
use Bitrix\Main\Engine\Controller;

class UserFieldExport extends Controller
{
    /**
     * Доступ к действиям и логика списка — в сервисе кнопки: при
     * перенаправлении (service-redirect) реализация подставляется из
     * модуля-приёмника, поэтому контроллер остаётся тонким.
     *
     * @return array
     * @throws ModuleException
     */
    protected function getDefaultPreFilters(): array
    {
        return array_merge(
            parent::getDefaultPreFilters(),
            $this->getButtonService()->getPreFilters()
        );
    }

    /**
     * @return array<string, mixed>
     * @throws ModuleException
     */
    public function listAction(): array
    {
        /** @var ClassList $classList */
        $classList = Container::get(ClassList::SERVICE_CODE);

        // Классы миграций ищем в lib модуля-источника: при перенаправлении
        // классы лежат именно там, а сервис приёмника их не видит.
        $fields = $classList
            ->setSubClassesFilter([UserFieldEntity::class])
            ->getFromLib('Migration');

        return $this->getButtonService()->getListData($fields);
    }

    /**
     * @param string $entityId
     * @param string $fieldName
     * @param string $providerType
     * @return array<string, mixed>
     * @throws ModuleException
     */
    public function exportAction(string $entityId, string $fieldName, string $providerType): array
    {
        /** @var IUserFieldService $userFieldService */
        $userFieldService = Container::get(IUserFieldService::SERVICE_CODE);

        return $userFieldService->exportField(
            $entityId,
            $fieldName,
            $providerType,
            $this->getButtonService()->getEntityName($entityId)
        );
    }

    /**
     * @return IOptionExportButtonService
     * @throws ModuleException
     */
    private function getButtonService(): IOptionExportButtonService
    {
        return Container::get(IOptionExportButtonService::SERVICE_CODE);
    }
}
