<?php

namespace App\Controller\Component;

use App\Model\Entity\KaleyraConnection;
use Cake\Controller\Component;
use Cake\Core\Configure;
use Cake\Http\Client;
use Cake\Routing\Router;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

class KaleyraComponent extends Component
{
    private $Logger = null;
    private $httpHeaders = [];
    private $HttpClient = null;

    private $apiURL = 'https://api.kaleyra.io/v1/';
    private $phoneNumber;
    private $callback = null;

    public function initialize(array $config): void
    {
        parent::initialize($config);

        $logFile = Configure::read('AppConfig.LogsFilePath') . DS . 'kaleyra.log';
        $this->Logger = new Logger('KaleyraAPI');
        $this->Logger->pushHandler(new StreamHandler($logFile, Logger::DEBUG));

        if (!$config['connection']) {
            throw new \Exception(__("Init connection before calling API"));
        }
        /** @var KaleyraConnection $connection */
        $connection = $config['connection'];
        $this->phoneNumber = $connection->phone_number;

        $this->HttpClient = new Client();
        $this->httpHeaders = [
            "api-key"  => $connection->api_key
        ];
        $this->apiURL .= "{$connection->sid}/messages";

        $wwwBaseURL = Configure::read('AppConfig.appBaseUrl');
        $this->callback = ($wwwBaseURL)
            ? "{$wwwBaseURL}" . Router::url(['_name' => 'kaleyra_handler'])
            : Router::url(['_name' => 'kaleyra_handler'], true);
        // Life hack: Kaleyra API return phone number of receiver only on they callbacks.
        // We send phone of sender (WhatsApp phone on Open Connection) with callback URL and take it in KaleyraController->handle().
        $addition = ["from_number" => $this->phoneNumber];
        if (isset($config['chat_id'])) {
            // Chat ID in Bitrix24. It's uses for sending system messages into the chat.
            $addition['chat_id'] = $config['chat_id'];
        }
        $this->callback .= "?" . http_build_query($addition);
    }

    /**
     * @param array $options ['client' => recipient's Contract entity, 'operator' => operator's Contract entity, ...optionally by name like placeholder]
     * @return void
     */
    public function fillParameters(string $placeholders, array $options) : array
    {
        $values = [];
        $onlyNames = str_replace(['{', '}'], '', $placeholders);
        $holders = explode(",", $onlyNames);
        if (!!$options['client']) {
            $this->Logger->debug('client properties as default', $options['client']);
            foreach ($holders as $placeholder)
            {
                if (empty($values[$placeholder]))
                    $values[$placeholder] = $this->getPlaceValue($placeholder, $options['client']);
            }
        }

        foreach ($holders as $placeholder)
        {
            if (empty($values[$placeholder]))
                $values[$placeholder] = $this->getPlaceValue($placeholder, $options);
        }

        return $values;
    }

    public function sendKaleyraTemplatedMessage(
        string $template,
        string $sender,
        string $receiver,
        string $mediaUrl = null,
        array $parameters = [],
        string $title = '',
        string $lang = 'en'
    )
    {
        $messageParameters = [
            'from' => $sender,
            'to' => $receiver,
            'type' => 'template',
            'channel' => 'whatsapp',
            'template_name' => $template,
            'lang_code' => $lang,
            'params' => implode(',', array_map(function ($value) { return "\"{$value}\""; }, array_values($parameters))),
            'callback_url' => $this->callback
        ];
        if($messageParameters['params'] === '""') {
            unset($messageParameters['params']);
        }
        if($mediaUrl)
        {
            $messageParameters['type'] = 'mediatemplate';
            if($mediaUrl[0] == '@')
            {
                $messageParameters['media'] = fopen(mb_substr($mediaUrl, 1), 'r');
            } else {
                $messageParameters['media_url'] = $mediaUrl;
            }
            $messageParameters['template_header'] = $title;
        }
        return $this->_sendRequest($this->apiURL, $messageParameters);
    }

    public function makeKaleyraMessagesFromBx24Messages(array $bitrixMessages) : array
    {
        $this->Logger->debug("Bitrix messages", $bitrixMessages);
        $kaleyraMessages = [];

        foreach($bitrixMessages as $message)
        {
            $text = $this->remove_bbcode($message['message']['text']);
            $receiver = $message['chat']['id'];
            $bxMessageId = $message['im']['message_id'];
            if($text)
            {
                $kaleyraMessage = [
                    'sender' => $this->phoneNumber,
                    'receiver' => $receiver,
                    'type' => 'text',
                    'content' => $text,
                    'caption' => '',
                    'bx_id' => $bxMessageId
                ];
                $kaleyraMessages[] = $kaleyraMessage;
            }

            $files = $message['message']['files'] ?? null;
            if($files)
            {
                $muchMoreThenOneFile = count($files) > 1;
                foreach($files as $i => $file)
                {
                    $kaleyraMessage = [
                        'sender' => $this->phoneNumber,
                        'receiver' => $receiver,
                        'type' => 'media',
                        'content' => $this->remove_bbcode($file['link']),
                        'caption' => '',
                        'bx_id' => $bxMessageId
                    ];

                    $postfix = mb_substr($kaleyraMessage['content'], -5);
                    if(!strpos($postfix, '.'))
                    {
                        $kaleyraMessage['content'] .= '#' . urlencode($file['name']);
                    }
                    $kaleyraMessages[] = $kaleyraMessage;
                }
            }
        }
        return $kaleyraMessages;
    }

    public function sendBatchMessages($messages)
    {
        $response = [];
        foreach($messages as $message)
        {
            $response[] = $this->sendMessage(
                $message['sender'],
                $message['receiver'],
                $message['content'],
                $message['type'],
                $message['caption']
            );
        }
        return $response;
    }

    private function sendMessage(
        string $sender,
        string $receiver,
        string $content,
        string $type = "text",
        string $caption = ""
    )
    {
        $textual = $type == 'text';
        $parameters = [
            'from' => $sender,
            'to' => $receiver,
            'channel' => 'whatsapp',
            'type' => $type,
            'body' => $content,
            'callback_url' => $this->callback
        ];
        if(!$textual)
        {
            unset($parameters['body']);
            $parameters['caption'] = $caption;
            $parameters['media_url'] = $content;
        }

        return $this->_sendRequest($this->apiURL, $parameters);
    }

    private function remove_bbcode(string $value) : string
    {
        return trim( // Remove spaces after BB codes.
            preg_replace(
                '~\[([a-z]+)[^]]*]~s', // Remove singles BB codes
                '',
                preg_replace('~\[([a-z]+)[^]]*].*?\[/\1]~s', '', $value) // Remove pairings BB codes
            )
        );
    }

    private function _sendRequest(string $url, array $postFields)
    {
        try
        {
            $response = $this->HttpClient->post($url, $postFields,  [ 'headers' => $this->httpHeaders, ]);
            $this->Logger->debug("sendMessage", [
                'url' => $url,
                'parameters' => $postFields,
                'status' => $response->getStatusCode(),
                'response' => $response->getJson(),
            ]);
            return $response->getJson();
        } catch (\Exception $e) {
            $this->Logger->error("Kaleyra API error {$e->getMessage()} in context", [
                'url' => $url,
                'parameters' => $postFields,
                'headers' => $this->httpHeaders,
            ]);
            return null;
        }
    }

    private function getPlaceValue(string $placeholder, array $entity)
    {
        $placeholder = trim($placeholder);
        $parts = explode('.', $placeholder);
        if (count($parts) == 1) {
            if (!empty($entity[$placeholder])) {
                return $entity[$placeholder];
            }

            $placeholder_as_name = mb_convert_case($placeholder, MB_CASE_UPPER);
            if (!empty($entity[$placeholder_as_name])) {
                return $entity[$placeholder_as_name];
            }

            $normalized_form = $this->makeUserFieldName($placeholder, '');
            if (!empty($entity[$normalized_form])) {
                return $entity[$normalized_form];
            }

            return null;
        }
        if (empty($entity[$parts[0]])) {
            return null;
        }
        return $this->getPlaceValue(
            implode('.', array_slice($parts, 1)), // All levels of including
            $entity[$parts[0]]
        );
    }

    function makeUserFieldName(string $placeholder, $prefix = 'Current') : string
    {
        $fieldName = mb_ereg_replace($prefix, '', $placeholder);
        $parts = preg_split('/[A-Z]/', $fieldName, -1, PREG_SPLIT_NO_EMPTY | PREG_SPLIT_OFFSET_CAPTURE);
        return implode('_', array_map(function ($namePart) use ($fieldName) {
                return mb_convert_case($fieldName[$namePart[1]-1] . $namePart[0], MB_CASE_UPPER);
            }, $parts)
        );
    }
}
