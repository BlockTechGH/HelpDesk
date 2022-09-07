<?php

namespace App\Controller;

use App\Model\Entity\BitrixToken;
use App\Model\Entity\KaleyraConnection;
use Cake\Core\Configure;
use Cake\Event\EventInterface;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

class KaleyraController extends AppController
{
    protected $KaleyraConnections;
    protected $BitrixTokens;
    protected $kaleyraLogger;

    public $replyTo = null;
    public $timestamp = null;
    public $type;

    /**
     * beforeFilter callback.
     *
     * @param \Cake\Event\EventInterface $event Event.
     * @return \Cake\Http\Response|null|void
     */
    public function beforeFilter(EventInterface $event)
    {
        parent::beforeFilter($event);
        $this->KaleyraConnections = $this
            ->getTableLocator()
            ->get('KaleyraConnections');
        $this->BitrixTokens = $this
            ->getTableLocator()
            ->get('BitrixTokens');

        // we need redirect if request not form Bitrix24
        if(!$this->isCallFromKaleyra)
        {
            return $this->redirect([
                '_name' => 'home',
            ]);
        }

        $this->replyTo = $this->request->getQuery('reply_to');
        $this->timestamp = $this->request->getQuery('timestamp');
        $this->type = $this->request->getQuery('type');

        $logFile = Configure::read('AppConfig.LogsFilePath') . DS . 'kaleyra.log';
        $this->kaleyraLogger = new Logger('kaleyra');
        $this->kaleyraLogger->pushHandler(new StreamHandler($logFile, Logger::DEBUG));
    }

    public function handle()
    {
        $this->autoRender = false;
        $data = $this->request->getQueryParams();
        $this->kaleyraLogger->debug('kaleyra sent request with', $data);

        if($this->status)
        {
            $this->wanumber = $data['from_number'];
        }

        /** @var KaleyraConnection $connectionRecord */
        $connectionRecord = $this->KaleyraConnections->getConnectionByPhoneNumber($this->wanumber);
        if(!$connectionRecord)
        {
            $this->kaleyraLogger->debug("Connection is not found by phone number {$this->wanumber}");
            return;
        }
        $this->memberId = $connectionRecord->member_id;

        /** @var BitrixToken $token */
        $token = $this->BitrixTokens->getTokenObjectByMemberId($this->memberId);
        $this->authId = $token->auth_id;
        $this->refreshId = $token->refresh_id;
        $this->domain = $token->domain;

        $this->loadComponent('Bx24');
        if($this->status)
        {
            $connector = $this->Bx24->getConnectorID();
            $messages = [
                [
                    'im' => null,
                    'chat' => [ 'id' => $this->mobile ],
                    'message' => ['id' => $this->messageId ] // $this->messageId
                ]
            ];
            if($this->status == 'failed' && !empty($data['chat_id']))
            {
                $idChat = intval($data['chat_id']);
                $this->Bx24->sendSystemMessagesToBxChat([ $idChat => $data['error'] ]);
            } else {
                $activity = $this->Bx24->findCRMActivityByMessageId(explode(":", $data['id'])[0]);
                if ($activity && isset($activity['ID'])) {
                    $body = mb_ereg_replace(
                        "Status: \w*\n",
                        "Status: {$data['status']}\n",
                        $activity['DESCRIPTION']
                    );
                    $this->kaleyraLogger->debug("handle - update activity text", [
                        'message_id' => $data['id'],
                        'activity.text' => $activity['DESCRIPTION'],
                        'status.new' => $data['text'],
                        'activity.text.new' => $body
                    ]);
                    $this->Bx24->updateCRMActivityDescription(intval($activity['ID']), $body);
                } else
                    $this->Bx24->markMessagesAs($connector, $connectionRecord->line, $messages, $this->status);
            }
        } else {
            $bitrixResponse = $this->Bx24->sendMessageToBxChat($connectionRecord->line, $this->message);
            $arrBxMessages = $bitrixResponse['result']['DATA']['RESULT'];
            $this->kaleyraLogger->debug('handle - Bx24 - sendMessageToBxChat - response', [ 'message' =>  $arrBxMessages]);
        }
    }
}
