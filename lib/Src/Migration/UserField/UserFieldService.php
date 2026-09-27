<?php

namespace Base\Module\Src\Migration\UserField;

use Base\Module\Exception\ModuleException;
use Base\Module\Service\Migration\UserField\UserFieldEntity;
use Bitrix\Main\Loader;
use CUserTypeEntity;
use Base\Module\Service\Container;
use Base\Module\Service\LazyService;
use Base\Module\Service\Migration\UserField\UserFieldService as IUserFieldService;
use Base\Module\Src\Migration\UserField\Providers\UserFieldExportable;
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
     * @return array<int, array{
     *     class: class-string,
     *     entityId: string,
     *     fieldName: string,
     *     userTypeId: string,
     *     exists: bool,
     *     label: string,
     * }>
     * @throws ModuleException
     */
    public function getFieldsStatus(): array
    {
        $fieldClasses = $this->fields;

        if (empty($fieldClasses)) {
            return [];
        }

        $entities = [];
        foreach ($fieldClasses as $class) {
            $entityId = $class::getEntityId();
            $entities[$entityId] = $entityId;
        }

        $existing = [];
        $rsFields = CUserTypeEntity::GetList([], ['LANG' => LANGUAGE_ID]);
        while ($field = $rsFields->Fetch()) {
            if (!isset($entities[$field['ENTITY_ID']])) {
                continue;
            }
            $key = $field['ENTITY_ID'] . '::' . $field['FIELD_NAME'];
            $existing[$key] = $field;
        }

        $status = [];
        foreach ($fieldClasses as $class) {
            $entityId = $class::getEntityId();
            $fieldName = $class::getFieldName();
            $key = $entityId . '::' . $fieldName;
            $field = $existing[$key] ?? null;

            $label = (string)($field['EDIT_FORM_LABEL'] ?? '');
            if ($label === '') {
                $label = (string)($field['LIST_COLUMN_LABEL'] ?? '');
            }
            if ($label === '') {
                $label = $fieldName;
            }

            $status[] = [
                'class' => $class,
                'entityId' => $entityId,
                'fieldName' => $fieldName,
                'userTypeId' => $class::getUserTypeId(),
                'exists' => $field !== null,
                'label' => $label,
            ];
        }

        return $status;
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
     * @return array<int, array{
     *     entityId: string,
     *     fieldName: string,
     *     userTypeId: string,
     *     label: string,
     * }>
     * @throws ModuleException
     */
    public function getAvailableFields(): array
    {
        $existing = [];
        foreach ($this->fields as $class) {
            $key = $class::getEntityId() . '::' . $class::getFieldName();
            $existing[$key] = true;
        }

        $result = [];
        $rsFields = CUserTypeEntity::GetList([], ['LANG' => LANGUAGE_ID]);
        while ($field = $rsFields->Fetch()) {
            $key = $field['ENTITY_ID'] . '::' . $field['FIELD_NAME'];
            if (isset($existing[$key])) {
                continue;
            }

            $label = (string)($field['EDIT_FORM_LABEL'] ?? '');
            if ($label === '') {
                $label = (string)($field['LIST_COLUMN_LABEL'] ?? '');
            }
            if ($label === '') {
                $label = $field['FIELD_NAME'];
            }

            $result[] = [
                'entityId' => $field['ENTITY_ID'],
                'fieldName' => $field['FIELD_NAME'],
                'userTypeId' => $field['USER_TYPE_ID'],
                'label' => $label,
            ];
        }

        return $result;
    }

    /**
     * @return array<int, array{
     *     type: string,
     *     label: string,
     * }>
     * @throws ModuleException
     */
    public function getExportableProviders(): array
    {
        $this->registerProviders();

        $exportable = [];
        foreach ($this->providers as $type => $className) {
            $provider = new $className();
            if ($provider instanceof UserFieldExportable) {
                $exportable[] = [
                    'type' => $type,
                    'label' => $className::getType(),
                ];
            }
        }

        return $exportable;
    }

    /**
     * @param string $entityId
     * @param string $fieldName
     * @param string $providerType
     * @return array{
     *     success: bool,
     *     file?: string,
     *     error?: string,
     * }
     * @throws ModuleException
     */
    public function exportField(string $entityId, string $fieldName, string $providerType): array
    {
        $field = $this->findField($entityId, $fieldName);
        if (!$field) {
            return ['success' => false, 'error' => 'Field not found'];
        }

        $provider = $this->getProvider($providerType);
        if (!($provider instanceof UserFieldExportable)) {
            return ['success' => false, 'error' => 'Provider is not exportable'];
        }

        $namespace = str_replace('.', '\\', ucwords($this->moduleId, '.'));
        $fieldData = $this->buildFieldData($field);

        $code = $provider->makeFile($fieldData, $namespace);

        $moduleDir = Loader::getLocal('modules/' . $this->moduleId);
        if ($moduleDir === false) {
            return ['success' => false, 'error' => 'Module dir not found'];
        }

        $migrationDir = $moduleDir . '/lib/Migration';
        if (!is_dir($migrationDir)) {
            mkdir($migrationDir, 0755, true);
        }

        $filePath = $migrationDir . '/' . BaseUserFieldProvider::toClassName($fieldName) . '.php';
        file_put_contents($filePath, $code);

        return ['success' => true, 'file' => $filePath];
    }

    /**
     * @param string $entityId
     * @param string $fieldName
     * @return array|null
     */
    private function findField(string $entityId, string $fieldName): ?array
    {
        $rs = CUserTypeEntity::GetList([], ['LANG' => LANGUAGE_ID]);
        while ($field = $rs->Fetch()) {
            if ($field['ENTITY_ID'] === $entityId && $field['FIELD_NAME'] === $fieldName) {
                return $field;
            }
        }

        return null;
    }

    /**
     * @param array $field
     * @return array
     */
    private function buildFieldData(array $field): array
    {
        $params = [];
        foreach ([
            'XML_ID', 'SORT', 'MULTIPLE', 'MANDATORY', 'SHOW_FILTER',
            'SHOW_IN_LIST', 'EDIT_IN_LIST', 'IS_SEARCHABLE', 'SETTINGS',
            'EDIT_FORM_LABEL', 'LIST_COLUMN_LABEL', 'LIST_FILTER_LABEL',
            'ERROR_MESSAGE', 'HELP_MESSAGE',
        ] as $key) {
            if (isset($field[$key])) {
                $params[$key] = $field[$key];
            }
        }

        return [
            'entityId' => $field['ENTITY_ID'],
            'fieldName' => $field['FIELD_NAME'],
            'userTypeId' => $field['USER_TYPE_ID'],
            'providerType' => $field['USER_TYPE_ID'],
            'params' => $params,
        ];
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
