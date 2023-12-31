<?php

// autoload_static.php @generated by Composer

namespace Composer\Autoload;

class ComposerStaticInit1a64e4a2bee93f2d2c517a3b98c5161e
{
    public static $prefixLengthsPsr4 = array (
        'P' => 
        array (
            'Prestashop\\Futureai\\' => 20,
        ),
    );

    public static $prefixDirsPsr4 = array (
        'Prestashop\\Futureai\\' => 
        array (
            0 => __DIR__ . '/../..' . '/src',
        ),
    );

    public static $classMap = array (
        'Composer\\InstalledVersions' => __DIR__ . '/..' . '/composer/InstalledVersions.php',
        'FutureAi' => __DIR__ . '/../..' . '/futureai.php',
        'PrestaShop\\FutureAi\\FutureAiApi' => __DIR__ . '/../..' . '/classes/FutureAiApi.php',
    );

    public static function getInitializer(ClassLoader $loader)
    {
        return \Closure::bind(function () use ($loader) {
            $loader->prefixLengthsPsr4 = ComposerStaticInit1a64e4a2bee93f2d2c517a3b98c5161e::$prefixLengthsPsr4;
            $loader->prefixDirsPsr4 = ComposerStaticInit1a64e4a2bee93f2d2c517a3b98c5161e::$prefixDirsPsr4;
            $loader->classMap = ComposerStaticInit1a64e4a2bee93f2d2c517a3b98c5161e::$classMap;

        }, null, ClassLoader::class);
    }
}
