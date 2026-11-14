<?php

namespace AppModules\NoticeBoard\Observers;

use AppModules\NoticeBoard\Models\NoticeView;

class NoticeViewObserver
{

    public function creating(NoticeView $noticeView)
    {
        if (company()) {
            $noticeView->company_id = company()->id;
        }
    }

}
