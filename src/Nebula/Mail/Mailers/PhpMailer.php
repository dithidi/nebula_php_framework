<?php

namespace Nebula\Mail\Mailers;

use Nebula\Exceptions\MailException;
use Nebula\Mail\Mailers\Mailer;
use PHPMailer\PHPMailer\PHPMailer as PHPMailerClass;

class PhpMailer extends Mailer {
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

        $mail = new PHPMailerClass(true);
        $mail->CharSet = 'UTF-8';

        // If Amazon SES, setup SMTP
        if (!empty($this->config['subDriver']) && $this->config['subDriver'] == 'ses') {
            // Specify the SMTP settings.
            $mail->isSMTP();
            $mail->Username = $this->config['smtp']['username'];
            $mail->Password = $this->config['smtp']['password'];
            $mail->Host = $this->config['smtp']['host'];
            $mail->Port = $this->config['smtp']['port'];
            $mail->SMTPAuth = true;
            $mail->SMTPSecure = 'tls';
        }

        // Add the from data
        $mail->From = $mailable->from['email'];
        $mail->FromName = $mailable->from['name'];

        // Add the to data
        $mail->addAddress($this->to);

        // Add the view data
        $mail->isHTML(!empty($views['html']));
        $mail->Subject = $mailable->subject;

        $mail->Body = $views['html'] ?? $views['text'];

        if (!empty($views['html']) && !empty($views['text'])) {
            $mail->AltBody = $views['text'];
        }

        try {
            $results = $mail->send();
        } catch (\Exception $e) {
            throw new MailException("{$mail->ErrorInfo} in file " . __FILE__ . " on line " . __LINE__ . ".", 500);
        }

        return $results;
    }
}
