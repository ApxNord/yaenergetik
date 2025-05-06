<?php

class ParentHostStrategy extends SameHostStrategy
{
    public function validate(CHttpRequest $request): void
    {
        parent::validate($request);
        if (!$request->isPostRequest) {
            return;
        }

        $this->validateParent($request);
        
    }

    private function validateParent(CHttpRequest $request) : void
    {
        $current = $this->parseHost($request->getHostInfo());
        $referer = $this->parseHost($request->getUrlReferrer());

        $currentParts = explode('.', $current);
        if (count($currentParts) < 3) return; 

        array_shift($currentParts);
        $parentHost = implode('.', $currentParts);
        
        if (strcasecmp($parentHost, $referer) !== 0) {
            throw new CsrfValidationException('Parent host mismatch');
        }
    }
}
