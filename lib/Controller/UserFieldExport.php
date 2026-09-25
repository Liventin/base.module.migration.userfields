<?php

namespace Base\Module\Controller;

use Base\Module\Controller\Filter\AdminFilter;
use Base\Module\Exception\ModuleException;
use Base\Module\Service\Container;
use Base\Module\Service\Migration\UserField\UserFieldEntity;
use Base\Module\Service\Migration\UserField\UserFieldService as IUserFieldService;
use Base\Module\Service\Tool\ClassList;
use Bitrix\Main\Engine\ActionFilter\Authentication;
use Bitrix\Main\Engine\Controller;

class UserFieldExport extends Controller
{
    protected function getDefaultPreFilters(): array
    {
        return [
            new Authentication(),
            new AdminFilter(),
        ];
    }
    /**
     * @return array<string, mixed>
     * @throws ModuleException
     */
    public function listAction(): array
    {
        /** @var IUserFieldService $userFieldService */
        $userFieldService = Container::get(IUserFieldService::SERVICE_CODE);

        /** @var ClassList $classList */
        $classList = Container::get(ClassList::SERVICE_CODE);
        $fields = $classList
            ->setSubClassesFilter([UserFieldEntity::class])
            ->getFromLib('Migration');

        return [
            'fields' => $userFieldService
                ->setFields($fields)
                ->getAvailableFields(),
            'providers' => $userFieldService->getExportableProviders(),
        ];
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

        return $userFieldService->exportField($entityId, $fieldName, $providerType);
    }
}