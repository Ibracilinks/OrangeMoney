<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit71738a433bb03e2994c0f38bd99f7a5b
{
    public static $prefixLengthsPsr4 = array (
        'I' => 
        array (
            'Ibracilinks\\OrangeMoney\\' => 24,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'Ibracilinks\\OrangeMoney\\' => 
        array (
            0 => __DIR__ . '/../..' . '/src',
        ),
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit71738a433bb03e2994c0f38bd99f7a5b::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit71738a433bb03e2994c0f38bd99f7a5b::$prefixDirsPsr4;

        }, null, ClassLoader::class);
    }
}
