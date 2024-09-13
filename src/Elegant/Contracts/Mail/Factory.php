<?php

namespace Elegant\Contracts\Mail;

interface Factory
{
    /**
     * Get a mailer instance by name.
     *
     * @param  string|null  $name
     * @return \Elegant\Contracts\Mail\Mailer
     */
    public function mailer($name = null);
}
