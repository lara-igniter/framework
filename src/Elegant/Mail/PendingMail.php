<?php

namespace Elegant\Mail;

use Elegant\Contracts\Mail\Mailable as MailableContract;
use Elegant\Contracts\Mail\Mailer as MailerContract;

class PendingMail
{
    /**
     * The mailer instance.
     *
     * @var \Elegant\Contracts\Mail\Mailer
     */
    protected $mailer;

    /**
     * The locale of the message.
     *
     * @var string
     */
    protected $locale;

    /**
     * The "to" recipients of the message.
     *
     * @var array
     */
    protected $to = [];

    /**
     * The "cc" recipients of the message.
     *
     * @var array
     */
    protected $cc = [];

    /**
     * The "bcc" recipients of the message.
     *
     * @var array
     */
    protected $bcc = [];

    /**
     * Create a new mailable mailer instance.
     *
     * @param \Elegant\Contracts\Mail\Mailer $mailer
     * @return void
     */
    public function __construct(MailerContract $mailer)
    {
        $this->mailer = $mailer;
    }

    /**
     * Set the locale of the message.
     *
     * @param string $locale
     * @return $this
     */
    public function locale($locale)
    {
        $this->locale = $locale;

        return $this;
    }

    /**
     * Set the recipients of the message.
     *
     * @param mixed $users
     * @return $this
     */
    public function to($users)
    {
        $this->to = $users;

        if (!$this->locale && is_object($users)) {
            if(isset($users->locale)) {
                $this->locale($users->locale);
            }
        }

        return $this;
    }

    /**
     * Set the recipients of the message.
     *
     * @param mixed $users
     * @return $this
     */
    public function cc($users)
    {
        $this->cc = $users;

        return $this;
    }

    /**
     * Set the recipients of the message.
     *
     * @param mixed $users
     * @return $this
     */
    public function bcc($users)
    {
        $this->bcc = $users;

        return $this;
    }

    /**
     * Send a new mailable message instance.
     *
     * @param \Elegant\Contracts\Mail\Mailable $mailable
     * @return void
     */
    public function send(MailableContract $mailable)
    {
        $this->mailer->send($this->fill($mailable));
    }

    /**
     * Populate the mailable with the addresses.
     *
     * @param \Elegant\Contracts\Mail\Mailable $mailable
     * @return \Elegant\Mail\Mailable
     */
    protected function fill(MailableContract $mailable)
    {
        return tap($mailable->to($this->to)
            ->cc($this->cc)
            ->bcc($this->bcc), function (MailableContract $mailable) {
            if ($this->locale) {
                $mailable->locale($this->locale);
            }
        });
    }
}
