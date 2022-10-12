<?php
declare(strict_types=1);

namespace App\Controller;

use Cake\Core\Configure;
use Cake\Event\EventInterface;
use Cake\Http\Client\Response;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

class TicketController extends AppController
{
    private $BitrixTokens;
    private $Tickets;

    private $TicketControllerLogger;

    public function initialize(): void
    {
        $auth = $this->request->getData('auth');
        $this->memberId = $auth ? $auth['member_id'] : $this->request->getQuery('member_id');
        $this->domain = $auth ? $auth['DOMAIN'] : $this->request->getQuery('DOMAIN');

        if($auth)
        {
            $this->isAccessFromBitrix = true;
            $this->memberId = $auth['member_id'] ?? "";
            $this->authId = $auth['access_token'] ?? "";
            $this->refreshId = $auth['refresh_token'] ?? "";
            $this->authExpires = $auth['expires_in'] ?? "";
            $this->domain = $auth['domain'];
        } else {
            $this->authId = $this->request->getData('AUTH_ID') ?? '';
            $this->refreshId = $this->request->getData('REFRESH_ID') ?? '';
            $this->authExpires = (int)($this->request->getData('AUTH_EXPIRES')) ?? '';
        }
        if($this->memberId && !($this->refreshId && $this->authId && $this->authExpires))
        {
            $this->BitrixTokens = $this->getTableLocator()->get('BitrixTokens');
            $tokenRecord = $this->BitrixTokens->getTokenObjectByMemberId($this->memberId);
            $this->authId = $tokenRecord['auth_id'];
            $this->refreshId = $tokenRecord['refresh_id'];
            $this->authExpires = (int)$tokenRecord['auth_expires'];
            $this->domain = $tokenRecord['domain'];
        }
        $this->isAccessFromBitrix = $this->authId && $this->memberId && $this->domain;
    }

    public function beforeFilter(EventInterface $event)
    {
        parent::beforeFilter($event);
        if(!$this->isAccessFromBitrix)
        {
            return $this->redirect([
                '_name' => 'home',
            ]);
        }
        

        $this->loadComponent('Bx24');
        $this->Tickets = $this->getTableLocator()->get('Tickets');

        $logFile = Configure::read('AppConfig.LogsFilePath') . DS . 'tickets_rest.log';
        $this->TicketControllerLogger = new Logger('BitrixController');
        $this->TicketControllerLogger->pushHandler(new StreamHandler($logFile, Logger::DEBUG));
    }

    public function collectTickets()
    {
        if($this->request->is('ajax'))
        {
            $this->disableAutoRender();
            $this->viewBuilder()->disableAutoLayout();

            $current = $this->request->getQuery('current') ?? $this->request->getData('current') ?? 1;
            $rowCount = $this->request->getQuery('rowCount') ?? $this->request->getData('rowCount') ?? 10;
            $fromDate = $this->request->getQuery('from') ?? $this->request->getData('from');
            $toDate = $this->request->getQuery('to') ?? $this->request->getData('to');
            $searchPhrase = $this->request->getQuery('search') ?? $this->request->getData('search') ?? "";

            $tickets = $this->Tickets->getTicketsFor(
                $this->memberId,
                // Custom filter 
                [], 
                // Order of tickets
                ['created' => 'desc'],
                // Pagination: [page, count]
                [$current, $rowCount],
                // Date diapazone 
                $fromDate, 
                $toDate
            );
            $ticketActivityIDs = array_column($tickets, 'action_id');
            $extendInformation = $this->Bx24->getTicketAttributes($ticketActivityIDs);
            foreach($extendInformation as $i => $attributes)
            {
                if(!$attributes || ($searchPhrase && !mb_strstr($attributes['subject'], $searchPhrase)))
                {
                    unset($tickets['rows'][$i] );
                    $tickets['total'] = $tickets['total'] - 1;
                    continue;
                }
                $tickets['rows'][$i] = array_merge([
                    'id' => $attributes['id'],
                    'name' => $attributes['subject'],
                    'responsible' => $attributes['responsible'],
                    'client' => $attributes['customer'],
                ], $tickets['rows'][$i]->toArray());
                $this->BxControllerLogger->debug(__FUNCTION__ . ' - example', [
                    'row' => $tickets['rows'][$i],
                    'attributes' => $attributes,
                ]);
                return ['rows' => [], 'rowCount' => $rowCount, 'total' => 0, 'current' => $current];
            }
            $tickets['rows'] = array_slice($tickets['rows'], $rowCount);
            $tickets['rowCount'] = count($tickets['rows']);
            $this->TicketControllerLogger->debug('displaySettingsInterface - ' . __FUNCTION__ . ' - result', $tickets);
            return new Response(['body' => json_encode($tickets)]);
        }
    }
}