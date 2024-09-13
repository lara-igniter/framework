<?php

namespace Elegant\Mail;

use Elegant\Contracts\Mail\Mailable as MailableContract;
use Elegant\Contracts\Mail\Mailer as MailerContract;
use Elegant\Contracts\Support\Htmlable;
use Elegant\Contracts\View\Factory;
use Elegant\Support\HtmlString;
use Elegant\Support\Traits\Macroable;
use InvalidArgumentException;
use Swift_Mailer;

class Mailer implements MailerContract
{
    use Macroable;

    /**
     * The name that is configured for the mailer.
     *
     * @var string
     */
    protected $name;

    /**
     * The view factory instance.
     *
     * @var \Elegant\Contracts\View\Factory
     */
    protected $views;

    /**
     * The Swift Mailer instance.
     *
     * @var \Swift_Mailer
     */
    protected $swift;


    /**
     * The global from address and name.
     *
     * @var array
     */
    protected $from;

    /**
     * The global reply-to address and name.
     *
     * @var array
     */
    protected $replyTo;

    /**
     * The global return path address.
     *
     * @var array
     */
    protected $returnPath;

    /**
     * The global to address and name.
     *
     * @var array
     */
    protected $to;

    /**
     * Array of failed recipients.
     *
     * @var array
     */
    protected $failedRecipients = [];

    /**
     * Create a new Mailer instance.
     *
     * @param string $name
     * @param \Elegant\Contracts\View\Factory $views
     * @param \Swift_Mailer $swift
     * @return void
     */
    public function __construct(string $name, Factory $views, Swift_Mailer $swift)
    {
        $this->name = $name;
        $this->views = $views;
        $this->swift = $swift;
    }

    /**
     * Set the global from address and name.
     *
     * @param string $address
     * @param string|null $name
     * @return void
     */
    public function alwaysFrom($address, $name = null)
    {
        $this->from = compact('address', 'name');
    }

    /**
     * Set the global reply-to address and name.
     *
     * @param string $address
     * @param string|null $name
     * @return void
     */
    public function alwaysReplyTo($address, $name = null)
    {
        $this->replyTo = compact('address', 'name');
    }

    /**
     * Set the global return path address.
     *
     * @param string $address
     * @return void
     */
    public function alwaysReturnPath($address)
    {
        $this->returnPath = compact('address');
    }

    /**
     * Set the global to address and name.
     *
     * @param string $address
     * @param string|null $name
     * @return void
     */
    public function alwaysTo($address, $name = null)
    {
        $this->to = compact('address', 'name');
    }

    /**
     * Begin the process of mailing a mailable class instance.
     *
     * @param mixed $users
     * @return \Elegant\Mail\PendingMail
     */
    public function to($users)
    {
        return (new PendingMail($this))->to($users);
    }

    /**
     * Begin the process of mailing a mailable class instance.
     *
     * @param mixed $users
     * @return \Elegant\Mail\PendingMail
     */
    public function cc($users)
    {
        return (new PendingMail($this))->cc($users);
    }

    /**
     * Begin the process of mailing a mailable class instance.
     *
     * @param mixed $users
     * @return \Elegant\Mail\PendingMail
     */
    public function bcc($users)
    {
        return (new PendingMail($this))->bcc($users);
    }

    /**
     * Send a new message with only an HTML part.
     *
     * @param string $html
     * @param mixed $callback
     * @return void
     */
    public function html($html, $callback)
    {
        $this->send(['html' => new HtmlString($html)], [], $callback);
    }

    /**
     * Send a new message with only a raw text part.
     *
     * @param string $text
     * @param mixed $callback
     * @return void
     */
    public function raw($text, $callback)
    {
        $this->send(['raw' => $text], [], $callback);
    }

    /**
     * Send a new message with only a plain part.
     *
     * @param string $view
     * @param array $data
     * @param mixed $callback
     * @return void
     */
    public function plain($view, array $data, $callback)
    {
        $this->send(['text' => $view], $data, $callback);
    }

    /**
     * Render the given message as a view.
     *
     * @param string|array $view
     * @param array $data
     * @return string
     */
    public function render($view, array $data = [])
    {
        // First we need to parse the view, which could either be a string or an array
        // containing both an HTML and plain text versions of the view which should
        // be used when sending an e-mail. We will extract both of them out here.
        [$view, $plain, $raw] = $this->parseView($view);

        $data['message'] = $this->createMessage();

        return $this->renderView($view ?: $plain, $data);
    }

    /**
     * Send a new message using a view.
     *
     * @param \Elegant\Contracts\Mail\Mailable|string|array $view
     * @param array $data
     * @param \Closure|string|null $callback
     * @return void
     */
    public function send($view, array $data = [], $callback = null)
    {
        if ($view instanceof MailableContract) {
            return $this->sendMailable($view);
        }

        // First we need to parse the view, which could either be a string or an array
        // containing both an HTML and plain text versions of the view which should
        // be used when sending an e-mail. We will extract both of them out here.
        [$view, $plain, $raw] = $this->parseView($view);

        $data['message'] = $message = $this->createMessage();

        // Once we have retrieved the view content for the e-mail we will set the body
        // of this message using the HTML type, which will provide a simple wrapper
        // to creating view based emails that are able to receive arrays of data.
        $callback($message);

        $this->addContent($message, $view, $plain, $raw, $data);

        // If a global "to" address has been set, we will set that address on the mail
        // message. This is primarily useful during local development in which each
        // message should be delivered into a single mail address for inspection.
        if (isset($this->to['address'])) {
            $this->setGlobalToAndRemoveCcAndBcc($message);
        }

        // Next we will sent message.
        $swiftMessage = $message->getSwiftMessage();

        $this->sendSwiftMessage($swiftMessage);
    }

    /**
     * Send the given mailable.
     *
     * @param \Elegant\Contracts\Mail\Mailable $mailable
     * @return mixed
     */
    protected function sendMailable(MailableContract $mailable)
    {
        return $mailable->mailer($this->name)->send($this);
    }

    /**
     * Parse the given view name or array.
     *
     * @param string|array $view
     * @return array
     *
     * @throws \InvalidArgumentException
     */
    protected function parseView($view)
    {
        if (is_string($view)) {
            return [$view, null, null];
        }

        // If the given view is an array with numeric keys, we will just assume that
        // both a "pretty" and "plain" view were provided, so we will return this
        // array as is, since it should contain both views with numerical keys.
        if (is_array($view) && isset($view[0])) {
            return [$view[0], $view[1], null];
        }

        // If this view is an array but doesn't contain numeric keys, we will assume
        // the views are being explicitly specified and will extract them via the
        // named keys instead, allowing the developers to use one or the other.
        if (is_array($view)) {
            return [
                $view['html'] ?? null,
                $view['text'] ?? null,
                $view['raw'] ?? null,
            ];
        }

        throw new InvalidArgumentException('Invalid view.');
    }

    /**
     * Add the content to a given message.
     *
     * @param \Elegant\Mail\Message $message
     * @param string $view
     * @param string $plain
     * @param string $raw
     * @param array $data
     * @return void
     */
    protected function addContent($message, $view, $plain, $raw, $data)
    {
        if (isset($view)) {
            $message->setBody($this->renderView($view, $data) ?: ' ', 'text/html');
        }

        if (isset($plain)) {
            $method = isset($view) ? 'addPart' : 'setBody';

            $message->$method($this->renderView($plain, $data) ?: ' ', 'text/plain');
        }

        if (isset($raw)) {
            $method = (isset($view) || isset($plain)) ? 'addPart' : 'setBody';

            $message->$method($raw, 'text/plain');
        }
    }

    /**
     * Render the given view.
     *
     * @param string $view
     * @param array $data
     * @return string
     */
    protected function renderView($view, $data)
    {
        return $view instanceof Htmlable
            ? $view->toHtml()
            : $this->views->make($view, $data)->render();
    }

    /**
     * Set the global "to" address on the given message.
     *
     * @param \Elegant\Mail\Message $message
     * @return void
     */
    protected function setGlobalToAndRemoveCcAndBcc($message)
    {
        $message->to($this->to['address'], $this->to['name'], true);
        $message->cc(null, null, true);
        $message->bcc(null, null, true);
    }

    /**
     * Create a new message instance.
     *
     * @return \Elegant\Mail\Message
     */
    protected function createMessage()
    {
        $message = new Message($this->swift->createMessage());

        // If a global from address has been specified we will set it on every message
        // instance so the developer does not have to repeat themselves every time
        // they create a new message. We'll just go ahead and push this address.
        if (!empty($this->from['address'])) {
            $message->from($this->from['address'], $this->from['name']);
        }

        // When a global reply address was specified we will set this on every message
        // instance so the developer does not have to repeat themselves every time
        // they create a new message. We will just go ahead and push this address.
        if (!empty($this->replyTo['address'])) {
            $message->replyTo($this->replyTo['address'], $this->replyTo['name']);
        }

        if (!empty($this->returnPath['address'])) {
            $message->returnPath($this->returnPath['address']);
        }

        return $message;
    }

    /**
     * Send a Swift Message instance.
     *
     * @param \Swift_Message $message
     * @return int|null
     */
    protected function sendSwiftMessage($message)
    {
        $this->failedRecipients = [];

        try {
            return $this->swift->send($message, $this->failedRecipients);
        } finally {
            $this->forceReconnection();
        }
    }

    /**
     * Force the transport to re-connect.
     *
     * This will prevent errors in daemon queue situations.
     *
     * @return void
     */
    protected function forceReconnection()
    {
        $this->getSwiftMailer()->getTransport()->stop();
    }

    /**
     * Get the array of failed recipients.
     *
     * @return array
     */
    public function failures()
    {
        return $this->failedRecipients;
    }

    /**
     * Get the Swift Mailer instance.
     *
     * @return \Swift_Mailer
     */
    public function getSwiftMailer()
    {
        return $this->swift;
    }

    /**
     * Get the view factory instance.
     *
     * @return \Elegant\Contracts\View\Factory
     */
    public function getViewFactory()
    {
        return $this->views;
    }

    /**
     * Set the Swift Mailer instance.
     *
     * @param \Swift_Mailer $swift
     * @return void
     */
    public function setSwiftMailer($swift)
    {
        $this->swift = $swift;
    }
}
