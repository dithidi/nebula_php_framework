<?php

namespace Nebula\Mail;

class Mailable {
    /**
     * The person the message is from.
     *
     * @var array
     */
    public $from = [];

    /**
     * The "cc" recipients of the message.
     *
     * @var array
     */
    public $cc = [];

    /**
     * The "bcc" recipients of the message.
     *
     * @var array
     */
    public $bcc = [];

    /**
     * The "reply to" recipients of the message.
     *
     * @var array
     */
    public $replyTo = [];

    /**
     * The subject of the message.
     *
     * @var string
     */
    public $subject;

    /**
     * The view to use for the message.
     *
     * @var string
     */
    public $view;

    /**
     * The plain text view to use for the message.
     *
     * @var string
     */
    public $textView;

    /**
     * The view data for the message.
     *
     * @var array
     */
    public $viewData = [];

    /**
     * The attachments for the message.
     *
     * @var array
     */
    public $attachments = [];

    /**
     * The text headers for the message.
     *
     * @var array
     */
    public $textHeaders = [];

    /**
     * Builds the mailable class data.
     *
     * @return void
     *
     * @throws \Nebula\Exceptions\MailException
     */
    public function build()
    {
        throw new MailException('Mailable does not implement build method.', 500);
    }

    /**
     * Sets the 'from' data for the mailable.
     *
     * @param string $email The 'from' email.
     * @param string $name The 'from' name.
     * @return \Nebula\Mail\Mailable
     */
    public function from($email, $name)
    {
        $this->from = [
            'email' => $email,
            'name' => $name
        ];

        return $this;
    }

    /**
     * Sets the 'replyTo' data for the mailable.
     *
     * @param string $email The 'replyTo' email.
     * @param string $name The 'replyTo' name.
     * @return \Nebula\Mail\Mailable
     */
    public function replyTo($email, $name)
    {
        $this->replyTo = [
            'email' => $email,
            'name' => $name
        ];

        return $this;
    }

    /**
     * Sets the 'textHeaders' data for the mailable.
     *
     * @param array $headers The array of text headers.
     * @return \Nebula\Mail\Mailable
     */
    public function textHeaders($headers)
    {
        $this->textHeaders = $headers;

        return $this;
    }

    /**
     * Sets the 'subject' data for the mailable.
     *
     * @param string $subject The subject of the email.
     * @return \Nebula\Mail\Mailable
     */
    public function subject($subject)
    {
        $this->subject = $subject;

        return $this;
    }

    /**
     * Sets the 'textView' data for the mailable.
     *
     * @param string $textView The text view template name.
     * @return \Nebula\Mail\Mailable
     */
    public function text($textView)
    {
        $this->textView = $textView;

        return $this;
    }

    /**
     * Sets the 'view' data for the mailable.
     *
     * @param string $view The view template name.
     * @return \Nebula\Mail\Mailable
     */
    public function view($view)
    {
        $this->view = $view;

        return $this;
    }

    /**
     * Sets the 'viewData' data for the mailable.
     *
     * @param array $data The data for the view.
     * @return \Nebula\Mail\Mailable
     */
    public function with($data)
    {
        $this->viewData = $data;

        return $this;
    }

    /**
     * Validates the mailable for required information.
     *
     * @return bool|string
     */
    public function validateMailable()
    {
        if (empty($this->from)) {
            return "The from attribute is required for a mailable";
        }

        if (empty($this->view) && empty($this->textView)) {
            return "At least one view (view or text) needs to be assigned to a mailable";
        }

        return false;
    }
}
