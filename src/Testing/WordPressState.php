<?php

namespace MM\Meros\Foundations\Testing;

/** State for the small WordPress surface used by isolated Foundations tests. */
final class WordPressState
{
    public static string $theme;
    public static array $options = [];
    public static array $hooks = [];

    public static function reset(string $theme): void
    {
        self::$theme = $theme;
        self::$options = self::$hooks = [];
    }
}
