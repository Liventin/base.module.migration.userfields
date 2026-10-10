<?php

namespace Base\Module\Src\Migration\UserField\Providers;

use Base\Module\Src\Migration\UserField\Providers\UserFieldExportable;

class UserFieldProvider implements UserFieldExportable
{
    /**
     * Базовые поля Bitrix, которые переносятся в миграцию как есть.
     * Должны совпадать с ключами, которые читает makeFile().
     */
    private const COMMON_FIELD_KEYS = [
        'XML_ID', 'SORT', 'MULTIPLE', 'MANDATORY', 'SHOW_FILTER',
        'SHOW_IN_LIST', 'EDIT_IN_LIST', 'IS_SEARCHABLE', 'SETTINGS',
        'EDIT_FORM_LABEL', 'LIST_COLUMN_LABEL', 'LIST_FILTER_LABEL',
        'ERROR_MESSAGE', 'HELP_MESSAGE',
    ];

    protected string $sort = '100';
    protected string $multiple = 'N';
    protected string $mandatory = 'N';
    protected string $showFilter = 'N';
    protected string $showInList = 'Y';
    protected string $editInList = 'Y';
    protected string $isSearchable = 'N';
    protected array $settings = [];
    protected array $labels = [];

    public function setSort(int $sort): self
    {
        $this->sort = (string)$sort;
        return $this;
    }

    public function setMultiple(bool $multiple): self
    {
        $this->multiple = $multiple ? 'Y' : 'N';
        return $this;
    }

    public function setMandatory(bool $mandatory): self
    {
        $this->mandatory = $mandatory ? 'Y' : 'N';
        return $this;
    }

    public function setShowFilter(bool $showFilter): self
    {
        $this->showFilter = $showFilter ? 'E' : 'N';
        return $this;
    }

    public function setShowInList(bool $showInList): self
    {
        $this->showInList = $showInList ? 'Y' : 'N';
        return $this;
    }

    public function setEditInList(bool $editInList): self
    {
        $this->editInList = $editInList ? 'Y' : 'N';
        return $this;
    }

    public function setIsSearchable(bool $isSearchable): self
    {
        $this->isSearchable = $isSearchable ? 'Y' : 'N';
        return $this;
    }

    public function setSettings(array $settings): self
    {
        $this->settings = $settings;
        return $this;
    }

    public function setLabels(string $label): self
    {
        $this->labels = [
            'EDIT_FORM_LABEL' => ['ru' => $label],
            'LIST_COLUMN_LABEL' => ['ru' => $label],
            'LIST_FILTER_LABEL' => ['ru' => $label],
        ];
        return $this;
    }

    public function getFieldData(array $field): array
    {
        $fieldName = $field['fieldName'];
        $label = "Field $fieldName";

        return [
            'ENTITY_ID' => $field['entityId'],
            'FIELD_NAME' => $fieldName,
            'USER_TYPE_ID' => $field['userTypeId'],
            'XML_ID' => $fieldName,
            'EDIT_FORM_LABEL' => ['ru' => $label],
            'LIST_COLUMN_LABEL' => ['ru' => $label],
            'LIST_FILTER_LABEL' => ['ru' => $label],
        ];
    }

    /**
     * Приводит запись пользовательского поля Bitrix к виду, которым
     * оперирует миграция (та же форма, что читает makeFile()).
     *
     * Знание о базовых полях Bitrix живёт здесь, рядом с makeFile(),
     * а не в сервисе: сервис только достаёт запись из БД и отдаёт её
     * провайдеру.
     *
     * @param array $field запись из CUserTypeEntity::GetList()
     * @return array
     */
    public function buildFieldData(array $field): array
    {
        $params = [];
        foreach (self::COMMON_FIELD_KEYS as $key) {
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

    public function getParamsToArray(): array
    {
        $result = [
            'SORT' => $this->sort,
            'MULTIPLE' => $this->multiple,
            'MANDATORY' => $this->mandatory,
            'SHOW_FILTER' => $this->showFilter,
            'SHOW_IN_LIST' => $this->showInList,
            'EDIT_IN_LIST' => $this->editInList,
            'IS_SEARCHABLE' => $this->isSearchable,
            'SETTINGS' => $this->settings,
        ];

        foreach ($this->labels as $key => $value) {
            $result[$key] = $value;
        }

        return $result;
    }

    public function afterAdd(int $fieldId, array $field, string $moduleId): void
    {
    }

    /**
     * @param array $fieldData
     * @param string $namespace namespace модуля (готовый, с \ разделителями)
     * @return string
     */
    public function makeFile(array $fieldData, string $namespace): string
    {
        $entityId = self::quote((string)$fieldData['entityId']);
        $fieldName = (string)$fieldData['fieldName'];
        $className = self::toClassName($fieldName);
        $userTypeId = self::quote((string)$fieldData['userTypeId']);
        $params = $fieldData['params'] ?? [];

        return "<?php\n\n" .
            "namespace $namespace\\Migration;\n\n" .
            "use $namespace\\Service\\Migration\\UserField\\UserFieldEntity;\n\n" .
            "class $className implements UserFieldEntity\n" .
            "{\n" .
            "    public static function getEntityId(): string\n" .
            "    {\n" .
            "        return $entityId;\n" .
            "    }\n\n" .
            "    public static function getFieldName(): string\n" .
            "    {\n" .
            "        return " . self::quote($fieldName) . ";\n" .
            "    }\n\n" .
            "    public static function getUserTypeId(): string\n" .
            "    {\n" .
            "        return $userTypeId;\n" .
            "    }\n\n" .
            "    public static function getParams(): array\n" .
            "    {\n" .
            "        return " . self::arrayToPhp($params) . ";\n" .
            "    }\n" .
            "}\n";
    }

    /**
     * UF_DEPARTMENT -> UfDepartment, UF_CRM_1703946100 -> UfCrm1703946100
     */
    public static function toClassName(string $fieldName): string
    {
        $parts = explode('_', $fieldName);
        $parts = array_map(static fn (string $p): string => ucfirst(strtolower($p)), $parts);

        return implode('', $parts);
    }

    public static function getType(): string
    {
        return 'base_user_type';
    }

    /**
     * @param string $value
     * @return string
     */
    private static function quote(string $value): string
    {
        return "'" . str_replace("'", "\\'", $value) . "'";
    }

    /**
     * @param mixed $value
     * @return string
     */
    private static function arrayToPhp(mixed $value): string
    {
        if (is_array($value)) {
            $items = [];
            foreach ($value as $key => $item) {
                $keyPart = is_int($key) ? '' : self::quote((string)$key) . ' => ';
                $items[] = $keyPart . self::arrayToPhp($item);
            }
            return '[' . implode(', ', $items) . ']';
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if (is_int($value) || is_float($value)) {
            return (string)$value;
        }
        return self::quote((string)$value);
    }
}
