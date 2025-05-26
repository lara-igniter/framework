<?php

namespace Elegant\Support\Facades;

/**
 * @method static array getCustomDirectives()
 * @method static array getExtensions()
 * @method static bool check(string $name, array ...$parameters)
 * @method static string compileString(string $value)
 * @method static string getPath()
 * @method static string stripParentheses(string $expression)
 * @method static void compile(string|null $path = null)
 * @method static void directive(string $name, callable $handler)
 * @method static void extend(callable $compiler)
 * @method static void setEchoFormat(string $format)
 * @method static void setPath(string $path)
 * @method static void withDoubleEncoding()
 * @method static void withoutDoubleEncoding()
 *
 * @see \Elegant\View\Compilers\BladeCompiler
 */
class Blade extends Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'blade.compiler';
    }
}
