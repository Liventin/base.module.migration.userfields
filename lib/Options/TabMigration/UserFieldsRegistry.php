<?php

namespace Base\Module\Options\TabMigration;

use Base\Module\Exception\ModuleException;
use Base\Module\Options\TabMigration;
use Base\Module\Service\Container;
use Base\Module\Service\Migration\UserField\UserFieldEntity;
use Base\Module\Service\Migration\UserField\UserFieldService as IUserFieldService;
use Base\Module\Service\Options\Option;
use Base\Module\Service\Options\OptionsService;
use Base\Module\Service\Tool\ClassList;
use Base\Module\Src\Options\Providers\TableProvider;
use Bitrix\Main\Localization\Loc;

class UserFieldsRegistry implements Option
{
    public static function getId(): string
    {
        return 'user_fields_registry';
    }

    public static function getName(): string
    {
        return Loc::getMessage('MODULE_OPTION_USER_FIELDS_REGISTRY_TITLE');
    }

    public static function getType(): string
    {
        return 'table';
    }

    public static function getTabId(): string
    {
        return TabMigration::getId();
    }

    public static function getSort(): int
    {
        return 200;
    }

    /**
     * @return array
     * @throws ModuleException
     */
    public static function getParams(): array
    {
        /** @var OptionsService $srvOptions */
        $srvOptions = Container::get(OptionsService::SERVICE_CODE);
        /** @var TableProvider $provider */
        $provider = $srvOptions->getProvider(self::getType());

        if (!$provider) {
            return [];
        }

        /** @var IUserFieldService $userFieldService */
        $userFieldService = Container::get(IUserFieldService::SERVICE_CODE);

        /** @var ClassList $classList */
        $classList = Container::get(ClassList::SERVICE_CODE);
        $fields = $classList
            ->setSubClassesFilter([UserFieldEntity::class])
            ->getFromLib('Migration');

        $rows = [];
        foreach ($userFieldService
            ->setFields($fields)
            ->getFieldsStatus() as $field) {
            $rows[] = [
                'cells' => [
                    [
                        'text' => Loc::getMessage(
                            $field['exists']
                                ? 'MODULE_OPTION_USER_FIELDS_REGISTRY_STATUS_YES'
                                : 'MODULE_OPTION_USER_FIELDS_REGISTRY_STATUS_NO'
                        ),
                        'status' => $field['exists'] ? 'ok' : 'no',
                    ],
                    $field['entityId'],
                    $field['fieldName'],
                    $field['label'],
                    $field['userTypeId'],
                ],
                'highlight' => !$field['exists'],
                'children' => [
                    [
                        'cells' => [
                            $field['class'],
                        ],
                    ],
                ],
            ];
        }

        usort($rows, static function (array $a, array $b): int {
            $entity = $a['cells'][1] <=> $b['cells'][1];
            return $entity !== 0 ? $entity : ($a['cells'][2] <=> $b['cells'][2]);
        });

        return $provider
            ->setColumns([
                Loc::getMessage('MODULE_OPTION_USER_FIELDS_REGISTRY_COL_STATUS'),
                Loc::getMessage('MODULE_OPTION_USER_FIELDS_REGISTRY_COL_ENTITY'),
                Loc::getMessage('MODULE_OPTION_USER_FIELDS_REGISTRY_COL_FIELD'),
                Loc::getMessage('MODULE_OPTION_USER_FIELDS_REGISTRY_COL_LABEL'),
                Loc::getMessage('MODULE_OPTION_USER_FIELDS_REGISTRY_COL_TYPE'),
            ])
            ->setChildColumns([
                Loc::getMessage('MODULE_OPTION_USER_FIELDS_REGISTRY_COL_CLASS'),
            ])
            ->setRows($rows)
            ->setEmpty(Loc::getMessage('MODULE_OPTION_USER_FIELDS_REGISTRY_EMPTY'))
            ->getParamsToArray();
    }
}