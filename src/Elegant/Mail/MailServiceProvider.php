<?php

namespace Elegant\Mail;

use Elegant\Contracts\Hook\PostControllerConstructor;

class MailServiceProvider implements PostControllerConstructor
{
    public function postControllerConstructor(&$params)
    {
        app('load')->config('mail', true);

        $this->registerElegantMailer();
    }

    /**
     * Register the Illuminate mailer instance.
     *
     * @return void
     */
    protected function registerElegantMailer()
    {
        app('mail.manager', new MailManager(app()));

        app('mailer', app('mail.manager')->mailer());
    }
}
