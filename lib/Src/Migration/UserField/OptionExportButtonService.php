<?php

namespace Base\Module\Src\Migration\UserField;

use Base\Module\Exception\ModuleException;
use Base\Module\Service\Container;
use Base\Module\Service\LazyService;
use Base\Module\Service\Migration\UserField\OptionExportButtonService as IOptionExportButtonService;
use Base\Module\Service\Migration\UserField\UserFieldService as IUserFieldService;
use Bitrix\Highloadblock\HighloadBlockLangTable;
use Bitrix\Highloadblock\HighloadBlockTable;
use Bitrix\Iblock\IblockTable;
use Bitrix\Main\Engine\ActionFilter\ClosureWrapper;
use Bitrix\Main\Engine\CurrentUser;
use Bitrix\Main\Error;
use Bitrix\Main\EventResult;
use Bitrix\Main\Loader;
use Bitrix\Main\Localization\Loc;
use Bitrix\Main\UserFieldTable;
use CCrmOwnerType;
use CUserTypeEntity;

/**
 * Логика кнопки «Экспорт полей» и её контроллера.
 *
 * Всё, что относится к кнопке, живёт здесь: JS, который её рисует, данные для
 * диалога экспорта, фильтры доступа контроллера и человекочитаемые имена
 * сущностей пользовательских полей. За счёт этого при настроенном
 * перенаправлении (service-redirect) модуль-источник оставляет у себя только
 * вызовы методов сервиса, а сам код берётся сервис-локатором из
 * модуля-приёмника.
 */
#[LazyService(
    serviceCode: IOptionExportButtonService::SERVICE_CODE,
    constructorParams: [LazyService::MODULE_ID, IUserFieldService::SERVICE_CODE]
)]
class OptionExportButtonService
{
    private string $moduleId;
    private string $userFieldServiceCode;

    /**
     * @param string $moduleId модуль-источник вызова (при перенаправлении — он же)
     * @param string $userFieldServiceCode код сервиса полей в текущей сборке
     */
    public function __construct(string $moduleId, string $userFieldServiceCode)
    {
        $this->moduleId = $moduleId;
        $this->userFieldServiceCode = $userFieldServiceCode;
    }

    /**
     * @return array{title: string, js: string}
     */
    public function getButton(): array
    {
        $messages = [
            'title' => Loc::getMessage('MODULE_OPTION_USER_FIELDS_EXPORT_TITLE'),
            'entity' => Loc::getMessage('MODULE_OPTION_USER_FIELDS_EXPORT_ENTITY'),
            'field' => Loc::getMessage('MODULE_OPTION_USER_FIELDS_EXPORT_FIELD'),
            'provider' => Loc::getMessage('MODULE_OPTION_USER_FIELDS_EXPORT_PROVIDER'),
            'ok' => Loc::getMessage('MODULE_OPTION_USER_FIELDS_EXPORT_OK'),
            'cancel' => Loc::getMessage('MODULE_OPTION_USER_FIELDS_EXPORT_CANCEL'),
        ];

        $actionPrefix = $this->getActionPrefix();

        $js = "var lang = " . json_encode($messages, JSON_UNESCAPED_UNICODE) . ";\n"
            . "function ufExportError(response) {\n"
            . "    if (response && response.errors && response.errors.length) { return response.errors[0].message; }\n"
            . "    return 'error';\n"
            . "}\n"
            . "BX.ajax.runAction('" . $actionPrefix . ".list', {}).then(function (response) {\n"
            . "    var data = response.data;\n"
            . "    var box = BX.create('div', {style: {padding: '16px 20px', boxSizing: 'border-box'}});\n"
            . "    var entities = [];\n"
            . "    var seen = {};\n"
            . "    data.fields.forEach(function (f) {\n"
            . "        if (seen[f.entityId]) { return; }\n"
            . "        seen[f.entityId] = true;\n"
            . "        entities.push({value: f.entityId, text: f.entityName ? f.entityName + ' (' + f.entityId + ')' : f.entityId});\n"
            . "    });\n"
            . "    var entitySelect = BX.create('select', {props: {id: 'uf-export-entity'}, style: {width: '520px', maxWidth: '520px'}});\n"
            . "    entities.forEach(function (e) {\n"
            . "        entitySelect.appendChild(BX.create('option', {props: {value: e.value}, text: e.text}));\n"
            . "    });\n"
            . "    var fieldSelect = BX.create('select', {props: {id: 'uf-export-field'}, style: {width: '520px', maxWidth: '520px'}});\n"
            . "    var providerSelect = BX.create('select', {props: {id: 'uf-export-provider'}, style: {width: '520px', maxWidth: '520px'}});\n"
            . "    data.providers.forEach(function (p) {\n"
            . "        providerSelect.appendChild(BX.create('option', {props: {value: p.type}, text: p.label}));\n"
            . "    });\n"
            . "    function fillFields(entityId) {\n"
            . "        while (fieldSelect.firstChild) { fieldSelect.removeChild(fieldSelect.firstChild); }\n"
            . "        data.fields.forEach(function (f) {\n"
            . "            if (f.entityId !== entityId) { return; }\n"
            . "            var fieldText = (f.label && f.label !== f.fieldName) ? f.fieldName + ' — ' + f.label : f.fieldName;\n"
            . "            fieldSelect.appendChild(BX.create('option', {props: {value: f.fieldName}, text: fieldText}));\n"
            . "        });\n"
            . "    }\n"
            . "    box.appendChild(BX.create('div', {style: {display: 'flex', alignItems: 'center', marginBottom: '10px'}, children: [\n"
            . "        BX.create('label', {style: {width: '110px', color: '#535c69'}, text: lang.entity + ':'}), entitySelect\n"
            . "    ]}));\n"
            . "    box.appendChild(BX.create('div', {style: {display: 'flex', alignItems: 'center', marginBottom: '10px'}, children: [\n"
            . "        BX.create('label', {style: {width: '110px', color: '#535c69'}, text: lang.field + ':'}), fieldSelect\n"
            . "    ]}));\n"
            . "    box.appendChild(BX.create('div', {style: {display: 'flex', alignItems: 'center', marginBottom: '10px'}, children: [\n"
            . "        BX.create('label', {style: {width: '110px', color: '#535c69'}, text: lang.provider + ':'}), providerSelect\n"
            . "    ]}));\n"
            . "    if (entities.length) { fillFields(entities[0].value); }\n"
            . "    BX.bind(entitySelect, 'change', function () { fillFields(this.value); });\n"
            . "    var popup = new BX.PopupWindow('userfield-export', null, {\n"
            . "        zIndex: 200,\n"
            . "        width: 700,\n"
            . "        content: box,\n"
            . "        titleBar: lang.title,\n"
            . "        closeIcon: true,\n"
            . "        closeByEsc: true,\n"
            . "        autoHide: false,\n"
            . "        overlay: {backgroundColor: 'black', opacity: 40},\n"
            . "        buttons: [\n"
            . "            new BX.PopupWindowButton({\n"
            . "                text: lang.ok,\n"
            . "                className: 'popup-window-button-accept',\n"
            . "                events: {click: function () {\n"
            . "                    BX.ajax.runAction('" . $actionPrefix . ".export', {\n"
            . "                        data: {\n"
            . "                            entityId: entitySelect.value,\n"
            . "                            fieldName: fieldSelect.value,\n"
            . "                            providerType: providerSelect.value\n"
            . "                        }\n"
            . "                    }).then(function (response) {\n"
            . "                        if (response.data && response.data.success) { location.reload(); }\n"
            . "                        else { alert(response.data && response.data.error ? response.data.error : 'error'); }\n"
            . "                    }).catch(function (response) { alert(ufExportError(response)); });\n"
            . "                }}\n"
            . "            }),\n"
            . "            new BX.PopupWindowButtonLink({\n"
            . "                text: lang.cancel,\n"
            . "                className: 'popup-window-button-link-cancel',\n"
            . "                events: {click: function () { popup.close(); }}\n"
            . "            })\n"
            . "        ],\n"
            . "        events: {onPopupClose: function () { this.destroy(); }}\n"
            . "    });\n"
            . "    popup.show();\n"
            . "}).catch(function (response) { alert(ufExportError(response)); });";

        return [
            'title' => Loc::getMessage('MODULE_OPTION_USER_FIELDS_EXPORT_BTN'),
            'js' => $js,
        ];
    }

    /**
     * Действия контроллера доступны только администратору.
     *
     * @return array<int, \Bitrix\Main\Engine\ActionFilter\Base>
     */
    public function getPreFilters(): array
    {
        return [
            new ClosureWrapper(function () {
                if (CurrentUser::get()->isAdmin()) {
                    return null;
                }

                $this->addError(new Error(
                    Loc::getMessage('MODULE_CONTROLLER_USER_FIELD_EXPORT_ACCESS_DENIED') ?: 'Access denied',
                    'access_denied'
                ));

                return new EventResult(EventResult::ERROR, null, null, $this);
            }),
        ];
    }

    /**
     * @param array<int, class-string> $fieldClasses
     * @return array{fields: array<int, array>, providers: array<int, array>}
     * @throws ModuleException
     */
    public function getListData(array $fieldClasses): array
    {
        return [
            'fields' => $this->getAvailableFields($fieldClasses),
            'providers' => $this->getUserFieldService()->getExportableProviders(),
        ];
    }

    /**
     * Поля, для которых в модуле ещё нет класса миграции: их и предлагаем
     * экспортировать. Классы миграций передаёт вызывающий код — они лежат в
     * lib модуля-источника, и сервис приёмника их не видит.
     *
     * @param array<int, class-string> $fieldClasses
     * @return array<int, array{
     *     entityId: string,
     *     entityName: string,
     *     fieldName: string,
     *     userTypeId: string,
     *     label: string,
     * }>
     */
    private function getAvailableFields(array $fieldClasses): array
    {
        $existing = [];
        foreach ($fieldClasses as $class) {
            $existing[$class::getEntityId() . '::' . $class::getFieldName()] = true;
        }

        $result = [];
        $entityNames = $this->getEntityNames();

        // Полный перечень полей здесь нужен по существу: чтобы предложить
        // экспорт, надо знать все поля и вычесть из них уже описанные
        // миграциями. Точечная выборка по конкретному полю не подходит.
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
     * Наименования сущностей пользовательских полей: [ENTITY_ID => caption].
     *
     * Сами записи UF хранят только идентификатор (CRM_LEAD, CRM_4,
     * IBLOCK_3_SECTION, HLBLOCK_1, …), поэтому человекочитаемое название
     * достаём у ядра. Сущности, названия которых ядро не отдаёт (пользователи),
     * остаются без названия — вызывающий код показывает идентификатор.
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
     * Идентификаторы инфоблоков берём одним запросом (DISTINCT по ENTITY_ID),
     * названия — вторым, сразу для всех найденных id. Обходить инфоблоки
     * по одному с CIBlock::GetArrayByID() в цикле не нужно: это N обращений
     * к БД вместо одного.
     *
     * @return array<string, string>
     */
    private function getIblockEntityNames(): array
    {
        $names = [];

        if (!Loader::includeModule('iblock') || !class_exists(IblockTable::class)) {
            return $names;
        }

        $iblockIds = $this->getUserFieldIblockIds();
        if (empty($iblockIds)) {
            return $names;
        }

        $captions = [];
        $rsBlocks = IblockTable::getList([
            'select' => ['ID', 'NAME'],
            'filter' => ['=ID' => $iblockIds],
        ]);
        while ($block = $rsBlocks->fetch()) {
            $caption = trim((string)$block['NAME']);
            if ($caption !== '') {
                $captions[(int)$block['ID']] = $caption;
            }
        }

        foreach ($captions as $iblockId => $caption) {
            $names['IBLOCK_' . $iblockId . '_SECTION'] = $caption;
            $names['IBLOCK_' . $iblockId . '_ELEMENT'] = $caption;
            $names['IBLOCK_' . $iblockId] = $caption;
        }

        return $names;
    }

    /**
     * Идентификаторы инфоблоков, у которых есть пользовательские поля.
     * Один запрос с DISTINCT вместо чтения полного списка полей.
     *
     * @return array<int, int>
     */
    private function getUserFieldIblockIds(): array
    {
        $iblockIds = [];

        // Ключ без модификатора — обычный LIKE: в ORM '%=FIELD' экранирует %,
        // и выборка получается пустой.
        $rsFields = UserFieldTable::getList([
            'select' => ['ENTITY_ID'],
            'filter' => ['ENTITY_ID' => 'IBLOCK_%'],
            'group' => ['ENTITY_ID'],
        ]);
        while ($field = $rsFields->fetch()) {
            $entityId = (string)$field['ENTITY_ID'];
            if (!preg_match('/^IBLOCK_(\d+)(?:_(SECTION|ELEMENT))?$/', $entityId, $matches)) {
                continue;
            }

            $iblockIds[(int)$matches[1]] = (int)$matches[1];
        }

        return $iblockIds;
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
     * Сервис полей текущей сборки. Возвращаем object без жёсткого typehint:
     * у сервиса-реализации интерфейс только документирующий (так же, как в
     * UserFieldsRegistry), а при редиректе код сервиса подставляет локатор.
     *
     * @return object
     * @throws ModuleException
     */
    private function getUserFieldService(): object
    {
        return Container::get($this->userFieldServiceCode);
    }

    /**
     * Префикс действий контроллера: <vendor>:<module>.api.UserFieldExport.
     * Строится из модуля-источника, поэтому при перенаправлении кнопка
     * в модуле-источнике обращается к своему собственному контроллеру.
     *
     * @return string
     */
    private function getActionPrefix(): string
    {
        $vendor = explode('.', $this->moduleId)[0];
        $shortModuleId = substr($this->moduleId, strlen($vendor) + 1);

        return $vendor . ':' . $shortModuleId . '.api.UserFieldExport';
    }
}
