<?php
// src/Service/EmailService.php
namespace App\Service;

use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Twig\Environment;

class EmailService
{
    public function __construct(
        private MailerInterface $mailer,
        private Environment $twig
    ) {}

    public function sendEmail(string $to, string $subject, array $context = []): void
    {
        try {
            $body = $this->twig->render('emails/default.html.twig', $context);
            $email = (new Email())
                ->from('no-reply@meuprojeto.com')
                ->to($to)
                ->cc('lucianoarm@gmail.com')
                ->subject($subject)
                ->html($body);
            
            $this->mailer->send($email);
        }
        catch (\Exception $e) {
            // Gravaria um LOG ou tratar o erro conforme necessário, sem comprometer o fluxo da aplicação
        }
    }
}
