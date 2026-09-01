<?php

namespace Base\Module\Install;

use Base\Module\Install\Interface\Install;
use Base\Module\Install\Interface\ReInstall;
use Base\Module\Service\Container;
use Base\Module\Exception\ModuleException;
use Base\Module\Service\Migration\UserField\UserFieldEntity;
use Base\Module\Service\Migration\UserField\UserFieldService as IUserFieldService;
use Base\Module\Service\Tool\ClassList;

class UserFieldInstaller implements Install, ReInstall
{
    /**
     * @return array
     * @throws ModuleException
     */
    private function getFields(): array
    {
        /** @var ClassList $classList */
        $classList = Container::get(ClassList::SERVICE_CODE);
        return $classList->setSubClassesFilter([UserFieldEntity::class])->getFromLib('Migration');
    }

    /**
     * @throws ModuleException
     */
    public function install(): void
    {
        /** @var IUserFieldService $userFieldService */
        $userFieldService = Container::get(IUserFieldService::SERVICE_CODE);
        $userFieldService->setFields($this->getFields())->install();
    }

    /**
     * @throws ModuleException
     */
    public function reInstall(): void
    {
        /** @var IUserFieldService $userFieldService */
        $userFieldService = Container::get(IUserFieldService::SERVICE_CODE);
        $userFieldService->setFields($this->getFields())->reInstall();
    }

    public function getInstallSort(): int
    {
        return 750;
    }

    public function getReInstallSort(): int
    {
        return 750;
    }
}
