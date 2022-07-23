<?php

namespace Nebula\Mail\Mailers;

use eftec\bladeone\BladeOne;

class Mailer {
    /**
     * The "to" recipients of the message.
     *
     * @var array
     */
    public $to = [];

    /**
     * The mailing configuration array.
     *
     * @var array
     */
    public $config = [];

    /**
     * Holds the mailing config array.
     */

    /**
     * Create a new class instance.
     *
     * @return void
     */
    public function __construct($mailerConfig)
    {
        $this->config = $mailerConfig;
    }

    /**
     * Builds the views for the mailable.
     *
     * @param \Nebula\Mail\Mailable $mailable The mailable class instance.
     * @return array
     */
    protected function buildViews($mailable)
    {
        // Initialize the Blade compiler
        $views = assets_path('views');
        $cache = storage_path('framework/cache/views');
        $blade = new BladeOne($views, $cache, BladeOne::MODE_DEBUG);

        // Build the view and text view
        if (!empty($mailable->view)) {
            $html = $blade->run($mailable->view, $mailable->viewData ?? []);
        }

        if (!empty($mailable->textView)) {
            $text = $blade->run($mailable->textView, $mailable->viewData ?? []);
        }

        return [
            'html' => $html ?? null,
            'text' => $text ?? null
        ];
    }
}
