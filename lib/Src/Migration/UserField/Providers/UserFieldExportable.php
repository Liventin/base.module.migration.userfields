<?php

namespace Base\Module\Src\Migration\UserField\Providers;

interface UserFieldExportable
{
    /**
     * @param array $fieldData
     * @param string $namespace namespace модуля (готовый, с \\ разделителями)
     * @return string
     */
    public function makeFile(array $fieldData, string $namespace): string;
}