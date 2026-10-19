<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Avertissement au parent : une tranche de l'écolage de son enfant n'est pas réglée. */
class RetardEcolage extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public readonly string $eleve,
        public readonly string $tranche,
        public readonly string $dateLimite,
        public readonly string $montantRetard,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject("Écolage en retard — {$this->eleve}")
            ->greeting('Bonjour,')
            ->line("La tranche « {$this->tranche} » de l'écolage de {$this->eleve} devait être réglée avant le {$this->dateLimite}.")
            ->line("Montant en retard à ce jour : {$this->montantRetard}.")
            ->line('Merci de régulariser la situation auprès du secrétariat. Si le paiement a déjà été effectué, ne tenez pas compte de ce message.')
            ->action('Voir la situation', rtrim((string) config('app.frontend_url'), '/').'/paiements')
            ->salutation('Le secrétariat');
    }

    /** @return array<string, string> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'retard_ecolage',
            'titre' => "Écolage en retard — {$this->eleve}",
            'message' => "La tranche « {$this->tranche} » (avant le {$this->dateLimite}) n'est pas réglée : {$this->montantRetard} en retard.",
            'lien' => '/paiements',
        ];
    }
}
