<?php

namespace Elegant\Contracts\Debug;

use Throwable;

interface ExceptionHandler
{
    /**
     * Report or log an exception.
     *
     * @param  \Throwable  $e
     * @return void
     *
     * @throws \Throwable
     */
    public function report(Throwable $e);

    /**
     * Determine if the exception should be reported.
     *
     * @param  \Throwable  $e
     * @return bool
     */
    public function shouldReport(Throwable $e);

    /**
     * Render an exception into an HTTP response.
     *
     * @param  mixed  $request
     * @param  \Throwable  $e
     * @return \Symfony\Component\HttpFoundation\Response
     *
     * @throws \Throwable
     */
    public function render($request, Throwable $e);

    /**
     * Render an exception to the console.
     *
     * @param  mixed  $output
     * @param  \Throwable  $e
     * @return void
     */
    public function renderForConsole($output, Throwable $e);
}
