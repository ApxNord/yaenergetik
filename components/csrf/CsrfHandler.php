<?php

class CsrfHandler
{
    public function validate(int $rule, CHttpRequest $request): void
    {
        $strategy = CsrfStrategyFactory::create($rule);
        
        try {
            $strategy->validate($request);
        } catch (CsrfValidationException $e) {
            Log::warning('CSRF validation failed: ' . $e->getMessage());
            throw new CHttpException(403, $e->getMessage());
        }
    }

    public function resolveRule($controllerRules, CAction $action): int
    {
        if (is_int($controllerRules)) {
            return $controllerRules;
        }
        
        return $controllerRules[$action->getId()] 
            ?? ($controllerRules['*'] ?? CsrfStrategyFactory::DEFAULT_RULE);
    }
}
