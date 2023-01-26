<?php
declare(strict_types=1);

namespace App\Controller;

use App\Controller\Component\Bx24Component;
use App\Model\Table\TicketStatusesTable;
use Cake\Core\Configure;
use Cake\Event\Event;
use Cake\Event\EventInterface;
use Cake\Http\Response;
use Cake\I18n\FrozenDate;
use Cake\I18n\FrozenTime;
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

        $logFile = Configure::read('AppConfig.LogsFilePath') . DS . 'tickets_controller.log';
        $this->TicketControllerLogger = new Logger('TicketController');
        $this->TicketControllerLogger->pushHandler(new StreamHandler($logFile, Logger::DEBUG));

        $this->placementOptions = $this->request->getData('PLACEMENT_OPTIONS') ?? "";
        $this->placement = json_decode($this->placementOptions, true);
        $this->place = $this->request->getData('PLACEMENT');

        // subscribe on events
        $eventManager = $this->getEventManager();
        $eventManager->on('Ticket.created', [$this, 'handleTicketCreated']);
        $eventManager->on('Ticket.changeResponsible', [$this, 'handleTicketChangeResponsible']);
    }


    public function displayCrmInterface()
    {
        $this->TicketControllerLogger->debug(__FUNCTION__ . ' - started');

        $statuses = $this->TicketStatuses->getStatusesFor($this->memberId);
        $currentUser = $this->Bx24->getCurrentUser();
        $data = $this->request->getData();
        $entityId = intval($this->placement['ID']);
        $placementType = $data['PLACEMENT'];
        $contactTypes = ['PHONE', 'EMAIL'];
        switch($placementType)
        {
            case 'CRM_CONTACT_DETAIL_ACTIVITY':
                $entityType = 'CRM_CONTACT';
                break;
            case 'CRM_COMPANY_DETAIL_ACTIVITY':
                $entityType = 'CRM_COMPANY';
                break;
            case 'CRM_DEAL_DETAIL_ACTIVITY':
                $entityType = 'CRM_DEAL';
                break;
            default:
                $entityType = null;
                break;
        }

        if($entityType == 'CRM_CONTACT')
        {
            $contact = $this->Bx24->getContact($entityId);
            $contacts = [];
            foreach($contactTypes as $contactType)
            {
                $all = $this->Bx24->getPersonalContacts($contact, $contactType);
                $contacts = array_merge($contacts, $all);
                $contact[$contactType] = count($all) ? $all[0] : "";
            }
            $contact['WORK_COMPANY'] = "";
            $customer = $this->Bx24->makeUserAttributes($contact);
        }

        if($entityType == 'CRM_COMPANY')
        {
            $company = $this->Bx24->getCompanyInfo($entityId);
            $contacts = [];
            foreach($contactTypes as $contactType)
            {
                $all = $this->Bx24->getCompanyContactsInfo($company, $contactType);
                $contacts = array_merge($contacts, $all);
                $company[$contactType] = count($all) ? $all[0] : "";
            }
            $customer = $this->Bx24->makeCompanyAttributes($company);
        }
        
        if($entityType == 'CRM_DEAL')
        {
            $dealData = $this->Bx24->getDealData($entityId);
            $deal = $dealData['deal'];
            $result = $this->Bx24->getDealCommunicationInfo($dealData, $contactTypes);
            $contacts = [];
            foreach($result as $type => $values)
            {
                $deal[$type] = isset($values[0])? $values[0]: "";
                $contacts = array_merge($contacts, $values);
            }
            $customer = $this->Bx24->makeCompanyAttributes($deal);
        }
        $this->TicketControllerLogger->debug(__FUNCTION__ . ' - customer', $customer);
        $this->TicketControllerLogger->debug(__FUNCTION__ . ' - contacts', $contacts);

        // why is this necessary?
        // $entity['TITLE'] = $this->Bx24->getEntityTitle($entity);
        
        if(!empty($customer['phone']) && !empty($customer['phone']['VALUE']))
        {
            $customer['phone'] = $customer['phone']['VALUE'];
        }
        if(!empty($customer['email']) && !empty($customer['email']['VALUE']))
        {
            $customer['email'] = $customer['email']['VALUE'];
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
            $responsibleId = $data['responsible'] ?? $currentUser['ID'];
            $ticketId = $this->Tickets->getLatestID() + 1;
            $postfix = $this->Bx24->getTicketSubject($ticketId);

            // Create ticket activity
            $source = $this->Bx24->prepareNewActivitySource($entityId, $entityType, $subject, $text, (int)$responsibleId, $contacts);
            $this->TicketControllerLogger->debug('displayCrmInterface - crm.activity.add - zero source', $source);

            if(!$source['COMMUNICATIONS'])
            {
                $this->TicketControllerLogger->debug(__FUNCTION__ . ' - Error', ['no communication filled']);
                return new Response([
                    'body' => json_encode([
                        'status' => __('Error creating ticket. Please fill in the email or phone number of the contact'),
                        'error' => true
                    ]),
                ]);
            }

            $activityId = $this->Bx24->createTicketBy($source, $postfix);
            $result = [
                'status' => __('Ticket was not created'),
            ];
            if ($activityId) {
                $activity = $this->Bx24->getActivities([$activityId]);

                // Write into DB
                $ticketRecord = $this->Tickets->create(
                    $this->memberId, 
                    $activity[$activityId], 
                    1, 
                    $status['id'],
                    0
                );
                $result = [
                    'status' => __('Ticket was created successful'), 
                    'ticket' => $activityId,
                ];

                // send event Ticket Created
                $event = new Event('Ticket.created', $this, [
                    'ticket' => $ticketRecord,
                    'status' => $status->name,
                    'ticketAttributes' => $this->Bx24->getOneTicketAttributes($activity[$activityId])
                ]);
                $this->getEventManager()->dispatch($event);
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
            'PLACEMENT' => $data['PLACEMENT'],
        ]);

        $this->render('display_crm_interface');
    }

    public function displayCrmEntityTicketsInterface()
    {
        $this->TicketControllerLogger->debug(__FUNCTION__ . ' - started');
        $statuses = $this->TicketStatuses->getStatusesFor($this->memberId);
        $this->set('statuses', $statuses);
        $this->set('domain', $this->domain);
        $this->set('placementOptions', $this->placementOptions);
        $this->set('place', $this->place);

        if($this->request->is('ajax'))
        {
            $this->TicketControllerLogger->debug(__FUNCTION__ . ' - ajax');
            $this->disableAutoRender();
            $this->viewBuilder()->disableAutoLayout();

            $data = $this->request->getData();
            $currentPage = intval($this->request->getData('current'));
            $rowCount = intval($this->request->getData('rowCount'));
            $order = $this->request->getData('sort');
            $entityId = intval($this->placement['ID']);
            $placementType = $data['PLACEMENT'];

            $this->TicketControllerLogger->debug(__FUNCTION__ . ' - params', [
                'entityId' => $entityId,
                'data' => $data
            ]);

            switch($placementType)
            {
                case 'CRM_CONTACT_DETAIL_TAB':
                    $entityData = $this->Bx24->getContactInfo($entityId);
                    $entityType = 'CRM_CONTACT';
                    break;
                case 'CRM_COMPANY_DETAIL_TAB':
                    $entityData = $this->Bx24->getCompanyInfo($entityId);
                    $entityType = 'CRM_COMPANY';
                    break;
                case 'CRM_DEAL_DETAIL_TAB':
                    $entityData = $this->Bx24->getDeal($entityId);
                    $entityType = 'CRM_DEAL';
                    break;
            }

            $this->TicketControllerLogger->debug(__FUNCTION__ . ' - entityData', [
                'entityType' => $entityType,
                'entityData' => $entityData
            ]);

            $entityTypeId = $this->Bx24::CRM_ENTITY_TYPES_IDS[$entityType];

            $arOurTypeActivityData = $this->Bx24->getActivityTypeAndName();
            $additionalFilter = [
                'PROVIDER_TYPE_ID' => $arOurTypeActivityData['TYPE_ID']
            ];
            $start = ($currentPage - 1) * $rowCount;

            $result = $this->Bx24->getActivitiesByOwnerIdAndOwnerTypeId($entityId, $entityTypeId, $order, $start, $additionalFilter);
            $activities = $result['activities'];
            $total = $result['total'];

            $this->TicketControllerLogger->debug(__FUNCTION__ . ' - activities', [
                'activities' => $activities
            ]);

            $tickets = [];

            if($activities)
            {
                $responsibleIds = array_unique(array_map(function($activity) {
                    return $activity['RESPONSIBLE_ID'];
                }, array_values($activities)));

                $responsible = $this->Bx24->getUserById($responsibleIds);

                $this->TicketControllerLogger->debug(__FUNCTION__ . ' - users', [
                    'responsibleIds' => $responsibleIds,
                    'responsible' => $responsible
                ]);

                foreach($responsible as $id => $user)
                {
                    $responsible[$user['ID']] = $user;
                    unset($responsible[$id]);
                }

                $activitiesIds = array_unique(array_map(function($activity) {
                    return $activity['ID'];
                }, array_values($activities)));

                $tickets = $this->Tickets->getByActivityIds($activitiesIds, $order);
            }

            if(!$tickets)
            {
                $this->TicketControllerLogger->debug(__FUNCTION__ . ' - no tickets found');

                $result = [
                    'total' => 0,
                    'rowCount' => 0,
                    'current' => 1,
                    'rows' => []
                ];
                return new Response(['body' => json_encode($result)]);
            }

            foreach($tickets as $ticket)
            {
                $responsibleId = $activities[$ticket->action_id]['RESPONSIBLE_ID'];
                $responsibleName = implode(' ', [$responsible[$responsibleId]['NAME'], $responsible[$responsibleId]['LAST_NAME']]);

                $result['rows'][] = [
                    'id' => $ticket['id'],
                    'name' => $activities[$ticket->action_id]['SUBJECT'],
                    'responsible' => $responsibleName,
                    'status' => $statuses[$ticket->status_id]['name'],
                    'client' => $entityData['TITLE'] ?? implode(' ', [$entityData['NAME'], $entityData['LAST_NAME']]),
                    'created' => (new  FrozenTime($activities[$ticket->action_id]['CREATED']))->format(Bx24Component::DATE_TIME_FORMAT),
                ];
            }

            $result = [
                'total' => $total,
                'current' => $currentPage,
                'rowCount' => $rowCount,
                'rows' => $result['rows']
            ];

            $this->TicketControllerLogger->debug(__FUNCTION__ . ' - data', [
                'statuses' => $statuses,
                'data' => $data,
                'entityId' => $entityId,
                'entityTypeId' => $entityTypeId,
                'entityData' => $entityData,
                'activities' => $activities,
                'responsibleIds' => $responsibleIds,
                'responsible' => $responsible,
                'activitiesIds' => $activitiesIds,
                'tickets' => $tickets,
                'result' => $result
            ]);

            return new Response(['body' => json_encode($result)]);
        }
    }

    public function collectTickets()
    {
        if($this->request->is('ajax') || !$this->request->getData('rowCount'))
        {
            $this->disableAutoRender();
            $this->viewBuilder()->disableAutoLayout();

            $currentPage = (int)($this->request->getData('current') ?? 1);
            $rowCount = (int)($this->request->getData('rowCount'));
            $fromDate = $this->request->getData('from');
            $toDate = $this->request->getData('to');
            $searchPhrase = $this->request->getData('searchPhrase') ?? "";
            $period = $this->request->getData('period') ?? "month";
            $order = $this->request->getData('sort');
            $limitCount = $this->Bx24::BITRIX_REST_API_RESULT_LIMIT_COUNT;
            $arOurTypeActivityData = $this->Bx24->getActivityTypeAndName();

            $this->TicketControllerLogger->debug(__FUNCTION__ . ' - input filter params', [
                'period' => $period,
                'fromDate' => $fromDate,
                'toDate' => $toDate,
                'searchPhrase' => $searchPhrase
            ]);

            $filter = [
                'PROVIDER_TYPE_ID' => $arOurTypeActivityData['TYPE_ID'],
                'PROVIDER_ID' => 'REST_APP'
            ];
            if ($fromDate) {
                if ($period == $this->Tickets::PERIOD_MONTH)
                {
                    $fromDate = FrozenDate::createFromFormat('m/Y', $fromDate)->firstOfMonth();
                    $filter['<=CREATED'] = $fromDate->modify('+ 1 month')->i18nFormat('yyyy-MM-dd HH:mm:ss');
                }
                elseif($period == $this->Tickets::PERIOD_DAY)
                {
                    $fromDate = FrozenDate::createFromFormat('m/d/Y', $fromDate);
                    $filter['<=CREATED'] = $fromDate->modify('+1 day')->i18nFormat('yyyy-MM-dd HH:mm:ss');
                }
                else
                {
                    $fromDate = FrozenDate::createFromFormat('m/d/Y', $fromDate);
                }
                $filter['>=CREATED'] = $fromDate->i18nFormat('yyyy-MM-dd HH:mm:ss');
            }
            if ($toDate && $period == $this->Tickets::PERIOD_BETWEEN) {
                $parts = explode('/', $toDate);
                if(count($parts) == 2)
                {
                    $toDate = implode("/", [$parts[0], "01", $parts[1]]);
                }
                $toDate = FrozenDate::createFromFormat('m/d/Y', $toDate)
                    ->modify('+ 1 day')
                    ->i18nFormat('yyyy-MM-dd HH:mm:ss');
                $filter['<=CREATED'] = $toDate;
            }
            if($searchPhrase)
            {
                $filter['%SUBJECT'] = $searchPhrase;
            }

            $this->TicketControllerLogger->debug(__FUNCTION__ . ' - filter params', [
                'filter' => $filter,
                'order' => $order
            ]);

            $sliceStart = ($currentPage - 1) * $rowCount;
            $paginationStart = intval(floor($sliceStart / $limitCount)) * $limitCount;
            if($sliceStart >= $paginationStart)
            {
                $sliceStart = $sliceStart - $paginationStart;
            }

            $result = $this->Bx24->getActivitiesByFilterWithPagination($filter, $order, $paginationStart);
            $activities = $result['activities'];
            $total = $result['total'];

            $this->TicketControllerLogger->debug(__FUNCTION__ . ' - activities', [
                '$activities' => $activities
            ]);

            $tickets = [];

            if($activities)
            {
                $activities = array_slice($activities, $sliceStart, $rowCount, true);

                $responsibleIds = array_unique(array_map(function($activity) {
                    return $activity['RESPONSIBLE_ID'];
                }, array_values($activities)));

                $arRowResponsible = $this->Bx24->getUserById($responsibleIds);
                $responsible = [];
                foreach($arRowResponsible as $id => $user)
                {
                    $responsible[$user['ID']] = $user;
                    unset($arRowResponsible[$id]);
                }

                $this->TicketControllerLogger->debug(__FUNCTION__ . ' - users', [
                    'responsibleIds' => $responsibleIds,
                    'responsible' => $responsible
                ]);

                $activitiesIds = array_unique(array_map(function($activity) {
                    return $activity['ID'];
                }, array_values($activities)));

                $tickets = $this->Tickets->getByActivityIds($activitiesIds, $order);
            }

            if(!$tickets)
            {
                $this->TicketControllerLogger->debug(__FUNCTION__ . ' - no tickets found');

                $result = [
                    'total' => 0,
                    'rowCount' => 0,
                    'current' => 1,
                    'rows' => []
                ];
                return new Response(['body' => json_encode($result)]);
            }

            foreach($tickets as $ticket)
            {
                $responsibleId = $activities[$ticket->action_id]['RESPONSIBLE_ID'];
                $responsibleName = implode(' ', [$responsible[$responsibleId]['NAME'], $responsible[$responsibleId]['LAST_NAME']]);
                $client = $this->Bx24->getOneTicketAttributes($activities[$ticket->action_id]);

                $result['rows'][] = [
                    'id' => $ticket['id'],
                    'name' => $activities[$ticket->action_id]['SUBJECT'],
                    'responsible' => $responsibleName,
                    'status_id' => $ticket->status_id,
                    'client' => $client['customer'] ?? [],
                    'created' => (new  FrozenTime($activities[$ticket->action_id]['CREATED']))->format(Bx24Component::DATE_TIME_FORMAT),
                ];
            }

            $result = [
                'total' => $total,
                'current' => $currentPage,
                'rowCount' => $rowCount,
                'rows' => $result['rows']
            ];

            $this->TicketControllerLogger->debug(__FUNCTION__ . ' - result', [
                'result' => $result
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
            $arDepartments = $this->request->getData('arDepartments');

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
            if(!$extendInformation)
            {
                $result = [
                    'total' => '',
                    'rowCount' => '',
                    'current' => 1,
                    'rows' => []
                ];
                return new Response(['body' => json_encode($result)]);
            }

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
                    'created' => (new FrozenTime($attributes['date']))->format(Bx24Component::DATE_TIME_FORMAT),
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
            $result = array_merge($result, ['summary' => $summary], $this->calcTeamsSummary($rows, $statuses, $arDepartments));
            unset($rows);
            return new Response(['body' => json_encode($result)]);
        }
    }

    public function onChangeResponsible()
    {
        $this->viewBuilder()->disableAutoLayout();
        $this->disableAutoRender();

        $activityId = intval($this->request->getData('activityId'));
        $responsibleData = $this->request->getData('newResponsible');

        $this->TicketControllerLogger->debug(__FUNCTION__ . ' - input parameters', [
            'isPost' => $this->request->is('post'),
            'activityId' => $activityId,
            'responsibleData' => $responsibleData,
        ]);

        if($this->request->is('post') && $activityId && $responsibleData)
        {
            $ticketRecord = $this->Tickets->getByActivityIdAndMemberId($activityId, $this->memberId);

            $statuses = $this->TicketStatuses->getStatusesFor($this->memberId);
            $status = $statuses[$ticketRecord['status_id']];
            $activityInfo = $this->Bx24->getActivityAndRelatedDataById($activityId);
            $activity = $activityInfo['activity'];

            $this->TicketControllerLogger->debug(__FUNCTION__ . ' - collected data', [
                'status' => $status,
                'activityInfo' => $activityInfo
            ]);

            // send event Ticket Responsible Change
            $event = new Event('Ticket.changeResponsible', $this, [
                'ticket' => $ticketRecord,
                'status' => $status->name,
                'ticketAttributes' => $this->Bx24->getOneTicketAttributes($activity),
                'newResponsible' => $responsibleData['id']
            ]);
            $this->getEventManager()->dispatch($event);

            $result = [
                'error' => false,
                'status' => 'OK'
            ];
        } else {
            $result = [
                'status' => __('Bad request'),
                'error' => true
            ];
        }

        $body = json_encode($result);

        return new Response(['body' => $body]);
    }

    private function calcTeamsSummary(array $rows, array $statuses, array $arDepartments) : array
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
        
        if(!$agents){
            return null;
        }
        
        // Get departments and make maps
        foreach($agents as $id => $user)
        {
            $userIn = $user['department'] ?? [];
            foreach($userIn as $teamID)
            {
                $departments[$teamID][] = $id;
            }
            $perUser[$id] = [];
        }
        $departmentInformation = array_intersect_key($arDepartments, $departments);

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

    public function handleTicketChangeResponsible($event, $ticket, $status, $ticketAttributes, $newResponsible)
    {
        $this->TicketControllerLogger->debug(__FUNCTION__ . ' - input data', [
            'ticket' => $ticket,
            'status' => $status,
            'memberId' => $this->memberId,
            'ticketAttributes' => $ticketAttributes,
            'newResponsible' => $newResponsible
        ]);

        // we need collect necessary data and the run bp
        $arTemplateParameters = [
            'eventType' => 'notificationChangeResponsible',
            'ticketStatus' => $status,
            'ticketNumber' => $this->Bx24::TICKET_PREFIX . $ticket['id'],
            'ticketSubject' => $ticketAttributes['subject'],
            'ticketResponsibleId' => 'user_' . $newResponsible,
            'answerType' => '',
            'sourceType' => $ticket['source_type_id']
        ];

        $this->TicketControllerLogger->debug(__FUNCTION__ . ' - workflow parameters', [
            'arTemplateParameters' => $arTemplateParameters
        ]);

        $this->Options = $this->getTableLocator()->get('HelpdeskOptions');
        $entityTypeId = intval($ticketAttributes['ENTITY_TYPE_ID']);
        $arOption = $this->Options->getOption('notificationChangeResponsible' . Bx24Component::MAP_ENTITIES[$entityTypeId], $this->memberId);
        $templateId = intval($arOption['value']);

        $this->TicketControllerLogger->debug(__FUNCTION__ . ' - template', [
            'templateId' => $templateId
        ]);

        switch($entityTypeId)
        {
            case Bx24Component::OWNER_TYPE_DEAL:
                $entityId = intval($ticketAttributes['customer']['id']);
                break;

            case Bx24Component::OWNER_TYPE_CONTACT:
                $entityId = intval($ticketAttributes['customer']['id']);
                break;

            case Bx24Component::OWNER_TYPE_COMPANY:
                $entityId = intval($ticketAttributes['customer']['id']);
                break;

            default:
                $entityId = 0;
        }

        $this->TicketControllerLogger->debug(__FUNCTION__ . ' - entity', [
            'entityId' => $entityId,
            'entityType' => Bx24Component::MAP_ENTITIES[$entityTypeId]
        ]);

        if($templateId && $entityId)
        {
            $arResultStartWorkflow = $this->Bx24->startWorkflowFor($templateId, $entityId, $entityTypeId, $arTemplateParameters);
            $this->TicketControllerLogger->debug(__FUNCTION__ . ' - result', [
                'arResultStartWorkflow' => $arResultStartWorkflow
            ]);
        } else {
            $this->TicketControllerLogger->debug(__FUNCTION__ . ' - Missing required data to start workflow');
        }
    }

    public function handleTicketCreated($event, $ticket, $status, $ticketAttributes)
    {
        $this->TicketControllerLogger->debug(__FUNCTION__ . ' - input data', [
            'ticket' => $ticket,
            'status' => $status,
            'memberId' => $this->memberId,
            'ticketAttributes' => $ticketAttributes
        ]);

        // we need collect necessary data and the run bp
        $arTemplateParameters = [
            'eventType' => 'notificationCreateTicket',
            'ticketStatus' => $status,
            'ticketNumber' => $this->Bx24::TICKET_PREFIX . $ticket['id'],
            'ticketSubject' => $ticketAttributes['subject'],
            'ticketResponsibleId' => 'user_' . $ticketAttributes['responsible'],
            'answerType' => '',
            'sourceType' => $ticket['source_type_id']
        ];

        $this->TicketControllerLogger->debug(__FUNCTION__ . ' - workflow parameters', [
            'arTemplateParameters' => $arTemplateParameters
        ]);

        $this->Options = $this->getTableLocator()->get('HelpdeskOptions');
        $entityTypeId = intval($ticketAttributes['ENTITY_TYPE_ID']);
        $arOption = $this->Options->getOption('notificationCreateTicket' . Bx24Component::MAP_ENTITIES[$entityTypeId], $this->memberId);
        $templateId = intval($arOption['value']);

        $this->TicketControllerLogger->debug(__FUNCTION__ . ' - template', [
            'templateId' => $templateId
        ]);

        switch($entityTypeId)
        {
            case Bx24Component::OWNER_TYPE_DEAL:
                $entityId = intval($ticketAttributes['customer']['id']);
                break;

            case Bx24Component::OWNER_TYPE_CONTACT:
                $entityId = intval($ticketAttributes['customer']['id']);
                break;

            case Bx24Component::OWNER_TYPE_COMPANY:
                $entityId = intval($ticketAttributes['customer']['id']);
                break;

            default:
                $entityId = 0;
        }

        $this->TicketControllerLogger->debug(__FUNCTION__ . ' - entity', [
            'entityId' => $entityId,
            'entityType' => Bx24Component::MAP_ENTITIES[$entityTypeId]
        ]);

        if($templateId && $entityId)
        {
            $arResultStartWorkflow = $this->Bx24->startWorkflowFor($templateId, $entityId, $entityTypeId, $arTemplateParameters);
            $this->TicketControllerLogger->debug(__FUNCTION__ . ' - result', [
                'arResultStartWorkflow' => $arResultStartWorkflow
            ]);
        } else {
            $this->TicketControllerLogger->debug(__FUNCTION__ . ' - Missing required data to start workflow');
        }
    }
}