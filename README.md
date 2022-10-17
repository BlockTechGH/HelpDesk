# Установка проекта

## 1. Клонируем окружение и устанавливаем его

[Подробнее в соответствующей ветке](https://main.git.e2e4gu.ru/E2E4/helpdesk_in_b24/src/branch/environment)

## 2. Клонируем приложение

```
git clone ssh://git@main.git.e2e4gu.ru:2222/E2E4/helpdesk_in_b24.git ./html/application
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
            'username' => 'helpdesk',
            'password' => 'helpdesk',
            'database' => 'helpdesk',
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

        // portal id
        'member_id' => '*****',

        // logs configurations
        'LogsFilePath' => '/var/log/helpdesk',
        'LogsLifeTime' => 10,

        // fill for local development, leave empty for production
        'appBaseUrl' => '', // ngrok url
        'itemsPostfix' => '', // unique postfix for app item names
    ],
];
```

## 6. Открываем приложение и добавляем самоподписной сертификат в список исключений браузера

[https://helpdesk.local:4433](https://helpdesk.local:4433)

## 7. Для доступа к phpmyadmin

[http://helpdesk.local:9876](http://helpdesk.local:9876)


