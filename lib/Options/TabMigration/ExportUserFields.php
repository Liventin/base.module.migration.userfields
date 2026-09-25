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

        $js = "var sessid = BX.bitrix_sessid();\n"
            . "fetch('" . $listUrl . "', {method: 'POST', body: 'sessid=' + encodeURIComponent(sessid), headers: {'Content-Type': 'application/x-www-form-urlencoded'}, credentials: 'same-origin'})\n"
            . ".then(function (r) { return r.json(); })\n"
            . ".then(function (d) {\n"
            . "    if (!d.data) { alert(d.errors ? d.errors[0].message : 'error'); return; }\n"
            . "    var data = d.data;\n"
            . "    var entities = {};\n"
            . "    data.fields.forEach(function (f) { if (!entities[f.entityId]) entities[f.entityId] = []; entities[f.entityId].push(f); });\n"
            . "    var box = document.createElement('div'); box.style.padding = '12px';\n"
            . "    var lblE = document.createElement('label'); lblE.textContent = '" . Loc::getMessage('MODULE_OPTION_USER_FIELDS_EXPORT_ENTITY') . ": ';\n"
            . "    var selE = document.createElement('select'); selE.id = 'uf-export-entity'; selE.style.minWidth = '220px';\n"
            . "    Object.keys(entities).forEach(function (e) { var o = document.createElement('option'); o.value = e; o.textContent = e; selE.appendChild(o); });\n"
            . "    var br1 = document.createElement('br');\n"
            . "    var lblF = document.createElement('label'); lblF.textContent = '" . Loc::getMessage('MODULE_OPTION_USER_FIELDS_EXPORT_FIELD') . ": ';\n"
            . "    var selF = document.createElement('select'); selF.id = 'uf-export-field'; selF.style.minWidth = '220px'; selF.disabled = true;\n"
            . "    var br2 = document.createElement('br');\n"
            . "    var lblP = document.createElement('label'); lblP.textContent = '" . Loc::getMessage('MODULE_OPTION_USER_FIELDS_EXPORT_PROVIDER') . ": ';\n"
            . "    var selP = document.createElement('select'); selP.id = 'uf-export-provider'; selP.style.minWidth = '220px';\n"
            . "    data.providers.forEach(function (p) { var o = document.createElement('option'); o.value = p.type; o.textContent = p.label; selP.appendChild(o); });\n"
            . "    box.appendChild(lblE); box.appendChild(selE); box.appendChild(br1);\n"
            . "    box.appendChild(lblF); box.appendChild(selF); box.appendChild(br2);\n"
            . "    box.appendChild(lblP); box.appendChild(selP);\n"
            . "    window.ufExportData = data;\n"
            . "    var popup = new BX.PopupWindow('userfield-export', box, {\n"
            . "        closeIcon: {right: '12px', top: '10px'}, closeByEsc: true,\n"
            . "        buttons: [\n"
            . "            new BX.PopupWindowButton({id: 'uf-export-ok', text: '" . Loc::getMessage('MODULE_OPTION_USER_FIELDS_EXPORT_OK') . "', className: 'adm-btn-save'}),\n"
            . "            new BX.PopupWindowButton({id: 'uf-export-cancel', text: '" . Loc::getMessage('MODULE_OPTION_USER_FIELDS_EXPORT_CANCEL') . "'})\n"
            . "        ]\n"
            . "    });\n"
            . "    popup.show();\n"
            . "    document.getElementById('uf-export-ok').addEventListener('click', function () {\n"
            . "        var entity = document.getElementById('uf-export-entity').value;\n"
            . "        var field = document.getElementById('uf-export-field').value;\n"
            . "        var provider = document.getElementById('uf-export-provider').value;\n"
            . "        if (!entity || !field) return;\n"
            . "        fetch('" . $exportUrl . "', {method: 'POST', body: 'entityId=' + encodeURIComponent(entity) + '&fieldName=' + encodeURIComponent(field) + '&providerType=' + encodeURIComponent(provider) + '&sessid=' + encodeURIComponent(sessid), headers: {'Content-Type': 'application/x-www-form-urlencoded'}, credentials: 'same-origin'})\n"
            . "        .then(function (r) { return r.json(); })\n"
            . "        .then(function (resp) {\n"
            . "            if (resp.data && resp.data.success) { location.reload(); }\n"
            . "            else { alert(resp.data ? resp.data.error : 'error'); }\n"
            . "        });\n"
            . "    });\n"
            . "    document.getElementById('uf-export-cancel').addEventListener('click', function () { BX.PopupWindowManager.close('userfield-export'); });\n"
            . "    document.getElementById('uf-export-entity').addEventListener('change', function () {\n"
            . "        var e = this.value, sel = document.getElementById('uf-export-field');\n"
            . "        while (sel.firstChild) sel.removeChild(sel.firstChild);\n"
            . "        window.ufExportData.fields.forEach(function (f) { if (f.entityId === e) { var o = document.createElement('option'); o.value = f.fieldName; o.textContent = f.fieldName + (f.label ? ' (' + f.label + ')' : ''); sel.appendChild(o); } });\n"
            . "        sel.disabled = false;\n"
            . "    });\n"
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