<?php

namespace Base\Module\Controller;

use Base\Module\Exception\ModuleException;
use Base\Module\Service\Container;
use Base\Module\Service\Migration\UserField\UserFieldEntity;
use Base\Module\Service\Migration\UserField\UserFieldService as IUserFieldService;
use Base\Module\Service\Tool\ClassList;
use Bitrix\Main\Engine\ActionFilter\ClosureWrapper;
use Bitrix\Main\Engine\Controller;
use Bitrix\Main\Engine\CurrentUser;
use Bitrix\Main\Error;
use Bitrix\Main\EventResult;
use Bitrix\Main\Localization\Loc;

class UserFieldExport extends Controller
{
    /**
     * Действия доступны только авторизованному администратору.
     *
     * @return array
     */
    protected function getDefaultPreFilters(): array
    {
        return array_merge(parent::getDefaultPreFilters(), [
            new ClosureWrapper(function () {
                if (CurrentUser::get()->isAdmin()) {
                    return null;
                }

                $this->addError(new Error(
                    Loc::getMessage('MODULE_CONTROLLER_USER_FIELD_EXPORT_ACCESS_DENIED') ?: 'Access denied',
                    'access_denied'
                ));

                return new EventResult(EventResult::ERROR, null, null, $this);
            }),
        ]);
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
