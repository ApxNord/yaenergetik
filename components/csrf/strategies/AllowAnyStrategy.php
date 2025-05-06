<?php

class AllowAnyStrategy implements CsrfValidationStrategyInterface
{
    public function validate(CHttpRequest $request): void
    {

    }
}
