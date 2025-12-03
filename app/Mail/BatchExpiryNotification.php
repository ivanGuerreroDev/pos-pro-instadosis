<?php

namespace App\Mail;

use App\Models\ProductBatch;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BatchExpiryNotification extends Mailable
{
    use Queueable, SerializesModels;

    public $batch;
    public $notificationType;
    public $daysUntilExpiry;
    public $businessName;

    /**
     * Create a new message instance.
     */
    public function __construct(ProductBatch $batch, string $notificationType, int $daysUntilExpiry, string $businessName = '')
    {
        $this->batch = $batch;
        $this->notificationType = $notificationType;
        $this->daysUntilExpiry = $daysUntilExpiry;
        $this->businessName = $businessName;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $fromAddress = env('MAIL_FROM_ADDRESS', 'noreply@example.com');
        $fromName = env('MAIL_FROM_NAME', 'POS System');

        $subject = $this->getSubject();

        return new Envelope(
            from: new Address($fromAddress, $fromName),
            subject: $subject,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'mail.batch-expiry-notification',
            with: [
                'batch' => $this->batch,
                'notificationType' => $this->notificationType,
                'daysUntilExpiry' => $this->daysUntilExpiry,
                'businessName' => $this->businessName,
                'urgencyLevel' => $this->getUrgencyLevel(),
                'urgencyColor' => $this->getUrgencyColor(),
                'actionMessage' => $this->getActionMessage(),
            ],
        );
    }

    /**
     * Get the attachments for the message.
     */
    public function attachments(): array
    {
        return [];
    }

    /**
     * Get email subject based on notification type.
     */
    private function getSubject(): string
    {
        if ($this->notificationType === 'expired') {
            return '⚠️ Alerta: Lote Vencido - ' . $this->batch->batch_number;
        }

        if ($this->notificationType === 'out_of_stock') {
            return '📦 Alerta: Lote Sin Stock - ' . $this->batch->batch_number;
        }

        // near_expiry
        if ($this->daysUntilExpiry <= 15) {
            return '🚨 URGENTE: Lote por Vencer en ' . $this->daysUntilExpiry . ' días';
        } elseif ($this->daysUntilExpiry <= 30) {
            return '⚠️ Importante: Lote por Vencer en ' . $this->daysUntilExpiry . ' días (1 mes)';
        } elseif ($this->daysUntilExpiry <= 60) {
            return '⚡ Aviso: Lote por Vencer en ' . $this->daysUntilExpiry . ' días (2 meses)';
        } else {
            return '📅 Información: Lote por Vencer en ' . $this->daysUntilExpiry . ' días (3 meses)';
        }
    }

    /**
     * Get urgency level text.
     */
    private function getUrgencyLevel(): string
    {
        if ($this->notificationType === 'expired') {
            return 'VENCIDO';
        }

        if ($this->notificationType === 'out_of_stock') {
            return 'SIN STOCK';
        }

        if ($this->daysUntilExpiry <= 15) {
            return 'CRÍTICO';
        } elseif ($this->daysUntilExpiry <= 30) {
            return 'URGENTE';
        } elseif ($this->daysUntilExpiry <= 60) {
            return 'ADVERTENCIA';
        } else {
            return 'INFORMACIÓN';
        }
    }

    /**
     * Get urgency color for styling.
     */
    private function getUrgencyColor(): string
    {
        if ($this->notificationType === 'expired') {
            return '#8B0000'; // Dark red
        }

        if ($this->notificationType === 'out_of_stock') {
            return '#696969'; // Gray
        }

        if ($this->daysUntilExpiry <= 15) {
            return '#DC143C'; // Crimson
        } elseif ($this->daysUntilExpiry <= 30) {
            return '#FF4500'; // Orange red
        } elseif ($this->daysUntilExpiry <= 60) {
            return '#FFA500'; // Orange
        } else {
            return '#4169E1'; // Royal blue
        }
    }

    /**
     * Get action message based on urgency.
     */
    private function getActionMessage(): string
    {
        if ($this->notificationType === 'expired') {
            return 'Este lote ha vencido y debe ser descartado inmediatamente según las normativas sanitarias.';
        }

        if ($this->notificationType === 'out_of_stock') {
            return 'Este lote se ha agotado. Considere reabastecer si el producto sigue siendo necesario.';
        }

        if ($this->daysUntilExpiry <= 15) {
            return 'ACCIÓN INMEDIATA REQUERIDA: Venda o use este lote urgentemente antes de que venza.';
        } elseif ($this->daysUntilExpiry <= 30) {
            return 'Acción recomendada: Priorice la venta de este lote en el próximo mes.';
        } elseif ($this->daysUntilExpiry <= 60) {
            return 'Planificación sugerida: Incluya estrategias de promoción para este lote.';
        } else {
            return 'Información para su planificación: Este lote vencerá en los próximos 3 meses.';
        }
    }
}
