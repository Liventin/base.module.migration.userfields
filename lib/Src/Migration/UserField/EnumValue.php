<?php

namespace Sintec\Service\Src\Migration\UserField;

class EnumValue
{
    private string $value;
    private ?string $xmlId = null;
    private int $sort = 100;
    private bool $isDefault = false;

    public function setValue(string $value): self
    {
        $this->value = $value;
        return $this;
    }

    public function setXmlId(string $xmlId): self
    {
        $this->xmlId = $xmlId;
        return $this;
    }

    public function setSort(int $sort): self
    {
        $this->sort = $sort;
        return $this;
    }

    public function setIsDefault(bool $isDefault): self
    {
        $this->isDefault = $isDefault;
        return $this;
    }

    public function getElementToArray(): array
    {
        return [
            'VALUE' => $this->value,
            'XML_ID' => $this->xmlId ?? md5($this->value),
            'SORT' => $this->sort,
            'DEF' => $this->isDefault ? 'Y' : 'N',
        ];
    }
}
