<?php

namespace App\Http\Controllers;

use App\Helper\Reply;
use Illuminate\Http\Request;

class NotificationController extends AccountBaseController
{

    /** How many rows the bell panel shows. */
    private const PANEL_ROWS = 8;

    /** How many the All notifications page shows. */
    private const PAGE_ROWS = 50;

    /**
     * The bell panel: the latest notifications, unread ones marked. Opening
     * the panel counts as seeing them, so they are marked read and the bell's
     * count clears.
     */
    public function showNotifications()
    {
        $this->userType = in_array('client', user_roles()) ? 'client' : 'all';
        $this->notificationList = $this->user->notifications()->latest()->take(self::PANEL_ROWS)->get();

        $view = view('notifications.user_notifications', $this->data)->render();
        $this->user->unreadNotifications()->update(['read_at' => now()]);

        return Reply::dataOnly(['status' => 'success', 'html' => $view, 'unread' => 0]);
    }

    /** Every recent notification; visiting the page marks them read. */
    public function all()
    {
        $this->pageTitle = 'Notifications';
        $this->userType = in_array('client', user_roles()) ? 'client' : 'all';
        $this->notificationList = $this->user->notifications()->latest()->take(self::PAGE_ROWS)->get();

        // The rows keep their unread look for this visit; the bell is cleared.
        $this->user->unreadNotifications()->update(['read_at' => now()]);
        $this->unreadNotificationCount = 0;

        return view('notifications.all_user_notifications', $this->data);
    }

    public function markAllRead()
    {
        $this->user->unreadNotifications->markAsRead();
        return Reply::success(__('messages.notificationRead'));
    }

    public function markRead(Request $request)
    {
        $this->user->unreadNotifications->where('id', $request->id)->markAsRead();
        return Reply::dataOnly(['status' => 'success']);
    }

}
