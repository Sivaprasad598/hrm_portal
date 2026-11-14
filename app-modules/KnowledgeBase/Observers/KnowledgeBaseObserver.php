<?php

namespace AppModules\KnowledgeBase\Observers;

use AppModules\KnowledgeBase\Models\KnowledgeBase;

class KnowledgeBaseObserver
{

    public function creating(KnowledgeBase $doc)
    {
        if (company()) {
            $doc->company_id = company()->id;
        }
    }

}
