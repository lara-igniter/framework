<?php

namespace Elegant\View\Compilers\Concerns;

trait CompilesAuthorizations
{
    /**
     * Compile the can statements into valid PHP.
     *
     * @param  string  $expression
     * @return string
     */
    protected function compileCan($expression)
    {
        [$ability, $arguments] = explode(',', $expression);
        return '<?php if (ci()->gate->check' . $ability . ', ' . $arguments . '): ?>';
    }

    /**
     * Compile the cannot statements into valid PHP.
     *
     * @param  string  $expression
     * @return string
     */
    protected function compileCannot($expression)
    {
        [$ability, $arguments] = explode(',', $expression);
        return '<?php if (ci()->gate->denies' . $ability . ', ' . $arguments . '): ?>';
    }

    /**
     * Compile the canany statements into valid PHP.
     *
     * @param  string  $expression
     * @return string
     */
    protected function compileCanany($expression)
    {
        [$ability, $arguments] = explode(',', $expression);
        return '<?php if (ci()->gate->any' . $ability . ', ' . $arguments . '): ?>';
    }

    /**
     * Compile the else-can statements into valid PHP.
     *
     * @param  string  $expression
     * @return string
     */
    protected function compileElsecan($expression)
    {
        [$ability, $arguments] = explode(',', $expression);
        return '<?php elseif (ci()->gate->check' . $ability . ', ' . $arguments . '): ?>';
    }

    /**
     * Compile the else-cannot statements into valid PHP.
     *
     * @param  string  $expression
     * @return string
     */
    protected function compileElsecannot($expression)
    {
        [$ability, $arguments] = explode(',', $expression);
        return '<?php elseif (ci()->gate->denies' . $ability . ', ' . $arguments . '): ?>';
    }

    /**
     * Compile the else-canany statements into valid PHP.
     *
     * @param  string  $expression
     * @return string
     */
    protected function compileElsecanany($expression)
    {
        [$ability, $arguments] = explode(',', $expression);
        return '<?php elseif (ci()->gate->any' . $ability . ', ' . $arguments . '): ?>';
    }

    /**
     * Compile the end-can statements into valid PHP.
     *
     * @return string
     */
    protected function compileEndcan()
    {
        return '<?php endif; ?>';
    }

    /**
     * Compile the end-cannot statements into valid PHP.
     *
     * @return string
     */
    protected function compileEndcannot()
    {
        return '<?php endif; ?>';
    }

    /**
     * Compile the end-canany statements into valid PHP.
     *
     * @return string
     */
    protected function compileEndcanany()
    {
        return '<?php endif; ?>';
    }
}
