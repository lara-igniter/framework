<?php

namespace Elegant\View\Compilers\Concerns;

trait CompilesHelpers
{
    /**
     * Compile the CSRF statements into valid PHP.
     *
     * @return string
     */
    protected function compileCsrf()
    {
        return '<?php echo csrf_field(); ?>';
    }

    /**
     * Compile the "dd" statements into valid PHP.
     *
     * @param  string  $arguments
     * @return string
     */
    protected function compileDd($arguments)
    {
        return "<?php dd{$arguments}; ?>";
    }

    /**
     * Compile the "dump" statements into valid PHP.
     *
     * @param  string  $arguments
     * @return string
     */
    protected function compileDump($arguments)
    {
        return "<?php dump{$arguments}; ?>";
    }
}