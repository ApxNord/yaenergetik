<?php

class ResponseHandler
{
    const SUCCESS = 1;
    const FAIL = 0;
    //------------------------------------------------------------------------
    //  Отправка данных в виде JSON-объектов
    //------------------------------------------------------------------------

    /**
     * Отправить данные в виде JSON.
     *
     * @param mixed $success
     * @param array $data
     *
     * @fixme $data не может иметь ключ 'success'
     */
    public function json($success, $data = [])
    {
        $data['success'] = $success;

        Yii::app()->json->send(array_merge(
            ['success' => $success],
            $data
        ));
    }

    /**
     * Отправить страницу в виде JSON.
     *
     * @param string $view
     * @param array  $data
     * @param bool   $processOutput
     */
    public function jsonView($data = [])
    {
        $this->json($data);
    }
}
