<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Récapitulatif pour la direction et le secrétariat : familles passées en retard ce jour. */
class RecapRetards extends Notification implements ShouldQueue
{
    use Queueable;

    /** @param  list<string>  $eleves  noms des élèves concernés */
    public function __construct(
        public readonly array $eleves,
        public readonly string $montantTotal,
    ) {}

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['database', 'mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mail = (new MailMessage)
            ->subject($this->titre())
            ->greeting('Bonjour,')
            ->line($this->message())
            ->line('Les parents concernés ont été avertis.');
        foreach (array_slice($this->eleves, 0, 20) as $eleve) {
            $mail->line("• {$eleve}");
        }

        return $mail->action('Voir les impayés', rtrim((string) config('app.frontend_url'), '/').'/paiements');
    }

    /** @return array<string, string> */
    public function toArray(object $notifiable): array
    {
        return [
            'type' => 'recap_retards',
            'titre' => $this->titre(),
            'message' => $this->message().' '.$this->apercu(),
            'lien' => '/paiements',
        ];
    }

    private function titre(): string
    {
        $n = count($this->eleves);

        return $n > 1 ? "{$n} nouvelles familles en retard d'écolage" : "1 nouvelle famille en retard d'écolage";
    }

    private function message(): string
    {
        return "Montant en retard pour ces familles : {$this->montantTotal}.";
    }

    /** « Albert Paul, Durand Léa et 3 autres » */
    private function apercu(): string
    {
        $premiers = array_slice($this->eleves, 0, 3);
        $reste = count($this->eleves) - count($premiers);

        return implode(', ', $premiers).($reste > 0 ? " et {$reste} autre(s)" : '').'.';
    }
}
