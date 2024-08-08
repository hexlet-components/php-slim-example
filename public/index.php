<?php

// Подключение автозагрузки через composer
require __DIR__ . '/../vendor/autoload.php';

// Контейнеры в этом курсе не рассматриваются (это тема связанная с самим ООП), но если вам интересно, то посмотрите DI Container

use App\Car;
use App\CarRepository;
use App\CarValidator;
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

$container->set(\PDO::class, function () {
    $conn = new \PDO('sqlite:hexlet');
    $conn->setAttribute(\PDO::ATTR_DEFAULT_FETCH_MODE, \PDO::FETCH_ASSOC);
    return $conn;
});

$initFilePath = implode('/', [dirname(__DIR__), 'init.sql']);
$initSql = file_get_contents($initFilePath);
$container->get(\PDO::class)->exec($initSql);

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

$app->get('/users', function ($request, $response) use ($router) {
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

$app->get('/users/new', function ($request, $response) use ($router){
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

$app->get('/users/{id}', function ($request, $response, $args) use ($router) {
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

$app->get('/users/{id}/edit', function ($request, $response, $args) use ($router) {
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

$app->get('/cars', function ($request, $response) {
    $carRepository = $this->get(CarRepository::class);
    $cars = $carRepository->getEntities();

    $messages = $this->get('flash')->getMessages();

    $params = [
      'cars' => $cars,
      'flash' => $messages
    ];

    return $this->get('renderer')->render($response, 'cars/index.phtml', $params);
})->setName('cars.index');

$app->post('/cars', function ($request, $response) use ($router) {
    $carRepository = $this->get(CarRepository::class);
    $carData = $request->getParsedBodyParam('car');

    $validator = new CarValidator();
    $errors = $validator->validate($carData);

    if (count($errors) === 0) {
        $car = Car::fromArray([$carData['make'], $carData['model']]);
        $carRepository->save($car);
        $this->get('flash')->addMessage('success', 'Car was added successfully');
        return $response->withRedirect($router->urlFor('cars.index'));
    }

    $params = [
        'car' => $carData,
        'errors' => $errors
    ];

    return $this->get('renderer')->render($response->withStatus(422), 'cars/new.phtml', $params);
})->setName('cars.store');

$app->get('/cars/new', function ($request, $response) {
    $params = [
        'car' => new Car(),
        'errors' => []
    ];

    return $this->get('renderer')->render($response, 'cars/new.phtml', $params);
})->setName('cars.create');

$app->get('/cars/{id}', function ($request, $response, $args) {
    $carRepository = $this->get(CarRepository::class);
    $id = $args['id'];
    $car = $carRepository->find($id);

    if (is_null($car)) {
        return $response->write('Page not found')->withStatus(404);
    }

    $messages = $this->get('flash')->getMessages();

    $params = [
        'car' => $car,
        'flash' => $messages
    ];

    return $this->get('renderer')->render($response, 'cars/show.phtml', $params);
})->setName('cars.show');

$app->get('/cars/{id}/edit', function ($request, $response, $args) {
    $carRepository = $this->get(CarRepository::class);
    $messages = $this->get('flash')->getMessages();
    $id = $args['id'];
    $car = $carRepository->find($id);

    $params = [
        'car' => $car,
        'errors' => [],
        'flash' => $messages
    ];

    return $this->get('renderer')->render($response, 'cars/edit.phtml', $params);
})->setName('cars.edit');

$app->patch('/cars/{id}', function ($request, $response, $args) use ($router) {
    $carRepository = $this->get(CarRepository::class);
    $id = $args['id'];

    $car = $carRepository->find($id);

    if (is_null($car)) {
        return $response->write('Page not found')->withStatus(404);
    }

    $carData = $request->getParsedBodyParam('car');
    $validator = new CarValidator();
    $errors = $validator->validate($carData);

    if (count($errors) === 0) {
        $car->setMake($carData['make']);
        $car->setModel($carData['model']);
        $carRepository->save($car);
        $this->get('flash')->addMessage('success', "Car was updated successfully");
        return $response->withRedirect($router->urlFor('cars.show', $args));
    }

    $params = [
        'car' => $car,
        'errors' => $errors
    ];

    return $this->get('renderer')->render($response->withStatus(422), 'cars/edit.phtml', $params);
});

$app->run();
