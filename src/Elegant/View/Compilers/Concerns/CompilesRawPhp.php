<?php

namespace Elegant\View\Compilers\Concerns;

trait CompilesRawPhp
{
    /**
     * Compile the raw PHP statements into valid PHP.
     *
     * @param  string  $expression
     * @return string
     */
    protected function compilePhp($expression)
    {
        if ($expression) {
            return"<?php {$expression}; ?>";
        }

        return '<?php ';
    }

    /**
     * Compile end-php statements into valid PHP.
     *
     * @return string
     */
    protected function compileEndphp()
    {
        return ' ?>';
    }

    /**
     * Compile the unset statements into valid PHP.
     *
     * @param  string  $expression
     * @return string
     */
    protected function compileUnset($expression)
    {
        return "<?php unset{$expression}; ?>";
    }
}
