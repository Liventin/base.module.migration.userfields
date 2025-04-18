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
    "liventin/base.module.migration.userfields": "dev-main"
}
"repositories": [
    {
      "type": "vcs",
      "url": "git@github.com:liventin/base.module.migration.userfields"
    }
]
```
redirect (optional)
```
"extra": {
  "service-redirect": {
    "liventin/base.module.migration.userfields": "module.name",
  }
}
```
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
</table>