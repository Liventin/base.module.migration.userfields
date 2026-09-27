<?php

namespace Base\Module\Options\TabMigration;

use Base\Module\Options\TabMigration;
use Base\Module\Service\Container;
use Base\Module\Service\LazyService;
use Base\Module\Service\Options\Option;
use Base\Module\Service\Options\OptionsService;
use Bitrix\Main\Localization\Loc;

class ExportUserFields implements Option
{
    public static function getId(): string
    {
        return 'user_fields_export';
    }

    public static function getName(): string
    {
        return Loc::getMessage('MODULE_OPTION_USER_FIELDS_EXPORT_TITLE');
    }

    public static function getType(): string
    {
        return 'buttons';
    }

    public static function getTabId(): string
    {
        return TabMigration::getId();
    }

    public static function getSort(): int
    {
        return 250;
    }

    /**
     * @return array
     */
    public static function getParams(): array
    {
        /** @var OptionsService $srvOptions */
        $srvOptions = Container::get(OptionsService::SERVICE_CODE);
        /** @var \Base\Module\Src\Options\Providers\ButtonsProvider $provider */
        $provider = $srvOptions->getProvider(self::getType());

        if (!$provider) {
            return [];
        }

        $moduleId = LazyService::MODULE_ID;
        $vendor = explode('.', $moduleId)[0];
        $shortModuleId = substr($moduleId, strlen($vendor) + 1);
        $actionPrefix = $vendor . ':' . $shortModuleId . '.api.UserFieldExport';
        $listUrl = '/bitrix/services/main/ajax.php?action=' . $actionPrefix . '.list';
        $exportUrl = '/bitrix/services/main/ajax.php?action=' . $actionPrefix . '.export';

        $messages = [
            'title' => Loc::getMessage('MODULE_OPTION_USER_FIELDS_EXPORT_TITLE'),
            'entity' => Loc::getMessage('MODULE_OPTION_USER_FIELDS_EXPORT_ENTITY'),
            'field' => Loc::getMessage('MODULE_OPTION_USER_FIELDS_EXPORT_FIELD'),
            'provider' => Loc::getMessage('MODULE_OPTION_USER_FIELDS_EXPORT_PROVIDER'),
            'ok' => Loc::getMessage('MODULE_OPTION_USER_FIELDS_EXPORT_OK'),
            'cancel' => Loc::getMessage('MODULE_OPTION_USER_FIELDS_EXPORT_CANCEL'),
        ];

        $js = "var lang = " . json_encode($messages, JSON_UNESCAPED_UNICODE) . ";\n"
            . "var sessionId = BX.bitrix_sessid();\n"
            . "function ufExportPost(url, data) {\n"
            . "    data.sessid = sessionId;\n"
            . "    return fetch(url, {\n"
            . "        method: 'POST',\n"
            . "        headers: {'Content-Type': 'application/x-www-form-urlencoded'},\n"
            . "        credentials: 'same-origin',\n"
            . "        body: Object.keys(data).map(function (k) { return encodeURIComponent(k) + '=' + encodeURIComponent(data[k]); }).join('&')\n"
            . "    }).then(function (r) { return r.json(); });\n"
            . "}\n"
            . "ufExportPost('" . $listUrl . "', {}).then(function (d) {\n"
            . "    if (!d.data) { alert(d.errors ? d.errors[0].message : 'error'); return; }\n"
            . "    var data = d.data;\n"
            . "    var box = BX.create('div', {style: {padding: '16px 20px', minWidth: '360px'}});\n"
            . "    var entities = [];\n"
            . "    var seen = {};\n"
            . "    data.fields.forEach(function (f) {\n"
            . "        if (!seen[f.entityId]) { seen[f.entityId] = true; entities.push({value: f.entityId, text: f.entityId}); }\n"
            . "    });\n"
            . "    var entitySelect = BX.create('select', {props: {id: 'uf-export-entity'}, style: {minWidth: '220px'}});\n"
            . "    entities.forEach(function (e) {\n"
            . "        entitySelect.appendChild(BX.create('option', {props: {value: e.value}, text: e.text}));\n"
            . "    });\n"
            . "    var fieldSelect = BX.create('select', {props: {id: 'uf-export-field'}, style: {minWidth: '220px'}});\n"
            . "    var providerSelect = BX.create('select', {props: {id: 'uf-export-provider'}, style: {minWidth: '220px'}});\n"
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
            . "                    ufExportPost('" . $exportUrl . "', {\n"
            . "                        entityId: entitySelect.value,\n"
            . "                        fieldName: fieldSelect.value,\n"
            . "                        providerType: providerSelect.value\n"
            . "                    }).then(function (resp) {\n"
            . "                        if (resp.data && resp.data.success) { location.reload(); }\n"
            . "                        else { alert(resp.data && resp.data.error ? resp.data.error : 'error'); }\n"
            . "                    });\n"
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
            . "});";

        return $provider
            ->setAlign('right')
            ->setButtons([
                [
                    'title' => Loc::getMessage('MODULE_OPTION_USER_FIELDS_EXPORT_BTN'),
                    'js' => $js,
                ],
            ])
            ->getParamsToArray();
    }
}
