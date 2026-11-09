<?php

namespace Elegant\Http;

use Symfony\Component\HttpFoundation\BinaryFileResponse;

class FileResponse extends BinaryFileResponse
{
    /**
     * Store the file on CI_Output. public/index.php sends the kernel response.
     *
     * @return $this
     */
    public function send()
    {
        ResponseCapture::apply($this);

        return $this;
    }
}
