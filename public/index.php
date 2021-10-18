<?php

// Подключение автозагрузки через composer
require __DIR__ . '/../vendor/autoload.php';

// Контейнеры в этом курсе не рассматриваются (это тема связанная с самим ООП), но если вам интересно, то посмотрите DI Container
use Slim\Factory\AppFactory;
use DI\Container;
use App\Validator;

// Старт PHP сессии
session_start();

const FILE_PATH = __DIR__ . '/../users.json';

function getUsers($filepath)
{
    if (!file_exists($filepath)) {
        file_put_contents($filepath, '{}');
    }

    return json_decode(file_get_contents($filepath), true);
}

function saveUsers($filepath, $users)
{
    file_put_contents($filepath, json_encode($users));
}

function filterUsersByName($users, $term)
{
    return array_filter($users, fn($user) => str_contains($user['nickname'], $term) !== false);
}

$users = getUsers(FILE_PATH);

$container = new Container();
$container->set('renderer', function () {
    // Параметром передается базовая директория, в которой будут храниться шаблоны
    return new \Slim\Views\PhpRenderer(__DIR__ . '/../templates');
});
$container->set('flash', function () {
    return new \Slim\Flash\Messages();
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

$app->get('/users', function ($request, $response) use ($users) {
    $term = $request->getQueryParam('term') ?? '';
    $usersList = isset($term) ? filterUsersByName($users, $term) : $users;

    $messages = $this->get('flash')->getMessages();

    $params = [
      'users' => $usersList,
      'term' => $term,
      'flash' => $messages
    ];

    return $this->get('renderer')->render($response, 'users/index.phtml', $params);
})->setName('users.index');

$app->post('/users', function ($request, $response) use ($router, $users) {
    $userData = $request->getParsedBodyParam('user');

    $validator = new Validator();
    $errors = $validator->validate($userData);

    if (count($errors) === 0) {
        $id = uniqid();
        $users[$id] = $userData;

        saveUsers(FILE_PATH, $users);

        $this->get('flash')->addMessage('success', 'User was added successfully');

        return $response->withRedirect($router->urlFor('users.index'));
    }

    $params = [
        'userData' => $userData,
        'errors' => $errors
    ];

    return $this->get('renderer')->render($response->withStatus(422), 'users/new.phtml', $params);
})->setName('users.store');

$app->get('/users/new', function ($request, $response) {
    $params = [
        'userData' => [],
        'errors' => []
    ];

    return $this->get('renderer')->render($response, 'users/new.phtml', $params);
})->setName('users.create');

$app->get('/courses/{id}', function ($request, $response, array $args) {
    $id = $args['id'];
    return $response->write("Course id: {$id}");
})->setName('courses.show');

$app->get('/users/{id}', function ($request, $response, $args) use ($users) {
    $id = $args['id'];

    if (!array_key_exists($id, $users)) {
        return $response->write('Page not found')->withStatus(404);
    }

    $params = [
        'id' => $id,
        'nickname' => $users[$id]['nickname'],
    ];

    return $this->get('renderer')->render($response, 'users/show.phtml', $params);
})->setName('users.show');

$app->run();
