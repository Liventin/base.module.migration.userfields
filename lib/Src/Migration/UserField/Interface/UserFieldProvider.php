<?php

namespace Base\Module\Src\Migration\UserField\Interface;

abstract class UserFieldProvider
{
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
        $this->showFilter = $showFilter ? 'Y' : 'N';
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

    public function getFieldData(array $field, string $moduleId): array
    {
        $fieldName = $field['fieldName'];
        $label = "Field {$fieldName}";

        return array_merge(
            [
                'ENTITY_ID' => $field['entityId'],
                'FIELD_NAME' => $fieldName,
                'USER_TYPE_ID' => $field['userTypeId'],
                'XML_ID' => $fieldName,
                'SORT' => $this->sort,
                'MULTIPLE' => $this->multiple,
                'MANDATORY' => $this->mandatory,
                'SHOW_FILTER' => $this->showFilter,
                'SHOW_IN_LIST' => $this->showInList,
                'EDIT_IN_LIST' => $this->editInList,
                'IS_SEARCHABLE' => $this->isSearchable,
                'SETTINGS' => $this->settings,
                'EDIT_FORM_LABEL' => ['ru' => $label],
                'LIST_COLUMN_LABEL' => ['ru' => $label],
                'LIST_FILTER_LABEL' => ['ru' => $label],
            ],
            $this->labels
        );
    }

    public function afterAdd(int $fieldId, array $field, string $moduleId): void
    {
    }

    public function getParamsToArray(): array
    {
        return [
            'SORT' => $this->sort,
            'MULTIPLE' => $this->multiple,
            'MANDATORY' => $this->mandatory,
            'SHOW_FILTER' => $this->showFilter,
            'SHOW_IN_LIST' => $this->showInList,
            'EDIT_IN_LIST' => $this->editInList,
            'IS_SEARCHABLE' => $this->isSearchable,
            'SETTINGS' => $this->settings,
            'LABELS' => $this->labels,
        ];
    }
}
