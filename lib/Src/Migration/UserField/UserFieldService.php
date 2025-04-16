<?php

namespace Base\Module\Src\Migration\UserField;

use Bitrix\Main\Loader;
use Bitrix\Main\ObjectNotFoundException;
use Bitrix\Main\SystemException;
use CUserTypeEntity;
use Base\Module\Service\Container;
use Base\Module\Service\LazyService;
use Base\Module\Service\Migration\UserField\UserFieldService as IUserFieldService;
use Base\Module\Service\Tool\ClassList;
use Base\Module\Src\Migration\UserField\Interface\UserFieldProvider as IUserFieldProvider;
use Psr\Container\NotFoundExceptionInterface;
use ReflectionException;

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

    public function setFields(array $fields): self
    {
        $this->fields = $fields;
        return $this;
    }

    /**
     * @return void
     * @throws NotFoundExceptionInterface
     * @throws ObjectNotFoundException
     * @throws ReflectionException
     * @throws SystemException
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
            if (!$provider) {
                continue;
            }

            $params = $field['class']::getParams();
            $fieldData = array_merge($provider->getFieldData($field, $this->moduleId), $params);
            $fieldId = $userTypeEntity->Add($fieldData);

            if ($fieldId) {
                $field['params'] = $params;
                $provider->afterAdd($fieldId, $field, $this->moduleId);
            }
        }
    }

    /**
     * @return void
     * @throws NotFoundExceptionInterface
     * @throws ObjectNotFoundException
     * @throws ReflectionException
     * @throws SystemException
     */
    public function reInstall(): void
    {
        $this->install();
    }

    /**
     * @param string $type
     * @return IUserFieldProvider|null
     * @throws NotFoundExceptionInterface
     * @throws ObjectNotFoundException
     * @throws ReflectionException
     * @throws SystemException
     */
    public function getProvider(string $type): ?IUserFieldProvider
    {
        $this->registerProviders();
        return $this->providers[$type] ?? null;
    }

    /**
     * @return void
     * @throws SystemException
     * @throws ObjectNotFoundException
     * @throws NotFoundExceptionInterface
     * @throws ReflectionException
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
        $providerClasses = $classList->setSubClassesFilter([IUserFieldProvider::class])->getFromLib($relativePath);

        foreach ($providerClasses as $className) {
            $provider = new $className();
            $this->providers[$provider->getType()] = $provider;
        }
    }
}
