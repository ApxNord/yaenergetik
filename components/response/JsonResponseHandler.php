<?php

/**
 * Класс для стандартизированной отправки JSON-ответов.
 * 
 * @package components.web
 */
class JsonResponseHandler
{
    /** @var int Статус неудачного выполнения */
    const FAIL = 0;

    /** @var int Статус успешного выполнения */
    const SUCCESS = 1;

    /**
     * Отправляет данные в формате JSON.
     * 
     * @param int $success Статус операции (self::SUCCESS/self::FAIL)
     * @param array $data Дополнительные данные ответа
     * 
     * @example
     * // Успешный ответ с данными
     * $handler->send(self::SUCCESS, ['items' => $list]);
     * 
     * // Ошибка с описанием
     * $handler->send(self::FAIL, ['error' => 'Invalid request']);
     */
    public function send($success, $data = [])
    {
        Yii::app()->json->send(array_merge(
            ['success' => $success],
            $data
        ));
    }

    /**
     * Отправляет HTML-контент в JSON-обертке.
     * 
     * @param string $view Название view-файла
     * @param array $data Данные для рендеринга
     * @param bool $processOutput Флаг обработки вывода
     * 
     * @example
     * // Рендер модального окна
     * $handler->sendView('_modal', ['entity' => $model]);
     */
    public function sendView($view, $data = [], $processOutput = false)
    {
        $html = Yii::app()->controller->renderPartial($view, $data, true, $processOutput);
        $this->send(CHtml::value($data, 'success', self::SUCCESS), ['html' => $html]);
    }
}
