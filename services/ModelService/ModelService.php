<?php

class ModelService
{
    public function setModels(...$models)
    {
        return ModelUtils::setModels(...$models);
    }

    /**
     * Заполняет модели и проверяет их.
     *
     * @return true, если все модели успешно проверены
     */
    public function setAndValidate(...$models)
    {
        return ModelUtils::setAndValidate(...$models);
    }

    /**
     * Заполняет модели, проверяет их и сохраняет в базу данных.
     *
     * @return true, если все модели успешно проверены и сохранены
     */
    public function setAndSave(...$models)
    {
        return ModelUtils::setAndSave(...$models);
    }
}
