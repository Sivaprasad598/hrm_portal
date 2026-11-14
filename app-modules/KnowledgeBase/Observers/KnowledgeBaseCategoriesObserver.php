<?php

namespace AppModules\KnowledgeBase\Observers;

use AppModules\KnowledgeBase\Models\KnowledgeBaseCategory;

class KnowledgeBaseCategoriesObserver
{

    public function creating(KnowledgeBaseCategory $doc)
    {
        if (company()) {
            $doc->company_id = company()->id;
        }
    }

}
