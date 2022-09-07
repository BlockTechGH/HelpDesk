<?php
declare(strict_types=1);

/**
 * CakePHP(tm) : Rapid Development Framework (https://cakephp.org)
 * Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 *
 * Licensed under The MIT License
 * For full copyright and license information, please see the LICENSE.txt
 * Redistributions of files must retain the above copyright notice.
 *
 * @copyright Copyright (c) Cake Software Foundation, Inc. (https://cakefoundation.org)
 * @link      https://cakephp.org CakePHP(tm) Project
 * @since     0.2.9
 * @license   https://opensource.org/licenses/mit-license.php MIT License
 */
namespace App\Controller;

use Cake\Core\Configure;
use Cake\Event\EventInterface;
use Cake\Http\Response;
use Cake\Routing\Router;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

class BitrixController extends AppController
{
    protected $KaleyraConnections;
    protected $WhatsAppTemplates;
    protected $Languages;

    const CONNECTOR_CLOSED_EVENT = "ONIMCONNECTORSTATUSDELETE";
    const CONNECTOR_LINE_CLOSED_EVENT = "ONIMCONNECTORLINEDELETE";

    const PLACEMENT_LEAD_DETAIL = "CRM_LEAD_DETAIL_ACTIVITY";
    const PLACEMENT_DEAL_DETAIL = "CRM_DEAL_DETAIL_ACTIVITY";
    const PLACEMENT_CONTACT_DETAIL = "CRM_CONTACT_DETAIL_ACTIVITY";
    const PLACEMENT_COMPANY_DETAIL = "CRM_COMPANY_DETAIL_ACTIVITY";

    // Exists only 4 placements to open 'Send templated message' from. Calling method by name is more clear than if-else's tree.
    const PHONE_LOADERS = [
        self::PLACEMENT_LEAD_DETAIL => 'getLeadPhones',
        self::PLACEMENT_CONTACT_DETAIL => 'getPersonalPhones',
        self::PLACEMENT_DEAL_DETAIL => 'getDealPhones',
        self::PLACEMENT_COMPANY_DETAIL => 'getCompanyPhones',
    ];

    const ENTITY_LOADERS = [
        self::PLACEMENT_CONTACT_DETAIL => 'getContact',
        self::PLACEMENT_DEAL_DETAIL => 'getDeal',
        self::PLACEMENT_LEAD_DETAIL => 'getLead',
        self::PLACEMENT_COMPANY_DETAIL => 'getCompany'
    ];

    /**
     * beforeFilter callback.
     *
     * @param \Cake\Event\EventInterface $event Event.
     * @return \Cake\Http\Response|null|void
     */
    public function beforeFilter(EventInterface $event)
    {
        parent::beforeFilter($event);
        $this->loadComponent('Bx24');
        $this->KaleyraConnections = $this->getTableLocator()
            ->get('KaleyraConnections');
        $this->WhatsAppTemplates = $this->getTableLocator()
            ->get('WhatsappMessageTemplates');
        $this->Languages = $this->getTableLocator()
            ->get('LangCodes');

        // we need redirect if request not form Bitrix24
        if(!$this->isAccessFromBitrix)
        {
            return $this->redirect([
                '_name' => 'home',
            ]);
        }

        $logFile = Configure::read('AppConfig.LogsFilePath') . DS . 'bitrix_controller.log';
        $this->BxControllerLogger = new Logger('BitrixController');
        $this->BxControllerLogger->pushHandler(new StreamHandler($logFile, Logger::DEBUG));
    }

    public function displaySettingsInterface()
    {
        $data = $this->request->getParsedBody();
        $options = $data['PLACEMENT_OPTIONS'];
        $opts = json_decode($options, true);
        $line = intval($opts['LINE']);

        $flashOptions = [
            'params' => [
                'dismissible' => true,
            ]
        ];

        if(isset($data['saveSettings']))
        {
            $record = $this->KaleyraConnections->addConnection(
                $this->memberId,
                $data['apiKey'],
                $data['phoneNumber'],
                $data['sid'],
                $line,
                $data['widgetName']
            );
            $this->set('errors', $record->getErrors());

            if(!empty($data['PLACEMENT_OPTIONS']) && count($record->getErrors()) == 0)
            {
                $this->Bx24->activateConnector($data['phoneNumber'], $data['widgetName'], $options);
                $this->Flash->success(__('Connection data saved successfully'), $flashOptions);
            }
        } elseif (!empty($options)) {
            $data = $this->KaleyraConnections->getRecordDescription($this->memberId, $line);
        }

        $this->set('domain', $this->domain);
        $this->set('widgetName', $data['widgetName']);
        $this->set('phoneNumber', $data['phoneNumber']);
        $this->set('apiKey', $data['apiKey']);
        $this->set('sid', $data['sid']);

        // hidden fields from app installation
        $this->set('authId', $this->authId);
        $this->set('authExpires', $this->authExpires);
        $this->set('refreshId', $this->refreshId);
        $this->set('memberId', $this->memberId);
        $this->set('options', $options);

        $wwwBaseURL = Configure::read('AppConfig.appBaseUrl');
        $callback = ($wwwBaseURL)
            ? "{$wwwBaseURL}" . Router::url(['_name' => 'kaleyra_handler'])
            : Router::url(['_name' => 'kaleyra_handler'], true);
        $this->set('callbackURL', $callback);
    }

    public function displayCrmInterface()
    {
        $data = $this->request->getParsedBody();
        $properties = $this->request->getQueryParams();
        $all = array_merge($data, $properties);
        $this->BxControllerLogger->debug("displayCrmInterface - Show send message interface request", $all);

        if(!empty($data['action']))
        {
            $do = $data['action'];
            $this->autoRender = false;
            $data = [
                'id' => $data['id'],
                'name' => $data['title'],
                'header' => $data['header'],
                'id_lang' => $data['id_lang'],
                'placeholders' => $data['placeholders']
            ];
            switch ($do) {
                case "save": $data["id"] = $this->WhatsAppTemplates->store($data); break;
                case "delete": $this->WhatsAppTemplates->remove($data['id'], $data['name']); break;
            }
            return new Response(['body' => json_encode($data)]);
        }

        $entityType = $data['PLACEMENT'];
        $placementOptions = json_decode($data['PLACEMENT_OPTIONS'], true);
        $targetId = intval($placementOptions['ID']);

        // Get communication entity: Contact, Deal, Company or Lead
        $entityLoader = self::ENTITY_LOADERS[$entityType];
        $entity = method_exists($this->Bx24, $entityLoader) ? $this->Bx24->$entityLoader($targetId) : [];

        $title = $this->Bx24->getEntityTitle($entity, $entityType == self::PLACEMENT_LEAD_DETAIL);

        // Fill lists
        $phoneExtractor = self::PHONE_LOADERS[$entityType];
        $phoneNumbers = method_exists($this->Bx24, $phoneExtractor) ? $this->Bx24->$phoneExtractor($entity) : [];
        $templates = $this->WhatsAppTemplates->getSelectableList();
        $companyPhoneNumbers = $this->KaleyraConnections->getPhoneNumbers();
        $languages = $this->Languages->getSelectableList();
        $this->WhatsAppTemplates->extendByLanguages($templates, $languages);

        $responsibleId = isset($data['auth']) && isset($data['auth']['user_id']) ? intval($data['auth']['user_id']) : 1;
        $idLang = 1;
        $link = '';
        if(!empty($data['sendMessage']))
        {
            $recipientPhoneNumber = $data['phoneNumber'];
            $template = $data['template'] ?? 'classic_hello';
            $channelPhone = $data['companyPhoneNumber'];
            $idLang = $templates[$template]['id_lang'];//intval($data['lang']);
            $responsibleUser = $this->Bx24->getCurrentUser();
            $responsibleId = $responsibleUser ? ($responsibleUser["ID"] ?? $responsibleUser["id"]) : 0;
            $this->BxControllerLogger->debug("displayCrmInterface - ResponsibleUser", ['user' => $responsibleUser]);
            $connection = $this->KaleyraConnections->getConnectionByPhoneNumber($channelPhone);
            $this->loadComponent('Kaleyra', [
                'connection' => $connection
            ]);

            $pattern = $this->WhatsAppTemplates->getOne($templates[$template]['title'], $idLang);
            if (!$pattern) {
                $this->Flash->error(__("Template {$templates[$template]['title']} not support this language {$languages[$idLang]['code']}"));
                $phoneNumbers[$recipientPhoneNumber]['selected'] = true;
                $templates[$template]['selected'] = true;
                $companyPhoneNumbers[$channelPhone]['selected'] = true;
                $languages[$idLang]['selected'] = true;

                $this->set('formTitle', $title);
                $this->set('phoneNumbers', $phoneNumbers);
                $this->set('templates', $templates);
                $this->set('companyPhoneNumbers', $companyPhoneNumbers);
                $this->set('langs', $languages);
                $this->set('idLang', $idLang);
                $this->set('link', $link);

                $this->set('domain', $this->domain);
                $this->set('authId', $this->authId);
                $this->set('authExpires', $this->authExpires);
                $this->set('refreshId', $this->refreshId);
                $this->set('memberId', $this->memberId);
                $this->set('placementOptions', $data['PLACEMENT_OPTIONS']);
                $this->set('placement', $data['PLACEMENT']);
                $this->set('userID', $responsibleId);
                return;
            }
            $link = $data['mediaUrl'];
            $attachment = $this->request->getData('fileUpload');
            if ($attachment && $attachment->getSize() > 0) {
                // Temporary file has no extension on name. Kaleyra require file extension in name else File type is not supported error.
                $dir = dirname(realpath($attachment->getStream()->getMetadata('uri')));
                $name = $attachment->getClientFilename();
                $fileName = $dir . DS . $name;
                $attachment->moveTo($fileName);
                $link = "@{$fileName}";
            }

            $config = [
                'client' => $entity,
                'contact' => $phoneNumbers[$recipientPhoneNumber],
            ];
            $options = $this->Kaleyra->fillParameters($pattern['placeholders'], $config);
            $kaleyraMessageResponse = $this->Kaleyra->sendKaleyraTemplatedMessage(
                $pattern['name'],
                $channelPhone,
                $recipientPhoneNumber,
                $link,
                $options,
                $pattern['header'],
                $languages[$idLang]['code']
            );

            $phoneNumbers[$recipientPhoneNumber]['selected'] = true;
            $templates[$template]['selected'] = true;
            $companyPhoneNumbers[$channelPhone]['selected'] = true;
            $languages[$idLang]['selected'] = true;

            $ok = count($kaleyraMessageResponse['error']) == 0;
            $config['client']['phone'] = $recipientPhoneNumber;
            $subject = __('Template whatsapp message from {0}', [$connection->phone_number]);
            if ($ok){
                $this->Flash->success(__("Your message delivered successful."));

                $placeholdersLine = implode(", ", array_values($options));
                $template = $templates[$template]['title'];
                $body = "Template: {$template}\nStatus: sent";
                if (trim($placeholdersLine) != '') {
                    $body .= "\nPlaceholder values: {$placeholdersLine}";
                }
            } else {
                $this->Flash->error(__("An error occurred while sending the message"));
                $this->BxControllerLogger->error("An error occurred while sending the message", [
                    'errors' => $kaleyraMessageResponse['error']
                ]);
                $errorsInLines = implode(";\n\t-", $kaleyraMessageResponse['error']);
                $body = "Error message:\n\t- {$errorsInLines}";
            }
            $this->Bx24->createCrmActivity($kaleyraMessageResponse['id'], $subject, $body, $entity, $config['client'], $responsibleId, $ok, true);
        }

        $this->set('formTitle', $title);
        $this->set('phoneNumbers', $phoneNumbers);
        $this->set('templates', $templates);
        $this->set('companyPhoneNumbers', $companyPhoneNumbers);
        $this->set('langs', $languages);
        $this->set('idLang', $idLang);
        $this->set('link', $link);

        $this->set('domain', $this->domain);
        $this->set('authId', $this->authId);
        $this->set('authExpires', $this->authExpires);
        $this->set('refreshId', $this->refreshId);
        $this->set('memberId', $this->memberId);
        $this->set('placementOptions', $data['PLACEMENT_OPTIONS']);
        $this->set('placement', $data['PLACEMENT']);
        $this->set('userID', $responsibleId);
    }

    public function handleOCEvents()
    {
        $this->autoRender = false;

        $data = $this->request->getData('data');
        $event = $this->request->getData('event');

        $this->BxControllerLogger->debug('handleOCEvents', [
            'event' => $event,
            'data' => $data
        ]);

        if($event == self::CONNECTOR_CLOSED_EVENT || $event == self::CONNECTOR_LINE_CLOSED_EVENT)
        {
            /*
             * Documentation:
             *   https://dev.1c-bitrix.ru/rest_help/imconnector/events/onImconnectorlinedelete.php
             *   https://dev.1c-bitrix.ru/rest_help/imconnector/events/onimconnectorstatusdelete.php
             * But real case is:
             *   data is object with fields 'connector' and 'line' (register is required) if event is ONIMCONNECTORSTATUSDELETE;
             *   data is number of line if event is ONIMCONNECTORLINEDELETE.
             * at April 2022.
             */
            $line = (int)($data['line'] ?? $data);
            $this->BxControllerLogger->debug('handleOCEvents - line', [
                'line' => $line,
            ]);

            $this->KaleyraConnections->removeConnectionByline($line);
        }
    }

    public function handleBitrixMessage()
    {
        $this->autoRender = false;

        $data = $this->request->getData('data');

        $this->BxControllerLogger->debug('handleBitrixMessage', [
            'data' => $data
        ]);

        $connection = $this->KaleyraConnections->getConnectionByLine(intval($data['LINE']));

        $this->BxControllerLogger->debug('handleBitrixMessage - getConnection', [
            '$connection' => $connection
        ]);

        $this->loadComponent('Kaleyra', [
            'connection' => $connection,
            // Chat ID in Bitrix24, uses for sending a system messages (like error) by Kaleyra API callback request.
            'chat_id' => $data['MESSAGES'][0]['im']['chat_id']
        ]);

        $messages = $this->Kaleyra->makeKaleyraMessagesFromBx24Messages($data['MESSAGES']);

        $this->BxControllerLogger->debug('handleBitrixMessage - messages', [
            '$messages' => $messages
        ]);

        $response = $this->Kaleyra->sendBatchMessages($messages);
        $chatErrors = [];
        foreach ($data['MESSAGES'] as $i => $message){
            if (!empty($response[$i]['error']) && !empty($response[$i]['error']['error']))
            {
                $chatErrors[$message['im']['chat_id']] = $response[$i]['error']['error'];
            }
        }
        if (count($chatErrors) > 0) {
            $this->Bx24->sendSystemMessagesToBxChat($chatErrors);
        }
    }
}
