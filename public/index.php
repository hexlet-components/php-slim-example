<?php

// Подключение автозагрузки через composer
require __DIR__ . '/../vendor/autoload.php';

// Контейнеры в этом курсе не рассматриваются (это тема связанная с самим ООП), но если вам интересно, то посмотрите DI Container
use Slim\Factory\AppFactory;
use DI\Container;

const FILE_PATH = __DIR__ . '/../users.json';

function filterUsersByName($users, $term)
{
    return array_filter($users, fn($user) => str_contains($user['nickname'], $term) !== false);
}

$container = new Container();
$container->set('renderer', function () {
    // Параметром передается базовая директория, в которой будут храниться шаблоны
    return new \Slim\Views\PhpRenderer(__DIR__ . '/../templates');
});
$app = AppFactory::createFromContainer($container);

$router = $app->getRouteCollector()->getRouteParser();

$app->addErrorMiddleware(true, true, true);

$app->get('/', function ($request, $response) {
    $response->getBody()->write('Welcome to Slim!');
    return $response;
    // Благодаря пакету slim/http этот же код можно записать короче
    // return $response->write('Welcome to Slim!');
})->setName('home');

$app->get('/users', function ($request, $response) {
    $users = [];

    if (file_exists(FILE_PATH)) {
        $users = json_decode(file_get_contents(FILE_PATH), true);
    }

    $term = $request->getQueryParam('term') ?? '';
    $usersList = isset($term) ? filterUsersByName($users, $term) : $users;

    $params = [
      'users' => $usersList,
      'term' => $term
    ];

    return $this->get('renderer')->render($response, 'users/index.phtml', $params);
})->setName('users.index');

$app->post('/users', function ($request, $response) use ($router) {
    $userData = $request->getParsedBodyParam('user');

    if (!file_exists(FILE_PATH)) {
        touch(FILE_PATH);
    }

    $data = file_get_contents(FILE_PATH);
    $users = json_decode($data, true);

    $id = uniqid();
    $users[$id] = $userData;

    $encodedUsers = json_encode($users);

    file_put_contents(FILE_PATH, $encodedUsers);

    return $response->withRedirect($router->urlFor('users.index'));
})->setName('users.store');

$app->get('/users/new', function ($request, $response) {
    return $this->get('renderer')->render($response, 'users/new.phtml');
})->setName('users.create');

$app->get('/courses/{id}', function ($request, $response, array $args) {
    $id = $args['id'];
    return $response->write("Course id: {$id}");
})->setName('courses.show');

$app->get('/users/{id}', function ($request, $response, $args) {
    $params = ['id' => $args['id'], 'nickname' => 'user-' . $args['id']];
    // Указанный путь считается относительно базовой директории для шаблонов, заданной на этапе конфигурации
    // $this доступен внутри анонимной функции благодаря https://php.net/manual/ru/closure.bindto.php
    // $this в Slim это контейнер зависимостей
    return $this->get('renderer')->render($response, 'users/show.phtml', $params);
})->setName('users.show');

$app->run();
