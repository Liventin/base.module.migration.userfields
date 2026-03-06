<?php

namespace Base\Module\Src\Migration\UserField;

use Base\Module\Exception\ModuleException;
use Base\Module\Service\Migration\UserField\UserFieldEntity;
use Bitrix\Main\Loader;
use CUserTypeEntity;
use Base\Module\Service\Container;
use Base\Module\Service\LazyService;
use Base\Module\Service\Migration\UserField\UserFieldService as IUserFieldService;
use Base\Module\Service\Tool\ClassList;
use Base\Module\Src\Migration\UserField\Providers\UserFieldProvider as BaseUserFieldProvider;


#[LazyService(serviceCode: IUserFieldService::SERVICE_CODE, constructorParams: ['moduleId' => LazyService::MODULE_ID])]
class UserFieldService
{
    private string $moduleId;
    private array $fields = [];
    private ?array $providers = null;

    public function __construct(string $moduleId)
    {
        $this->moduleId = $moduleId;
    }

    /**
     * @param UserFieldEntity[] $fields
     * @return $this
     */
    public function setFields(array $fields): self
    {
        $this->fields = [];

        $othersUfs = [];
        $hlblockUfs = [];

        foreach ($fields as $field) {
            if ($field::getUserTypeId() === 'hlblock') {
                $hlblockUfs[] = $field;
            } else {
                $othersUfs[] = $field;
            }
        }

        $this->fields = array_merge($othersUfs, $hlblockUfs);
        return $this;
    }

    /**
     * @return void
     * @throws ModuleException
     */
    public function install(): void
    {
        $userTypeEntity = new CUserTypeEntity();

        $fieldKeys = [];
        $validFields = [];
        foreach ($this->fields as $fieldClass) {
            if (!class_exists($fieldClass)) {
                continue;
            }

            $entityId = $fieldClass::getEntityId();
            $fieldName = $fieldClass::getFieldName();
            $userTypeId = $fieldClass::getUserTypeId();

            if (empty($entityId) || empty($fieldName) || empty($userTypeId)) {
                continue;
            }

            $fieldKeys[] = [
                'ENTITY_ID' => $entityId,
                'FIELD_NAME' => $fieldName,
            ];
            $validFields[$entityId . '::' . $fieldName] = [
                'entityId' => $entityId,
                'fieldName' => $fieldName,
                'userTypeId' => $fieldClass::getUserTypeId(),
                'class' => $fieldClass,
            ];
        }

        $existingFields = [];
        if (!empty($fieldKeys)) {
            $rsFields = CUserTypeEntity::GetList(
                [],
                [
                    'LOGIC' => 'OR',
                    $fieldKeys,
                ]
            );
            while ($field = $rsFields->Fetch()) {
                $key = $field['ENTITY_ID'] . '::' . $field['FIELD_NAME'];
                $existingFields[$key] = $field;
            }
        }

        foreach ($validFields as $key => $field) {
            if (isset($existingFields[$key])) {
                continue;
            }

            $provider = $this->getProvider($field['userTypeId']);

            $params = $field['class']::getParams();
            $fieldData = array_merge($provider->getFieldData($field), $params);
            $fieldId = $userTypeEntity->Add($fieldData);

            if ($fieldId) {
                $field['params'] = $params;
                $provider->afterAdd($fieldId, $field, $this->moduleId);
            }
        }
    }

    /**
     * @return void
     * @throws ModuleException
     */
    public function reInstall(): void
    {
        $this->install();
    }

    /**
     * @param string $type
     * @return BaseUserFieldProvider
     * @throws ModuleException
     */
    public function getProvider(string $type): BaseUserFieldProvider
    {
        $this->registerProviders();

        if (!isset($this->providers[$type])) {
            return new $this->providers[BaseUserFieldProvider::getType()];
        }

        return new $this->providers[$type];
    }

    /**
     * @return void
     * @throws ModuleException
     */
    private function registerProviders(): void
    {
        if ($this->providers !== null) {
            return;
        }

        $this->providers = [];

        /** @var ClassList $classList */
        $classList = Container::get(ClassList::SERVICE_CODE);
        $moduleRoot = Loader::getLocal('modules/' . $classList->getModuleCode() . '/lib');
        $relativePath = str_replace($moduleRoot, '', __DIR__ . '/Providers');

        $classList = Container::get(ClassList::SERVICE_CODE);
        $providerClasses = $classList->setSubClassesFilter([BaseUserFieldProvider::class])->getFromLib($relativePath);

        foreach ($providerClasses as $className) {
            $this->providers[$className::getType()] = $className;
        }
    }
}
