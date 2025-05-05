<?php

class AllowAnyStrategy implements CsrfValidationStrategy
{
    public function validate(CHttpRequest $request): void
    {

    }
}
