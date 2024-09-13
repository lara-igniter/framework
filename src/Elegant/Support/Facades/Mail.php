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
 */
class Mail extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'mail.manager';
    }
}
