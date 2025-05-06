<?php

/**
 * Класс для управления перенаправлениями с учетом типа запроса.
 * 
 * @package components.web
 */
class RedirectHandler
{
    /**
     * Возвращает URL личного кабинета пользователя.
     * 
     * @return string Абсолютный URL
     * 
     * @example
     * $url = $handler->getDashboardUrl(); // "/dashboard"
     */
    public function getDashboardUrl()
    {
        return Yii::app()->user->getDashboardUrl();
    }

    /**
     * Выполняет перенаправление с учетом типа запроса.
     * 
     * @param mixed $url URL для перенаправления (строка или массив роута)
     * @param bool $terminate Завершить выполнение скрипта (по умолчанию true)
     * 
     * @throws CHttpException 400 Для AJAX-запросов
     * 
     * @example
     * // Обычный редирект
     * $handler->redirectEx(['site/index']);
     * 
     * // AJAX-редирект с кастомным статусом
     * $handler->redirectEx('/error', false);
     */
    public function redirectEx($url, $terminate = true)
    {
        $request = Yii::app()->request;

        if ($request->getIsAjaxRequest()) {
            Yii::app()->controller->redirect($url, $terminate, 400);
        } else {
            Yii::app()->controller->redirect($url, $terminate);
        }
    }
}
