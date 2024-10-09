<?php

/**
 * @copyright  Copyright (c) 2024 E-vino Comércio de Vinhos S.A. (https://evino.com.br)
 * @author     Kevin Mian Kraiker <kevin.kraiker@evino.com.br>
 * @Link       https://evino.com.br
 */

declare(strict_types=1);
namespace Mismatch\SpawniaSailorBundle\Util;

use function preg_replace;

class GraphQlUtils {
    public static function minify(string $query): string
    {
        $minified = $query;
        $minified = preg_replace('/\n+/', ' ', $minified);
        $minified = preg_replace('/\s*,\s*/', ',', $minified);
        $minified = preg_replace('/(?<!query|mutation|on)(\b|]|!)\s+(?=\b|\.{3}|\$)/', '$1,', $minified);
        $minified = preg_replace('/\s*([{(])\s*/', '$1', $minified);
        $minified = preg_replace('/\s*([})])\s*/', '$1', $minified);
        $minified = preg_replace('/(?<=[\b=:{!\]]|\.{3})\s+|\s+(?=[=:}\[])/', '', $minified);
        return $minified;
    }
}
