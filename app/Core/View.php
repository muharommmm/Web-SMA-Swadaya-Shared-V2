<?php

namespace App\Core;

class View
{
    public static function render(string $view, array $data = [], ?string $layout = 'layouts/public'): void
    {
        $viewsDir = dirname(__DIR__) . '/Views/';
        $viewFile = $viewsDir . $view . '.php';

        if (!is_file($viewFile)) {
            http_response_code(500);
            echo 'Template tidak ditemukan.';
            return;
        }

        extract($data, EXTR_SKIP);

        ob_start();
        require $viewFile;
        $content = ob_get_clean();

        if ($layout !== null) {
            $layoutFile = $viewsDir . $layout . '.php';
            if (is_file($layoutFile)) {
                require $layoutFile;
                return;
            }
        }

        echo $content;
    }

    public static function renderOperator(string $view, array $data = []): void
    {
        self::render($view, $data, 'layouts/operator');
    }

    public static function renderPartial(string $view, array $data = []): void
    {
        self::render($view, $data, null);
    }

    public static function partial(string $partial, array $data = []): string
    {
        $viewsDir = dirname(__DIR__) . '/Views/';
        $partialFile = $viewsDir . $partial . '.php';
        if (!is_file($partialFile)) {
            return '';
        }
        extract($data, EXTR_SKIP);
        ob_start();
        require $partialFile;
        return (string) ob_get_clean();
    }
}
