<?php

namespace App\Notifications;

use App\Models\EmployeeDocumentExpiry;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class EmployeeDocumentExpiryAlert extends Notification implements ShouldQueue
{
    use Queueable;

    protected $document;
    protected $alertType;

    /**
     * Create a new notification instance.
     */
    public function __construct(EmployeeDocumentExpiry $document, string $alertType = 'expiring_soon')
    {
        $this->document = $document;
        $this->alertType = $alertType;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail', 'database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $message = new MailMessage();
        
        if ($this->alertType === 'expiring_soon') {
            $message->subject('Document Expiry Alert - ' . $this->document->document_name)
                ->greeting('Hello ' . $notifiable->name . '!')
                ->line('Your document **' . $this->document->document_name . '** is expiring soon.')
                ->line('**Document Details:**')
                ->line('- Document Name: ' . $this->document->document_name)
                ->line('- Document Number: ' . ($this->document->document_number ?: 'N/A'))
                ->line('- Expiry Date: ' . $this->document->expiry_date->format('d M Y'))
                ->line('- Days until expiry: ' . $this->document->days_until_expiry)
                ->line('Please ensure you renew this document before the expiry date.');
                // ->action('View Document', route('employee-document-expiry.download', md5($this->document->id)))
                // ->line('Thank you for using our application!');
        } else {
            $message->subject('Alert - Employee Document Expiring Soon')
                ->greeting('Hello ' . $notifiable->name . '!')
                ->line('An employee document is expiring soon and requires attention.')
                ->line('**Document Details:**')
                ->line('- Employee: ' . $this->document->user->name)
                ->line('- Document Name: ' . $this->document->document_name)
                ->line('- Document Number: ' . ($this->document->document_number ?: 'N/A'))
                ->line('- Expiry Date: ' . $this->document->expiry_date->format('d M Y'))
                ->line('- Days until expiry: ' . $this->document->days_until_expiry)
                ->line('Please follow up with the employee to ensure document renewal.');
                // ->action('View Document', route('employee-document-expiry.download', md5($this->document->id)))
                // ->line('Thank you for using our application!');
        }

        return $message;
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        if ($this->alertType === 'expiring_soon') {
            return [
                'title' => 'Document Expiry Alert',
                'message' => 'Your document "' . $this->document->document_name . '" is expiring in ' . $this->document->days_until_expiry . ' days.',
                'document_id' => $this->document->id,
                'document_name' => $this->document->document_name,
                'expiry_date' => $this->document->expiry_date->format('d M Y'),
                'days_until_expiry' => $this->document->days_until_expiry,
                'type' => 'document_expiry_alert'
            ];
        } else {
            return [
                'title' => 'HR Alert - Employee Document Expiring',
                'message' => 'Employee ' . $this->document->user->name . '\'s document "' . $this->document->document_name . '" is expiring in ' . $this->document->days_until_expiry . ' days.',
                'document_id' => $this->document->id,
                'employee_name' => $this->document->user->name,
                'document_name' => $this->document->document_name,
                'expiry_date' => $this->document->expiry_date->format('d M Y'),
                'days_until_expiry' => $this->document->days_until_expiry,
                'type' => 'hr_document_expiry_alert'
            ];
        }
    }
}
