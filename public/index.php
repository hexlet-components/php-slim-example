<?php

// Подключение автозагрузки через composer
require __DIR__ . '/../vendor/autoload.php';

// Контейнеры в этом курсе не рассматриваются (это тема связанная с самим ООП), но если вам интересно, то посмотрите DI Container
use Slim\Factory\AppFactory;
use Slim\Middleware\MethodOverrideMiddleware;
use DI\Container;
use App\Validator;

// Старт PHP сессии
session_start();

const FILE_PATH = __DIR__ . '/../users.json';
const ADMIN_EMAIL = 'admin@hexlet.io';

function getUsers($request)
{
    return json_decode($request->getCookieParam('users') ?? '', true);
}

function filterUsersByName($users, $term)
{
    return array_filter($users, fn($user) => str_contains($user['nickname'], $term) !== false);
}

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
$app->add(MethodOverrideMiddleware::class);

$app->get('/', function ($request, $response) use ($router) {
    if (isset($_SESSION['isAdmin'])) {
        return $response->withRedirect($router->urlFor('users.index'));
    }

    $messages = $this->get('flash')->getMessages();
    $params = [
        'email' => '',
        'flash' => $messages ?? []
    ];

    return $this->get('renderer')->render($response, 'home.phtml', $params);
})->setName('home');

$app->post('/login', function ($request, $response) use ($router) {
    $email = $request->getParsedBodyParam('email');

    if ($email === ADMIN_EMAIL) {
        $_SESSION['isAdmin'] = true;

        return $response->withRedirect($router->urlFor('users.index'));
    }

    $this->get('flash')->addMessage('error', 'Access Denied!');

    return $response->withRedirect($router->urlFor('home'));
});

$app->delete('/logout', function ($request, $response) use ($router) {
        session_destroy();

        return $response->withRedirect($router->urlFor('home'));
});

$app->get('/users', function ($request, $response) {
    if (!isset($_SESSION['isAdmin'])) {
        $this->get('flash')->addMessage('error', 'Access Denied! Please login!');

        return $response->withRedirect($router->urlFor('home'));
    }

    $term = $request->getQueryParam('term') ?? '';
    $users = getUsers($request) ?? [];
    $usersList = isset($term) ? filterUsersByName($users, $term) : $users;

    $messages = $this->get('flash')->getMessages();

    $params = [
      'users' => $usersList,
      'term' => $term,
      'flash' => $messages
    ];

    return $this->get('renderer')->render($response, 'users/index.phtml', $params);
})->setName('users.index');

$app->post('/users', function ($request, $response) use ($router) {
    $users = getUsers($request);
    $userData = $request->getParsedBodyParam('user');

    $validator = new Validator();
    $errors = $validator->validate($userData);

    if (count($errors) === 0) {
        $id = uniqid();
        $users[$id] = $userData;

        $encodedUsers = json_encode($users);

        $this->get('flash')->addMessage('success', 'User was added successfully');

        return $response->withHeader('Set-Cookie', "users={$encodedUsers};path=/")
            ->withRedirect($router->urlFor('users.index'));
    }

    $params = [
        'userData' => $userData,
        'errors' => $errors
    ];

    return $this->get('renderer')->render($response->withStatus(422), 'users/new.phtml', $params);
})->setName('users.store');

$app->get('/users/new', function ($request, $response) {
    if (!isset($_SESSION['isAdmin'])) {
        $this->get('flash')->addMessage('error', 'Access Denied! Please login!');

        return $response->withRedirect($router->urlFor('home'));
    }

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

$app->get('/users/{id}', function ($request, $response, $args) {
    if (!isset($_SESSION['isAdmin'])) {
        $this->get('flash')->addMessage('error', 'Access Denied! Please login!');

        return $response->withRedirect($router->urlFor('home'));
    }

    $id = $args['id'];
    $users = getUsers($request);

    if (!array_key_exists($id, $users)) {
        return $response->write('Page not found')->withStatus(404);
    }

    $messages = $this->get('flash')->getMessages();

    $params = [
        'id' => $id,
        'user' => $users[$id],
        'flash' => $messages
    ];

    return $this->get('renderer')->render($response, 'users/show.phtml', $params);
})->setName('users.show');

$app->get('/users/{id}/edit', function ($request, $response, $args) {
    if (!isset($_SESSION['isAdmin'])) {
        $this->get('flash')->addMessage('error', 'Access Denied! Please login!');

        return $response->withRedirect($router->urlFor('home'));
    }

    $messages = $this->get('flash')->getMessages();
    $id = $args['id'];
    $users = getUsers($request);
    $userData = $users[$id];
    $params = [
        'id' => $id,
        'userData' => $userData,
        'errors' => [],
        'flash' => $messages
    ];

    return $this->get('renderer')->render($response, 'users/edit.phtml', $params);
})->setName('users.edit');

$app->patch('/users/{id}', function ($request, $response, $args) use ($router) {
    $id = $args['id'];
    $users = getUsers($request);
    $user = $users[$id];
    $userData = $request->getParsedBodyParam('user');

    $validator = new Validator();
    $errors = $validator->validate($userData);

    if (count($errors) === 0) {
        $user['nickname'] = $userData['nickname'];
        $user['email'] = $userData['email'];
        $users[$id] = $user;

        $encodedUsers = json_encode($users);

        $this->get('flash')->addMessage('success', "User was updated successfully");

        return $response->withHeader('Set-Cookie', "users={$encodedUsers};path=/")
            ->withRedirect($router->urlFor('users.show', $args));
    }

    $params = [
        'id' => $id,
        'userData' => $userData,
        'errors' => $errors
    ];

    return $this->get('renderer')->render($response->withStatus(422), 'users/edit.phtml', $params);
});

$app->delete('/users/{id}', function ($request, $response, $args) use ($router) {
    $id = $args['id'];
    $users = getUsers($request);
    unset($users[$id]);

    $encodedUsers = json_encode($users);

    $this->get('flash')->addMessage('success', "User was deleted successfully");

    return $response->withHeader('Set-Cookie', "users={$encodedUsers};path=/")
        ->withRedirect($router->urlFor('users.index'));
});

$app->run();
