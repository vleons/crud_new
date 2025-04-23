<?php

require_once __DIR__.'/vendor/autoload.php';
require_once __DIR__.'/src/DatabaseManager.php';

use Silex\Application;
use SilexApp\DatabaseManager;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Silex\Provider\TwigServiceProvider;

$app = new Application();
$app['debug'] = true;

// Конфигурация загрузки файлов
$app['upload.public_dir'] = '/uploads'; // URL-путь
$app['upload.dir'] = __DIR__.'/public/uploads'; // Физический путь

// Создаем папку для загрузок, если ее нет
if (!file_exists($app['upload.dir'])) {
    mkdir($app['upload.dir'], 0777, true);
}

// Регистрация провайдеров
$app->register(new Silex\Provider\DoctrineServiceProvider(), [
    'db.options' => [
        'driver' => 'pdo_sqlite',
        'path'   => __DIR__.'/database.sqlite',
    ],
]);

$app->register(new TwigServiceProvider(), [
    'twig.path' => __DIR__.'/templates',
]);

// Инициализация DatabaseManager
$app['db_manager'] = function() use ($app) {
    return new DatabaseManager(
        $app['db'],
        $app['upload.dir'],
        $app['upload.public_dir']
    );
};

// Маршрут для обработки статических файлов
$app->get($app['upload.public_dir'].'/{filename}', function($filename) use ($app) {
    $filePath = $app['upload.dir'].'/'.$filename;
    
    if (!file_exists($filePath)) {
        return $app->abort(404, "File not found");
    }

    return $app->sendFile($filePath);
});

// Инициализация БД
$app->get('/init', function() use ($app) {
    $schema = <<<SQL
    CREATE TABLE IF NOT EXISTS products (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        name VARCHAR(255) NOT NULL,
        price DECIMAL(10,2) NOT NULL,
        description TEXT,
        sale_id INTEGER,
        property_id INTEGER,
        image_path VARCHAR(255),
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    );
    
    INSERT INTO products (name, price, description, sale_id, property_id) VALUES
    ('Анальгин', 53.02, 'Анальгетик-антипиретик', 1, 1),
    ('Фестал', 80.00, 'Ферментный препарат', NULL, 2),
    ('НО-ШПА', 120.00, 'Спазмолитик', 1, 1);
    SQL;
    
    $app['db']->exec($schema);
    return $app->redirect('/');
});

// Главная страница - список продуктов
$app->get('/', function() use ($app) {
    $products = $app['db_manager']->getAllProducts();
    return $app['twig']->render('products/list.twig', [
        'products' => $products
    ]);
});

// Создание продукта
$app->match('/products/create', function(Request $request) use ($app) {
    if ($request->isMethod('POST')) {
        $data = [
            'name' => $request->get('name'),
            'price' => (float)$request->get('price'),
            'description' => $request->get('description'),
            'sale_id' => $request->get('sale_id') ? (int)$request->get('sale_id') : null,
            'property_id' => $request->get('property_id') ? (int)$request->get('property_id') : null
        ];
        
        /** @var UploadedFile $image */
        $image = $request->files->get('image');
        
        try {
            $id = $app['db_manager']->createProduct($data, $image);
            return $app->redirect("/products/{$id}");
        } catch (Exception $e) {
            $error = 'Ошибка при создании продукта: ' . $e->getMessage();
        }
    }
    
    return $app['twig']->render('products/create.twig', [
        'error' => $error ?? null
    ]);
});

// Просмотр продукта
$app->get('/products/{id}', function($id) use ($app) {
    $product = $app['db_manager']->getProductById($id);
    
    if (!$product) {
        return $app->redirect('/');
    }
    
    return $app['twig']->render('products/view.twig', [
        'product' => $product
    ]);
});

// Редактирование продукта (POST обработчик)
$app->post('/products/{id}', function(Request $request, $id) use ($app) {
    $product = $app['db_manager']->getProductById($id);
    
    if (!$product) {
        return $app->redirect('/');
    }
    
    $data = [
        'name' => $request->get('name'),
        'price' => (float)$request->get('price'),
        'description' => $request->get('description'),
        'sale_id' => $request->get('sale_id') ? (int)$request->get('sale_id') : null,
        'property_id' => $request->get('property_id') ? (int)$request->get('property_id') : null
    ];
    
    /** @var UploadedFile $image */
    $image = $request->files->get('image');
    
    try {
        $app['db_manager']->updateProduct($id, $data, $image);
        return $app->redirect("/products/{$id}");
    } catch (Exception $e) {
        return $app['twig']->render('products/edit.twig', [
            'product' => $product,
            'error' => 'Ошибка при обновлении: ' . $e->getMessage()
        ]);
    }
});

// Форма редактирования продукта (GET обработчик)
$app->get('/products/{id}/edit', function($id) use ($app) {
    $product = $app['db_manager']->getProductById($id);
    
    if (!$product) {
        return $app->redirect('/');
    }
    
    return $app['twig']->render('products/edit.twig', [
        'product' => $product,
        'error' => null // Добавляем переменную error со значением null
    ]);
});

// Удаление продукта
$app->get('/products/{id}/delete', function($id) use ($app) {
    $app['db_manager']->deleteProduct($id);
    return $app->redirect('/');
});

// Обработка статических файлов
$app->get('/css/{file}', function($file) use ($app) {
    $filePath = __DIR__.'/public/css/'.$file;
    if (!file_exists($filePath)) {
        return $app->abort(404);
    }
    return $app->sendFile($filePath);
});

$app->get('/js/{file}', function($file) use ($app) {
    $filePath = __DIR__.'/public/js/'.$file;
    if (!file_exists($filePath)) {
        return $app->abort(404);
    }
    return $app->sendFile($filePath);
});

$app->run();