<?php
declare(strict_types=1);

namespace App\Controller;

use App\Controller\Component\Bx24Component;
use App\Model\Table\TicketStatusesTable;
use Cake\Core\Configure;
use Cake\Event\EventInterface;
use Cake\Http\Response;
use Cake\I18n\FrozenDate;
use Monolog\Handler\StreamHandler;
use Monolog\Logger;

class TicketController extends AppController
{
    private $Tickets;
    private $TicketStatuses;

    private $TicketControllerLogger;

    private $placement;

    public function initialize(): void
    {
        $auth = $this->request->getData('auth');
        $this->memberId = $auth && isset($auth['member_id']) ? $auth['member_id'] : $this->request->getQuery('member_id');
        $this->domain = $this->request->getQuery('DOMAIN') ?? "";

        if($auth)
        {
            $this->memberId = $auth['member_id'] ?? "";
            $this->authId = $auth['AUTH_ID'] ?? "";
            $this->refreshId = $auth['REFRESH_ID'] ?? "";
            $this->authExpires = $auth['AUTH_EXPIRES'] ?? "";
        } else {
            $this->memberId = $this->request->getData('member_id') ?? '';
            $this->authId = $this->request->getData('AUTH_ID') ?? '';
            $this->authExpires = $this->request->getData('AUTH_EXPIRES') ?? '';
            $this->refreshId = $this->request->getData('REFRESH_ID');
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
        $this->TicketStatuses = $this->getTableLocator()->get('TicketStatuses');

        $logFile = Configure::read('AppConfig.LogsFilePath') . DS . 'tickets_rest.log';
        $this->TicketControllerLogger = new Logger('TicketController');
        $this->TicketControllerLogger->pushHandler(new StreamHandler($logFile, Logger::DEBUG));

        $this->placement = json_decode($this->request->getData('PLACEMENT_OPTIONS') ?? "", true);
    }


    public function displayCrmInterface()
    {
        $this->TicketControllerLogger->debug(__FUNCTION__ . ' - started');

        $statuses = $this->TicketStatuses->getStatusesFor($this->memberId);
        $currentUser = $this->Bx24->getCurrentUser();
        $data = $this->request->getData();
        $entityId = $this->placement['ID'];
        $entityType = $data['PLACEMENT'];
        
        $isContact = ($entityType == '	CRM_CONTACT_DETAIL_ACTIVITY');
        $entity = $isContact 
            ? $this->Bx24->getCompany((int)$entityId) 
            : $this->Bx24->getContact((int)$entityId);
        $entity['TITLE'] = $this->Bx24->getEntityTitle($entity);
        $contacts = [];
        foreach(['PHONE', 'EMAIL'] as $contactType)
        {
            $all = !$isContact 
                ? $this->Bx24->getPersonalContacts($entity, $contactType) 
                : $this->Bx24->getCompanyContacts($entity, $contactType);
            $contacts = array_merge($contacts, $all);
            $entity[$contactType] = count($all) ? $all[0] : "";
        }
        $entity['WORK_COMPANY'] = $isContact ? "" : $entity['NAME'];

        $this->TicketControllerLogger->debug(__FUNCTION__ . ' - customer object', $entity);
        $customer = $this->Bx24->makeUserAttributes($entity);
        
        if(!empty($customer['PHONE']) && !empty($customer['PHONE']['VALUE']))
        {
            $customer['PHONE'] = $customer['PHONE']['VALUE'];
        }
        if(!empty($customer['EMAIL']) && !empty($customer['EMAIL']['VALUE']))
        {
            $customer['EMAIL'] = $customer['EMAIL']['VALUE'];
        }

        if($this->request->is('ajax') || isset($data['subject']))
        {
            $this->viewBuilder()->disableAutoLayout();
            $this->disableAutoRender();
            $this->TicketControllerLogger->debug(__FUNCTION__ . ' - ajax');

            $subject = $data['subject'];
            $text = $data['description'];
            $statusId = intval($data['status']);
            $status = $statuses[$statusId];
            $ticketId = $this->Tickets->getLatestID() + 1;
            $postfix = $this->Bx24->getTicketSubject($ticketId);

            // Create ticket activity
            $source = $this->Bx24->prepareNewActivitySource((int)$entityId, $subject, $text, (int)$currentUser['ID'], $contacts);
            $this->TicketControllerLogger->debug('displayCrmInterface - crm.activity.add - zero source', $source);
            $activityId = $this->Bx24->createTicketBy($source, $postfix);
            $result = [
                'status' => __('Ticket was not created'),
            ];
            if ($activityId) {
                $activity = $this->Bx24->getActivities([$activityId]);
                //$activity['PROVIDER_TYPE_ID'] = "USER_ACTIVITY";

                // Write into DB
                $ticketRecord = $this->Tickets->create(
                    $this->memberId, 
                    $activity, 
                    1, 
                    $status['id'],
                    0
                );
                $result = [
                    'status' => __('Ticket was created successful'), 
                    'ticket' => $activityId,
                ];
            }

            $this->TicketControllerLogger->debug(__FUNCTION__ . ' - finish', $result);
            return new Response([
                'body' => json_encode($result),
            ]);
        }

        $this->set('customer', $customer);
        $this->set('responsible', $this->Bx24->makeUserAttributes($currentUser));
        $this->set('statuses', $statuses);
        $this->set('statusId', $this->TicketStatuses->getFirstStatusForMemberTickets($this->memberId, TicketStatusesTable::MARK_STARTABLE)['id']);
        $this->set('ajax', $this->getUrlOf('crm_interface', $this->domain));
        $this->set('required', [
            'AUTH_ID' => $this->authId,
            'AUTH_EXPIRES' => $this->authExpires,
            'REFRESH_ID'=> $this->refreshId,
            'member_id' => $this->memberId,
            'PLACEMENT_OPTIONS' => json_encode($this->placement),
            'PLACEMENT' => $entityId,
        ]);

        $this->render('display_crm_interface');
    }

    public function collectTickets()
    {
        if($this->request->is('ajax') || !$this->request->getData('rowCount'))
        {
            $this->disableAutoRender();
            $this->viewBuilder()->disableAutoLayout();

            $current = (int)($this->request->getData('current') ?? 1);
            $rowCount = (int)($this->request->getData('rowCount'));
            $fromDate = $this->request->getData('from');
            $toDate = $this->request->getData('to');
            $searchPhrase = $this->request->getData('searchPhrase') ?? "";
            $period = $this->request->getData('period') ?? "month";

            $tickets = $this->Tickets->getTicketsFor(
                $this->memberId,
                // Custom filter 
                [], 
                // Order of tickets
                ['created' => 'desc'],
                // Pagination: [page, count]
                [$current, $rowCount],
                // Date diapazone 
                $period,
                $fromDate, 
                $toDate
            );
            
            $total = intval($tickets['total']);
            $ticketActivityIDs = array_column($tickets['rows'], 'action_id');
            $this->TicketControllerLogger->debug(__FUNCTION__ . ' - ticket activities', [
                'id' => $ticketActivityIDs,
                'total' => $total,
                'rows' => count($tickets['rows']),
            ]);
            $ticketIds = count($ticketActivityIDs) > 0 ? range(0, count($ticketActivityIDs)-1) : [];
            $ticketMap = array_combine($ticketActivityIDs, $ticketIds);

            $extendInformation = $this->Bx24->getTicketAttributes($ticketActivityIDs);
            $total = count($extendInformation);
            $result = [
                'total' => $tickets['total'],
                'rowCount' => $rowCount,
                'current' => $current,
                'rows' => []
            ];
            $idsOfActivityWhatIsNotFound = [];
            foreach($extendInformation as $id => $attributes)
            {
                if(
                    !$attributes 
                    || !isset($ticketMap[$id]) 
                    || ($searchPhrase && !mb_strstr($attributes['subject'], $searchPhrase))
                )
                {
                    $idsOfActivityWhatIsNotFound[] = $id;
                    $total--;
                    continue;
                }
                $ticketNo = $ticketMap[$id];
                unset($ticketMap[$id]); // One activity for one ticket
                $ticket = $tickets['rows'][$ticketNo];
                $result['rows'][] = [
                    'id' => $ticket['id'],
                    'name' => $attributes['subject'],
                    'responsible' => $attributes['responsible'] ?? [],
                    'status_id' => $ticket->status_id,
                    'client' => $attributes['customer'] ?? [],
                    'created' => (new FrozenDate($attributes['date']))->format(Bx24Component::DATE_TIME_FORMAT),
                ];
            }
            $this->TicketControllerLogger->debug(__FUNCTION__ . ' - activities not found', [
                'id' => $idsOfActivityWhatIsNotFound
            ]);

            $result['total'] = count($result['rows']);
            if ($rowCount > 0) {
                $result['rows'] = array_slice($result['rows'], ($current - 1)*$rowCount, $rowCount);
            }
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

    public function getSummary()
    {
        if($this->request->is('ajax') || !$this->request->getData('rowCount'))
        {
            $this->disableAutoRender();
            $this->viewBuilder()->disableAutoLayout();

            $current = (int)($this->request->getData('current') ?? 1);
            $rowCount = (int)($this->request->getData('rowCount'));
            $fromDate = $this->request->getData('from');
            $toDate = $this->request->getData('to');
            $period = $this->request->getData('period') ?? "month";

            $tickets = $this->Tickets->getTicketsFor(
                $this->memberId,
                // Custom filter 
                [], 
                // Order of tickets
                ['created' => 'desc'],
                // Pagination: [page, count]
                [$current, $rowCount],
                // Date diapazone 
                $period,
                $fromDate, 
                $toDate
            );
            
            $total = intval($tickets['total']);
            $ticketActivityIDs = array_column($tickets['rows'], 'action_id');
            $this->TicketControllerLogger->debug(__FUNCTION__ . ' - ticket activities', [
                'id' => $ticketActivityIDs,
                'total' => $total,
                'rows' => count($tickets['rows']),
            ]);
            $ticketIds = count($ticketActivityIDs) > 0 ? range(0, count($ticketActivityIDs)-1) : [];
            $ticketMap = array_combine($ticketActivityIDs, $ticketIds);

            $extendInformation = $this->Bx24->getTicketAttributes($ticketActivityIDs);
            $total = count($extendInformation);
            $result = [
                'total' => $tickets['total'],
            ];
            $rows = [];
            $idsOfActivityWhatIsNotFound = [];
            foreach($extendInformation as $id => $attributes)
            {
                if(
                    !$attributes 
                    || !isset($ticketMap[$id])
                )
                {
                    $idsOfActivityWhatIsNotFound[] = $id;
                    $total--;
                    continue;
                }
                $ticketNo = $ticketMap[$id];
                unset($ticketMap[$id]); // One activity for one ticket
                $ticket = $tickets['rows'][$ticketNo];
                $rows[] = [
                    'id' => $ticket['id'],
                    'name' => $attributes['subject'],
                    'responsible' => $attributes['responsible'] ?? [],
                    'customer' => $attributes['customer'] ?? [],
                    'status_id' => $ticket->status_id,
                    'client' => $attributes['customer'] ?? [],
                    'created' => (new FrozenDate($attributes['date']))->format(Bx24Component::DATE_TIME_FORMAT),
                ];
            }
            $this->TicketControllerLogger->debug(__FUNCTION__ . ' - activities not found', [
                'id' => $idsOfActivityWhatIsNotFound
            ]);

            $statuses = $this->TicketStatuses->getStatusesFor($this->memberId);
            $summary = [];
            $indicators = $this->Tickets->calcIndicatorsForTickets($rows);
            foreach($indicators as $index => $value)
            {
                $summary[$statuses[$index]->name] = $value;
            }

            $result['total'] = count($rows);
            $result = array_merge($result, ['summary' => $summary], $this->calcTeamsSummary($rows, $statuses));
            unset($rows);
            return new Response(['body' => json_encode($result)]);
        }
    }

    private function calcTeamsSummary(array $rows, array $statuses) : array
    {
        $this->TicketControllerLogger->debug('getSummary - ' . __FUNCTION__ . '- arguments', [
            'rows' => $rows,
        ]);
        if (count($rows) == 0)
        {
            return [
                'perAgent' => [],
                'perTeam' => [],
                'teams' => [],
                'expose' => []
            ];
        }
        $summary = [];

        $agents = [];
        $perUser = [];
        $departments = [];
        $perClient = [];
        $customers = [];

        // Select user IDs
        foreach($rows as $row)
        {
            $user = $row['responsible']; 
            $uid = $user['id'];
            $agents[$uid] = $user;
            $customers[] = $row['customer']['id'];
        }
        
        // user.get
        $users = $this->Bx24->getUserById(array_keys($agents));
        if(!$users){
            return null;
        }
        
        // Get departments and make maps
        foreach($users as $user)
        {
            $uid = intval($user['ID']);
            $userIn = $user['UF_DEPARTMENT'] ?? [];
            foreach($userIn as $teamID)
            {
                $departments[$teamID][] = $uid;
            }
            $perUser[$uid] = [];
        }
        $departmentInformation = $this->Bx24->getDepartmentsByIds(array_keys($departments));

        // Cals statistics
        foreach($rows as $row)
        {
            $uid = $row['responsible']['id'];
            $idStatus = $row['status_id'];
            if (!isset($perUser[$uid][$idStatus])) {
                $perUser[$uid][$idStatus] = 0;
            }
            if (!isset($perUser[$uid]['total'])) {
                $perUser[$uid]['total'] = 0;
            }
            $perUser[$uid][$idStatus]++;
            $perUser[$uid]['total']++;

            $client = $row['customer']['title'];
            if(!isset($perClient[$client]))
            {
                $perClient[$client]['total'] = 0;
            }
            $perClient[$client]['total']++;
        }

        // Make departments statistic
        $perTeam = [];
        foreach($departments as $idDepartment => $persons)
        {
            $perTeam[$idDepartment]['total'] = 0;
            foreach (array_keys($statuses) as $statusId)
            {
                $sum = array_sum(
                    array_map(
                        function ($uid) use ($perUser, $statusId) {
                            $this->TicketControllerLogger->debug(__FUNCTION__ . ' - calc team statistics', [
                                'user' => $perUser[$uid],
                                'status' => $statusId
                            ]);
                            return isset($perUser[$uid][$statusId]) ? $perUser[$uid][$statusId] : 0;
                        }, 
                        $persons
                    )
                );
                $perTeam[$idDepartment][$statuses[$statusId]['name']] = $sum;
                $perTeam[$idDepartment]['total'] += $sum;
            }
        }

        // Make displayable datasets
        foreach($agents as $uid => $agent)
        {
            $perStatus = [];
            foreach($perUser[$uid] as $statusId => $amount)
            {
                if($statusId == 'total')
                {
                    $perStatus[$statusId] = $amount;
                } else {
                    $perStatus[$statuses[$statusId]['name']] = $amount;
                }
            }
            $perUser[$agent['title']] = $perStatus;
        }
        foreach($departmentInformation as $department)
        {
            $departments[$department['NAME']] = array_map(
                function ($uid) use ($agents) { 
                    return $agents[$uid]['title'];
                }, 
                $departments[$department['ID']]
            );
            $perTeam[$department['NAME']] = $perTeam[$department['ID']];
        }

        // Combine maps into result object
        $summary['perAgent'] = $this->leftNumericKeys($perUser);
        $summary['perTeam'] = $this->leftNumericKeys($perTeam);
        $summary['teams'] = $this->leftNumericKeys($departments);
        $summary['perCustomer'] = $perClient;
        $summary['expose'] = array_reduce(array_keys($summary['teams']), function(array $carry, $teamName) {
            $carry['team'][$teamName] = true;
            $carry['sla'][$teamName] = true;
            return $carry;
        }, []);
        $this->TicketControllerLogger->debug('summaryTickets - calcTeamSummary - result', $summary);
        return $summary;
    }

    private function leftNumericKeys(array $map) : array
    {
        $result = [];

        foreach($map as $key => $value)
        {
            if(intval($key) === 0)
            {
                $result[$key] = $value;
            } 
        }

        return $result;
    }
}