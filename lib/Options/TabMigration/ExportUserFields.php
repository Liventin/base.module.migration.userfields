<?php

namespace Base\Module\Options\TabMigration;

use Base\Module\Exception\ModuleException;
use Base\Module\Options\TabMigration;
use Base\Module\Service\Container;
use Base\Module\Service\Migration\UserField\OptionExportButtonService as IOptionExportButtonService;
use Base\Module\Service\Options\Option;
use Base\Module\Service\Options\OptionsService;
use Bitrix\Main\Localization\Loc;

class ExportUserFields implements Option
{
    public static function getId(): string
    {
        return 'user_fields_export';
    }

    public static function getName(): string
    {
        return Loc::getMessage('MODULE_OPTION_USER_FIELDS_EXPORT_TITLE');
    }

    public static function getType(): string
    {
        return 'buttons';
    }

    public static function getTabId(): string
    {
        return TabMigration::getId();
    }

    public static function getSort(): int
    {
        return 250;
    }

    /**
     * @return array
     * @throws ModuleException
     */
    public static function getParams(): array
    {
        /** @var OptionsService $srvOptions */
        $srvOptions = Container::get(OptionsService::SERVICE_CODE);
        /** @var \Base\Module\Src\Options\Providers\ButtonsProvider $provider */
        $provider = $srvOptions->getProvider(self::getType());

        if (!$provider) {
            return [];
        }

        /** @var IOptionExportButtonService $buttonService */
        $buttonService = Container::get(IOptionExportButtonService::SERVICE_CODE);
        $button = $buttonService->getButton();

        return $provider
            ->setAlign('right')
            ->setButtons([
                [
                    'title' => $button['title'],
                    'js' => $button['js'],
                ],
            ])
            ->getParamsToArray();
    }
}
