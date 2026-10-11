<?php

namespace Base\Module\Service\Migration\UserField;

/**
 * Контракт сервиса логики кнопки «Экспорт полей».
 *
 * Сервис отвечает за всё, что связано с кнопкой: рисует её (JS), отдаёт данные
 * для диалога экспорта, закрывает доступ к действиям контроллера и знает
 * человекочитаемые имена сущностей пользовательских полей. Класс опции
 * (lib/Options) и контроллер (lib/Controller) при этом остаются тонкими: они
 * только вызывают методы сервиса.
 *
 * Реализация живёт в lib/Src и при настроенном перенаправлении
 * (service-redirect) не копируется в модуль-источник: сервис-локатор модуля
 * подставляет реализацию из модуля-приёмника. Поэтому одна и та же «портняка»
 * JS не дублируется в каждом модуле на коробке.
 */
interface OptionExportButtonService
{
    public const SERVICE_CODE = 'base.module.migration.userfields.option.export.button.service';

    /**
     * Параметры кнопки для провайдера опций: заголовок и JS, который её рисует.
     *
     * @return array{title: string, js: string}
     */
    public function getButton(): array;

    /**
     * Фильтры доступа для контроллера экспорта (только администратор).
     *
     * @return array<int, \Bitrix\Main\Engine\ActionFilter\Base>
     */
    public function getPreFilters(): array;

    /**
     * Данные для диалога экспорта: поля без миграций и список провайдеров.
     *
     * @param array<int, class-string> $fieldClasses классы миграций модуля-источника
     * @return array{fields: array<int, array>, providers: array<int, array>}
     */
    public function getListData(array $fieldClasses): array;

    /**
     * Человекочитаемое имя сущности пользовательского поля по её коду.
     * Пустая строка, если ядро названия не отдаёт (например, пользователи).
     *
     * @param string $entityId
     * @return string
     */
    public function getEntityName(string $entityId): string;
}
