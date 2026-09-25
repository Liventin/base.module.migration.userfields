<?php

namespace Base\Module\Controller\Filter;

use Bitrix\Main\Engine\ActionFilter\Base;
use Bitrix\Main\Engine\CurrentUser;
use Bitrix\Main\Error;
use Bitrix\Main\Event;
use Bitrix\Main\EventResult;

/**
 * Разрешает доступ к действию только авторизованному администратору.
 */
class AdminFilter extends Base
{
    public function onBeforeAction(Event $event): ?EventResult
    {
        if (CurrentUser::get()->isAdmin()) {
            return null;
        }

        $this->addError(new Error('Access denied', 'access_denied'));

        return new EventResult(EventResult::ERROR, null, null, $this);
    }
}