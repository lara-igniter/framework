<?php

namespace Elegant\Support\Facades;

/**
 * @method static \Elegant\Mail\Mailer mailer(string|null $name = null)
 * @method static \Elegant\Mail\PendingMail bcc($users)
 * @method static \Elegant\Mail\PendingMail to($users)
 * @method static \Elegant\Support\Collection sent(string $mailable, \Closure|string $callback = null)
 * @method static array failures()
 * @method static void raw(string $text, $callback)
 * @method static void plain(string $view, array $data, $callback)
 * @method static void html(string $html, $callback)
 * @method static void send(\Elegant\Contracts\Mail\Mailable|string|array $view, array $data = [], \Closure|string $callback = null)
 *
 * @see \Elegant\Mail\Mailer
 * @see \Elegant\Mail\MailManager
 */
class Mail extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * Must return the container binding key so the facade reuses the
     * MailManager registered by MailServiceProvider (with its cached mailers
     * and View factory). Returning a new MailManager on every call forced
     * resolve() to call app('view') again and failed in long-running queue workers
     * when the CI singleton's view property was no longer available.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'mail.manager';
    }
}
