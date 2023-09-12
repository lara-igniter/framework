<?php

namespace Elegant\Database\Model;

use CI_DB_mysqli_driver;

interface Scope
{
    /**
     * Apply the scope to a given model query builder.
     *
     * @param \CI_DB_mysqli_driver $builder
     * @return void
     */
    public static function apply(CI_DB_mysqli_driver $builder);
}