<?php
declare(strict_types=1);

namespace App\Controller;

use App\Controller\Component\Bx24Component;
use Cake\Cache\Cache;
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

        // FOR DEV/TEST
        Cache::setConfig('short', [
            'className' => 'File',
            'duration' => '+ 1 hour',
            'path' => CACHE,
            'prefix' => 'tickets_'
        ]);
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

            $tickets = Cache::read("{$this->memberId}_tickets_{$current}_{$rowCount}_{$fromDate}_{$toDate}_{$searchPhrase}", 'short');
            if ($tickets == null) {
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
                Cache::write("{$this->memberId}_tickets_{$current}_{$rowCount}_{$fromDate}_{$toDate}_{$searchPhrase}", $tickets, 'short');
            }
            
            $total = intval($tickets['total']);
            $ticketActivityIDs = array_column($tickets['rows'], 'action_id');
            $this->TicketControllerLogger->debug(__FUNCTION__ . ' - ticket activities', [
                'id' => $ticketActivityIDs,
                'total' => $total,
                'rows' => count($tickets['rows']),
            ]);
            $ticketIds = range(0, count($ticketActivityIDs)-1);
            $ticketMap = array_combine($ticketActivityIDs, $ticketIds);

            $extendInformation = Cache::read("{$this->memberId}_activities_{$current}_{$rowCount}_{$fromDate}_{$toDate}_{$searchPhrase}", 'short');
            if (!$extendInformation) {
                $extendInformation = $this->Bx24->getTicketAttributes($ticketActivityIDs);
                Cache::write("{$this->memberId}_activities_{$current}_{$rowCount}_{$fromDate}_{$toDate}_{$searchPhrase}", $extendInformation, 'short');
            }
            $total = count($extendInformation);
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
                    $total--;
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
            $result['rows'] = array_slice($result['rows'], ($current - 1)*$rowCount, $rowCount);
            $result['rowCount'] = count($result['rows']);
            $result['total'] = $total;
            $this->TicketControllerLogger->debug('displaySettingsInterface - ' . __FUNCTION__ , [
                'parameters' => $this->request->getParsedBody(),
                'result.page.size' => count($result['rows']),
                'result.total' => $result['total'],
            ]);
            return new Response(['body' => json_encode($result)]);
        }
    }

    public function clearCache()
    {
        $this->disableAutoRender();
        $this->viewBuilder()->disableAutoLayout();
        if($this->request->is('ajax'))
        {
            Cache::clear('short');
        }
    }
}