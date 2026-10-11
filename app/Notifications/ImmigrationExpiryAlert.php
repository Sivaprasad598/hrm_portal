<?php

namespace App\Notifications;

use App\Models\Passport;
use App\Models\VisaDetail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ImmigrationExpiryAlert extends Notification implements ShouldQueue
{
    use Queueable;

    protected $immigrationDocument;
    protected $alertType;
    protected $documentType;

    /**
     * Create a new notification instance.
     */
    public function __construct($immigrationDocument, string $alertType = 'expiring_soon', string $documentType = 'passport')
    {
        $this->immigrationDocument = $immigrationDocument;
        $this->alertType = $alertType;
        $this->documentType = $documentType;
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
        
        $documentName = $this->documentType === 'passport' ? 'Passport' : 'Visa';
        $documentNumber = $this->documentType === 'passport' ? $this->immigrationDocument->passport_number : $this->immigrationDocument->visa_number;
        $daysUntilExpiry = now()->diffInDays($this->immigrationDocument->expiry_date, false);
        
        if ($this->alertType === 'expiring_soon') {
            $message->subject($documentName . ' Expiry Alert - ' . $documentNumber)
                ->greeting('Hello ' . $notifiable->name . '!')
                ->line('Your ' . strtolower($documentName) . ' **' . $documentNumber . '** is expiring soon.')
                ->line('**' . $documentName . ' Details:**')
                ->line('- ' . $documentName . ' Number: ' . $documentNumber)
                ->line('- Issue Date: ' . $this->immigrationDocument->issue_date->format('d M Y'))
                ->line('- Expiry Date: ' . $this->immigrationDocument->expiry_date->format('d M Y'))
                ->line('- Days until expiry: ' . $daysUntilExpiry)
                ->line('Please ensure you renew this ' . strtolower($documentName) . ' before the expiry date.');
        } else {
            $message->subject('Alert - Employee ' . $documentName . ' Expiring Soon')
                ->greeting('Hello ' . $notifiable->name . '!')
                ->line('An employee ' . strtolower($documentName) . ' is expiring soon and requires attention.')
                ->line('**' . $documentName . ' Details:**')
                ->line('- Employee: ' . $this->immigrationDocument->user->name)
                ->line('- ' . $documentName . ' Number: ' . $documentNumber)
                ->line('- Issue Date: ' . $this->immigrationDocument->issue_date->format('d M Y'))
                ->line('- Expiry Date: ' . $this->immigrationDocument->expiry_date->format('d M Y'))
                ->line('- Days until expiry: ' . $daysUntilExpiry)
                ->line('Please follow up with the employee to ensure ' . strtolower($documentName) . ' renewal.');
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
        $documentName = $this->documentType === 'passport' ? 'Passport' : 'Visa';
        $documentNumber = $this->documentType === 'passport' ? $this->immigrationDocument->passport_number : $this->immigrationDocument->visa_number;
        $daysUntilExpiry = now()->diffInDays($this->immigrationDocument->expiry_date, false);
        
        if ($this->alertType === 'expiring_soon') {
            return [
                'title' => $documentName . ' Expiry Alert',
                'message' => 'Your ' . strtolower($documentName) . ' "' . $documentNumber . '" is expiring in ' . $daysUntilExpiry . ' days.',
                'document_id' => $this->immigrationDocument->id,
                'document_type' => $this->documentType,
                'document_number' => $documentNumber,
                'expiry_date' => $this->immigrationDocument->expiry_date->format('d M Y'),
                'days_until_expiry' => $daysUntilExpiry,
                'type' => 'immigration_expiry_alert'
            ];
        } else {
            return [
                'title' => 'HR Alert - Employee ' . $documentName . ' Expiring',
                'message' => 'Employee ' . $this->immigrationDocument->user->name . '\'s ' . strtolower($documentName) . ' "' . $documentNumber . '" is expiring in ' . $daysUntilExpiry . ' days.',
                'document_id' => $this->immigrationDocument->id,
                'document_type' => $this->documentType,
                'employee_name' => $this->immigrationDocument->user->name,
                'document_number' => $documentNumber,
                'expiry_date' => $this->immigrationDocument->expiry_date->format('d M Y'),
                'days_until_expiry' => $daysUntilExpiry,
                'type' => 'hr_immigration_expiry_alert'
            ];
        }
    }
}