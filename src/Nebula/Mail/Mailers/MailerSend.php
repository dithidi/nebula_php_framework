<?php

namespace Nebula\Mail\Mailers;

use Nebula\Exceptions\MailException;
use Nebula\Mail\Mailers\Mailer;
use MailerSend\MailerSend as MailerSendClass;
use MailerSend\Helpers\Builder\Recipient;
use MailerSend\Helpers\Builder\EmailParams;

class MailerSend extends Mailer {
    /**
     * Builds the email views and sends the email.
     *
     * @param \Nebula\Mail\Mailable $mailable The mailable class instance.
     * @return bool
     *
     * @throws \Nebula\Exceptions\MailException;
     */
    public function send($mailable)
    {
        $views = $this->buildViews($mailable);

        $mail = new MailerSendClass(['api_key' => $this->config['key']]);

        // Add recipient(s)
        $recipients = [
            new Recipient($this->to, $this->to)
        ];

        $emailParams = (new EmailParams())
            ->setFrom($mailable->from['email'])
            ->setFromName($mailable->from['name'])
            ->setRecipients($recipients)
            ->setSubject($mailable->subject ?? '');

        if (!empty($views['html'])) {
            $emailParams = $emailParams->setHtml($views['html']);
        }

        if (!empty($views['text'])) {
            $emailParams = $emailParams->setText($views['text']);
        }

        try {
            $results = $mail->email->send($emailParams);
        } catch (\Exception $e) {
            throw new MailException($e->getMessage() . " in file " . __FILE__ . " on line " . __LINE__ . ".", 500);
        }

        return $results;
    }
}
