<?php

namespace Elegant\Mail;

use Elegant\Contracts\Hook\PostControllerConstructor;

class MailServiceProvider implements PostControllerConstructor
{
    public function postControllerConstructor(&$params)
    {
        app('load')->config('mail', true);

        app('load')->config('services', true);

        $this->registerElegantMailer();
        $this->registerMarkdownRenderer();
    }

    /**
     * Register the Illuminate mailer instance.
     *
     * @return void
     */
    protected function registerElegantMailer()
    {
        app('mail.manager', new MailManager(app('config')));

        app('mailer', app('mail.manager')->mailer());
    }

    /**
     * Register the Markdown renderer instance.
     *
     * @return void
     */
    protected function registerMarkdownRenderer()
    {
        app('markdown', new Markdown(app('view'), [
            'theme' => app('config')->config['mail']['markdown']['theme'] ?? 'default',
            'paths' => app('config')->config['mail']['markdown']['paths'] ?? [],
        ]));
    }
}
