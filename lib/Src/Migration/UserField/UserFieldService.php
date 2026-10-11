<?php

namespace Base\Module\Src\Migration\UserField;

use Base\Module\Exception\ModuleException;
use Base\Module\Service\Migration\UserField\UserFieldEntity;
use Bitrix\Main\Loader;
use CCrmOwnerType;
use CIBlock;
use CUserTypeEntity;
use Bitrix\Highloadblock\HighloadBlockTable;
use Bitrix\Highloadblock\HighloadBlockLangTable;
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
     * Наименования сущностей пользовательских полей: [ENTITY_ID => caption].
     *
     * Сами записи UF хранят только идентификатор (CRM_LEAD, CRM_4, IBLOCK_3_SECTION,
     * HLBLOCK_1, …), поэтому человекочитаемое название достаём у ядра: для
     * CRM-сущностей — у CCrmOwnerType, для инфоблоков — у самого инфоблока,
     * для HL-блоков — у HighloadBlockTable (языковое название). Сущности,
     * названия которых ядро не отдаёт (пользователи), остаются без названия —
     * вызывающий код показывает идентификатор.
     *
     * @return array<string, string>
     */
    public function getEntityNames(): array
    {
        $names = $this->getCrmEntityNames();

        foreach ($this->getIblockEntityNames() as $entityId => $caption) {
            if (!isset($names[$entityId])) {
                $names[$entityId] = $caption;
            }
        }

        foreach ($this->getHighloadBlockEntityNames() as $entityId => $caption) {
            if (!isset($names[$entityId])) {
                $names[$entityId] = $caption;
            }
        }

        return $names;
    }

    /**
     * @return array<string, string>
     */
    private function getCrmEntityNames(): array
    {
        $names = [];

        if (!Loader::includeModule('crm') || !class_exists(CCrmOwnerType::class)) {
            return $names;
        }

        foreach (CCrmOwnerType::GetAllDescriptions() as $typeId => $caption) {
            $entityId = CCrmOwnerType::ResolveUserFieldEntityID((int)$typeId);
            if ($entityId !== '' && $caption !== '' && !isset($names[$entityId])) {
                $names[$entityId] = (string)$caption;
            }
        }

        return $names;
    }

    /**
     * Наименования инфоблоков: [IBLOCK_3_SECTION => «Подразделения»].
     *
     * Пользовательские поля информационных блоков живут под идентификаторами
     * вида IBLOCK_<id>_SECTION / IBLOCK_<id>_ELEMENT, где <id> — ID инфоблока.
     * Для таких сущностей показываем имя инфоблока: сам код уже говорит
     * подготовленному человеку, что это разделы (или элементы) данного инфоблока.
     *
     * @return array<string, string>
     */
    private function getIblockEntityNames(): array
    {
        $names = [];

        if (!Loader::includeModule('iblock') || !class_exists(CIBlock::class)) {
            return $names;
        }

        $result = [];
        $rsFields = CUserTypeEntity::GetList([], ['LANG' => LANGUAGE_ID]);
        while ($field = $rsFields->Fetch()) {
            $entityId = (string)$field['ENTITY_ID'];
            if (!preg_match('/^IBLOCK_(\d+)(?:_(SECTION|ELEMENT))?$/', $entityId, $matches)) {
                continue;
            }

            $iblockId = (int)$matches[1];
            if (isset($result[$iblockId])) {
                continue;
            }

            $result[$iblockId] = (string)CIBlock::GetArrayByID($iblockId, 'NAME');
        }

        foreach ($result as $iblockId => $caption) {
            if ($caption === '') {
                continue;
            }
            $names['IBLOCK_' . $iblockId . '_SECTION'] = $caption;
            $names['IBLOCK_' . $iblockId . '_ELEMENT'] = $caption;
            $names['IBLOCK_' . $iblockId] = $caption;
        }

        return $names;
    }

    /**
     * Наименования HL-блоков: [HLBLOCK_1 => «Типы групп товаров»].
     *
     * Пользовательские поля HL-блоков живут под идентификатором вида HLBLOCK_<id>.
     * Имя блока хранится отдельно от таблицы — в языковой таблице, поэтому берём
     * название по текущему языку (ru), с откатом на техническое NAME.
     * Код HLBLOCK_<id> человеку ничего не говорит, поэтому показываем имя блока.
     *
     * @return array<string, string>
     */
    private function getHighloadBlockEntityNames(): array
    {
        $names = [];

        if (!Loader::includeModule('highloadblock')) {
            return $names;
        }

        if (!class_exists(HighloadBlockTable::class) || !class_exists(HighloadBlockLangTable::class)) {
            return $names;
        }

        $langTitles = [];
        $rsLangs = HighloadBlockLangTable::getList(['select' => ['ID', 'LID', 'NAME']]);
        while ($row = $rsLangs->fetch()) {
            $hlId = (int)$row['ID'];
            if (!isset($langTitles[$hlId]) || $row['LID'] === LANGUAGE_ID) {
                $langTitles[$hlId] = (string)$row['NAME'];
            }
        }

        $rsBlocks = HighloadBlockTable::getList(['select' => ['ID', 'NAME']]);
        while ($block = $rsBlocks->fetch()) {
            $hlId = (int)$block['ID'];
            $caption = $langTitles[$hlId] ?? (string)$block['NAME'];
            if ($caption === '') {
                continue;
            }

            $names['HLBLOCK_' . $hlId] = $caption;
        }

        return $names;
    }

    /**
     * Человекочитаемое наименование сущности по её идентификатору.
     * Пустая строка, если ядро названия не отдаёт (пользователи).
     *
     * @param string $entityId
     * @return string
     */
    public function getEntityName(string $entityId): string
    {
        $names = $this->getEntityNames();

        return $names[$entityId] ?? '';
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
     *     entityName: string,
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
        $entityNames = $this->getEntityNames();
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
                'entityName' => $entityNames[$field['ENTITY_ID']] ?? '',
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
        $fieldData = $provider->buildFieldData($field);
        $fieldData['entityName'] = $this->getEntityName((string)$field['ENTITY_ID']);

        $code = $provider->makeFile($fieldData, $namespace);

        $moduleDir = Loader::getLocal('modules/' . $this->moduleId);
        if ($moduleDir === false) {
            return ['success' => false, 'error' => 'Module dir not found'];
        }

        $migrationDir = $moduleDir . '/lib/Migration/' . BaseUserFieldProvider::MIGRATION_DIR;
        if (!is_dir($migrationDir) && !mkdir($migrationDir, 0755, true) && !is_dir($migrationDir)) {
            return ['success' => false, 'error' => 'Cannot create migration dir: ' . $migrationDir];
        }

        $filePath = $migrationDir . '/' . BaseUserFieldProvider::toClassName($fieldName) . '.php';
        if (file_put_contents($filePath, $code) === false) {
            return ['success' => false, 'error' => 'Cannot write migration file: ' . $filePath];
        }

        return ['success' => true, 'file' => $filePath];
    }

    /**
     * @param string $entityId
     * @param string $fieldName
     * @return array|null
     */
    private function findField(string $entityId, string $fieldName): ?array
    {
        $rs = CUserTypeEntity::GetList([], [
            'ENTITY_ID' => $entityId,
            'FIELD_NAME' => $fieldName,
            'LANG' => LANGUAGE_ID,
        ]);

        return ($field = $rs->Fetch()) ?: null;
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

        $providerClasses = $classList->setSubClassesFilter([BaseUserFieldProvider::class])->getFromLib($relativePath);

        foreach ($providerClasses as $className) {
            $this->providers[$className::getType()] = $className;
        }
    }
}
