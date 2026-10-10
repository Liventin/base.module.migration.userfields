# base.module.migration.userfields

<table>
<tr>
<td>
<a href="https://github.com/Liventin/base.module">Bitrix Base Module</a>
</td>
</tr>
</table>

install | update

```
"require": {
    "liventin/base.module.migration.userfields": "^1.0.0"
}
```
redirect (optional)
```
"extra": {
  "service-redirect": {
    "liventin/base.module.migration.userfields": "module.name",
  }
}
```
buttons row provider (optional)
```
"require": {
    "liventin/base.module.options.provider.js.buttons.row": "^1.0.0"
}
```
The export tab (`User Fields → Export`) is rendered by the buttons-row option
provider. The service does **not** require that package: with a service-redirect
the providers are registered in the receiving module only, so a hard dependency
would install the provider where it is never used. Install it in the project when
you need the export tab — without it the tab renders as an empty row.
<table>
<tr>
<th>User Field Providers</th>
</tr>
<tr>
<td>
<a href="https://github.com/Liventin/base.module.migration.userfields.provider.string">String</a>
</td>
</tr>
<tr>
<td>
<a href="https://github.com/Liventin/base.module.migration.userfields.provider.datetime">DateTime</a>
</td>
</tr>
<tr>
<td>
<a href="https://github.com/Liventin/base.module.migration.userfields.provider.enumeration">Enumeration</a>
</td>
</tr>
<tr>
<td>
<a href="https://github.com/Liventin/base.module.migration.userfields.provider.hlblock">Hl Block</a>
</td>
</tr>
<tr>
<td>
<a href="https://github.com/Liventin/base.module.migration.userfields.provider.iblock.element">Iblock Element</a>
</td>
</tr>
</table>