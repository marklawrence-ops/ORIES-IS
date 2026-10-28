<?php

namespace App\Support;

/**
 * Case-insensitive "contains" search in which % and _ typed by the user are matched literally.
 * The escape character is declared in the SQL, so PostgreSQL and SQLite behave the same.
 */
class Like
{
    /** Use as whereRaw(Like::sql('title'), [Like::pattern($term)]). */
    public static function sql(string $column): string
    {
        return "LOWER({$column}) LIKE ? ESCAPE '!'";
    }

    public static function pattern(string $term): string
    {
        $escaped = str_replace(['!', '%', '_'], ['!!', '!%', '!_'], mb_strtolower($term));

        return '%'.$escaped.'%';
    }
}
