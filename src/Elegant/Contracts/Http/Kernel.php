<?php

namespace Elegant\Contracts\Http;

interface Kernel
{
    /**
     * Bootstrap the application for HTTP requests.
     *
     * @return void
     */
    public function bootstrap();

    /**
     * Handle an incoming HTTP request.
     *
     * @param  \Elegant\Foundation\Http\Request  $request
     * @return \Elegant\Foundation\Http\Response
     */
    public function handle($request);

    /**
     * Perform any final actions for the request lifecycle.
     *
     * @param  \Elegant\Foundation\Http\Request  $request
     * @param  \Elegant\Foundation\Http\Response  $response
     * @return void
     */
    public function terminate($request, $response);

    /**
     * Get the application instance.
     *
     * @return mixed
     */
    public function getApplication();
}
