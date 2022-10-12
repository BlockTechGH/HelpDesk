<?php
declare(strict_types=1);

namespace App\Controller;

use App\Controller\Component\Bx24Component;
use Cake\Core\Configure;
use Cake\Event\EventInterface;
use Cake\Http\Response;
use Cake\I18n\FrozenDate;
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
        $this->memberId = $auth && isset($auth['member_id']) ? $auth['member_id'] : $this->request->getQuery('member_id');
        $this->domain = $auth && isset($auth['DOMAIN']) ? $auth['DOMAIN'] : $this->request->getQuery('DOMAIN');

        if($auth)
        {
            $this->isAccessFromBitrix = true;
            $this->memberId = $auth['member_id'] ?? "";
            $this->authId = $auth['access_token'] ?? "";
            $this->refreshId = $auth['refresh_token'] ?? "";
            $this->authExpires = $auth['expires_in'] ?? "";
            $this->domain = isset($auth['domain']) ? $auth['domain'] : $this->domain;
        } else {
            $this->authId = $this->request->getData('AUTH_ID') ?? '';
            $this->refreshId = $this->request->getData('REFRESH_ID') ?? '';
            $this->authExpires = (int)($this->request->getData('AUTH_EXPIRES')) ?? '';
        }
        if($this->memberId && !($this->refreshId && $this->authId && $this->authExpires))
        {
            $this->BitrixTokens = $this->getTableLocator()->get('BitrixTokens');
            $tokenRecord = $this->BitrixTokens->getTokenObjectByMemberId($this->memberId)->toArray();
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
        $this->TicketControllerLogger = new Logger('TicketController');
        $this->TicketControllerLogger->pushHandler(new StreamHandler($logFile, Logger::DEBUG));
    }

    public function collectTickets()
    {
        if($this->request->is('ajax'))
        {
            $this->disableAutoRender();
            $this->viewBuilder()->disableAutoLayout();

            $current = (int)($this->request->getQuery('current') ?? $this->request->getData('current') ?? 1);
            $rowCount = (int)($this->request->getQuery('rowCount') ?? $this->request->getData('rowCount') ?? 10);
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
            $ticketActivityIDs = array_column($tickets['rows'], 'action_id');
            $this->TicketControllerLogger->debug(__FUNCTION__ . ' - ticket activities', ['id' => $ticketActivityIDs]);
            $ticketIds = range(0, count($ticketActivityIDs)-1);
            $ticketMap = array_combine($ticketActivityIDs, $ticketIds);

            $extendInformation = $this->Bx24->getTicketAttributes($ticketActivityIDs);
            $result = [
                'total' => $tickets['total'],
                'rowCount' => $rowCount,
                'current' => $current,
                'rows' => []
            ];
            foreach($extendInformation as $id => $attributes)
            {
                if(!$attributes || ($searchPhrase && !mb_strstr($attributes['subject'], $searchPhrase)))
                {
                    $this->TicketControllerLogger->debug(__FUNCTION__ . ' - activity not found', [
                        'id' => $id
                    ]);
                    $result['total'] = $result['total'] - 1;
                    continue;
                }
                $ticketNo = $ticketMap[$id];
                $ticket = $tickets['rows'][$ticketNo];
                $result['rows'][] = [
                    'id' => $attributes['id'],
                    'name' => $attributes['subject'],
                    'responsible' => $attributes['responsible'] ?? [],
                    'status_id' => $ticket->status_id,
                    'client' => $attributes['customer'] ?? [],
                    'created' => (new FrozenDate($attributes['date']))->format(Bx24Component::DATE_TIME_FORMAT),
                ];
            }
            $result['rows'] = array_slice($result['rows'], $rowCount);
            $tickets['rowCount'] = count($tickets['rows']);
            $this->TicketControllerLogger->debug('displaySettingsInterface - ' . __FUNCTION__ , [
                'parameters' => $this->request->getParsedBody(),
                'result.count' => count($result['rows'])
            ]);
            return new Response(['body' => json_encode($result)]);
        }
    }
}