# Установка проекта

## 1. Клонируем окружение и устанавливаем его

[Подробнее в соответсвующей ветке](https://main.git.e2e4gu.ru/E2E4/kaleyra-integration/src/branch/environment)

## 2. Клонируем приложение

```
git clone ssh://git@main.git.e2e4gu.ru:2222/E2E4/kaleyra-integration.git ./html/application
```

## 3. Заходим в контейнер и устанавливаем зависимости

```
./bash.sh
```

```
composer install
```

Выходим из контейнера и меняем владельца файлов на своего

```
sudo chown -R ${USER}:${USER} html/application
```

# Конфигурация

## 4. Настраиваем подключение к базе данных

```
cp html/application/config/app_local.example.php html/application/config/app_local.php
```

И вводим соответсвующие настройки

```
    'Datasources' => [
        'default' => [
            'host' => 'db',
            'username' => 'kaleyra',
            'password' => 'kaleyra',
            'database' => 'kaleyra',
            'encoding' => 'utf8mb4',
            'timezone' => 'UTC',
```

## 5. Настраиваем конфигурацию приложения

```
cp html/application/config/app_config.example.php html/application/config/app_config.php
```

и указываем следующие данные

```
return [
    'AppConfig' => [
        // see real values in project wiki
        'client_id' => '*****',
        'client_secret' => '*****',

        // logs configurations
        'LogsFilePath' => '/var/log/kaleyra',
        'LogsLifeTime' => 10,
    ],
];
```

## 6. Открываем приложение и добавляем самоподписной сертификат в список исключений браузера

[https://kaleyra.local:4433](https://kaleyra.local:4433)

## 7. Для доступа к phpmyadmin

[http://kaleyra.local:9876](http://kaleyra.local:9876)


