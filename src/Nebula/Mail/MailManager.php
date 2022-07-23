<?php

namespace Nebula\Mail;

use Nebula\Exceptions\MailException;
use Nebula\Mail\Mailers\{ MailerSend, Mailgun, PhpMailer };

class MailManager {
    /**
     * The mailing configuration array.
     *
     * @var array
     */
    public $config = [];

    /**
     * The mailer instance.
     *
     * @var mixed
     */
    public $mailer = null;

    /**
     * The mailable instance.
     *
     * @var mixed
     */
    public $mailable = null;

    /**
     * The "to" recipients of the message.
     *
     * @var array
     */
    protected $to = [];

    /**
     * The mailer class mappings.
     *
     * @var array
     */
    private $mappings = [
        'phpmailer' => PhpMailer::class,
        'ses' => PhpMailer::class,
        'mailgun' => Mailgun::class,
        'mailersend' => MailerSend::class
    ];

    /**
     * Create a new class instance.
     *
     * @return void
     */
    public function __construct()
    {
        $this->config = app()->config['mail'];
        $this->mailer($this->config['default']);
    }

    /**
     * Overrides the class mailer.
     *
     * @param string $name The name of the mailer.
     * @return \Nebula\Mail\MailManager
     *
     * @throws \Nebula\Exceptions\MailException
     */
    public function mailer($name)
    {
        if (!isset($this->mappings[$this->config['default']])) {
            throw new MailException($this->config['default'] . " is not an approved mailer.", 500);
        }

        $this->mailer = new $this->mappings[$this->config['mailers'][$this->config['default']]['driver']]($this->config['mailers'][$this->config['default']]);

        // If 'to' is not empty, then add it to the mailer
        if (!empty($this->to)) {
            $this->mailer->to = $this->to;
        }

        return $this;
    }

    /**
     * Sets the 'to' data.
     *
     * @param string|array $toEmail The string or array of emails.
     * @return \Nebula\Mail\MailManager
     */
    public function to($toEmail)
    {
        $this->mailer->to = $toEmail;

        return $this;
    }

    /**
     * Initiates a sending of an email.
     *
     * @param \Nebula\Mail\Mailable $mailable The mailable class instance.
     * @return mixed
     *
     * @throws \Nebula\Exceptions\MailException
     */
    public function send($mailable)
    {
        $this->mailable = $mailable;

        // Build the mailable
        $this->mailable->build();

        // Perform validation
        $validation = $this->mailable->validateMailable();

        if (!empty($validation)) {
            throw new MailException("$validation in file " . __FILE__ . " on line " . __LINE__ . ".", 500);
        }

        // User the mailer to send the email
        $this->mailer->send($this->mailable);

        return $this;
    }
}
