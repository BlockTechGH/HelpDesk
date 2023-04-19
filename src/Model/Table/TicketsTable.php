<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\Core\Configure;
use Cake\I18n\FrozenDate;
use Cake\I18n\FrozenTime;
use Cake\ORM\Query;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;
use Cake\Datasource\ConnectionManager;

/**
 * Tickets Model
 *
 * @property \App\Model\Table\TicketStatusesTable&\Cake\ORM\Association\BelongsTo $TicketStatuses
 * @property \App\Model\Table\TicketCategoriesTable&\Cake\ORM\Association\BelongsTo $TicketCategories
 *
 * @method \App\Model\Entity\Ticket newEmptyEntity()
 * @method \App\Model\Entity\Ticket newEntity(array $data, array $options = [])
 * @method \App\Model\Entity\Ticket[] newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\Ticket get($primaryKey, $options = [])
 * @method \App\Model\Entity\Ticket findOrCreate($search, ?callable $callback = null, $options = [])
 * @method \App\Model\Entity\Ticket patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method \App\Model\Entity\Ticket[] patchEntities(iterable $entities, array $data, array $options = [])
 * @method \App\Model\Entity\Ticket|false save(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \App\Model\Entity\Ticket saveOrFail(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \App\Model\Entity\Ticket[]|\Cake\Datasource\ResultSetInterface|false saveMany(iterable $entities, $options = [])
 * @method \App\Model\Entity\Ticket[]|\Cake\Datasource\ResultSetInterface saveManyOrFail(iterable $entities, $options = [])
 * @method \App\Model\Entity\Ticket[]|\Cake\Datasource\ResultSetInterface|false deleteMany(iterable $entities, $options = [])
 * @method \App\Model\Entity\Ticket[]|\Cake\Datasource\ResultSetInterface deleteManyOrFail(iterable $entities, $options = [])
 */
class TicketsTable extends Table
{
    public const PERIOD_MONTH = "month";
    public const PERIOD_DAY = "date";
    public const PERIOD_BETWEEN = "between";

    /**
     * Initialize method
     *
     * @param array $config The configuration for the Table.
     * @return void
     */
    public function initialize(array $config): void
    {
        parent::initialize($config);

        $this->setTable('tickets');
        $this->setDisplayField('id');
        $this->setPrimaryKey('id');

        $this->belongsTo('TicketStatuses', [
            'foreignKey' => 'status_id',
            'joinType' => 'INNER',
        ]);
        $this->belongsTo('TicketCategories', [
            'foreignKey' => 'category_id',
            'joinType' => 'INNER',
        ]);
        $this->hasMany('Resolutions')->setForeignKey('ticket_id')->setDependent(true);
        $this->addBehavior('Timestamp');
    }

    /**
     * Default validation rules.
     *
     * @param \Cake\Validation\Validator $validator Validator instance.
     * @return \Cake\Validation\Validator
     */
    public function validationDefault(Validator $validator): Validator
    {
        $validator
            ->integer('status_id')
            ->requirePresence('status_id', 'create')
            ->notEmptyString('status_id');

        $validator
            ->allowEmptyString('category_id')
            ->integer('category_id');

        $validator
            ->scalar('member_id')
            ->maxLength('member_id', 255)
            ->requirePresence('member_id', 'create')
            ->notEmptyString('member_id');

        $validator
            ->integer('action_id')
            ->notEmptyString('action_id');

        $validator
            ->scalar('source_type_id')
            ->requirePresence('source_type_id', 'create')
            ->notEmptyString('source_type_id');

        $validator
            ->scalar('source_id')
            ->maxLength('source_id', 255)
            ->requirePresence('source_id', 'create')
            ->notEmptyString('source_id');

        return $validator;
    }

    /**
     * Returns a rules checker object that will be used for validating
     * application integrity.
     *
     * @param \Cake\ORM\RulesChecker $rules The rules object to be modified.
     * @return \Cake\ORM\RulesChecker
     */
    public function buildRules(RulesChecker $rules): RulesChecker
    {
        $rules->add($rules->existsIn('status_id', 'TicketStatuses'), ['errorField' => 'status_id']);
        $rules->add($rules->existsIn('category_id', 'TicketCategories'), ['errorField' => 'category_id']);

        return $rules;
    }

    public function create(string $memberId, array $activity, $categoryId, int $statusId, $prevActivityId, $incidentCategoryId)
    {
        $entity = $this->newEntity([
            'status_id' => $statusId,
            'category_id' => $categoryId,
            'incident_category_id' => $incidentCategoryId,
            'member_id' => $memberId,
            'action_id' => $activity['ID'],
            'source_type_id' => $activity['PROVIDER_TYPE_ID'],
            'source_id' => $prevActivityId,
        ]);
        if (!$entity->hasErrors()) {
            $this->save($entity);
        }
        return $entity;
    }

    public function getNextID(): int
    {
        $connection = ConnectionManager::get('default');
        $tableName = $this->getTable();

        $result = $connection->execute("SELECT `AUTO_INCREMENT` FROM INFORMATION_SCHEMA.TABLES WHERE table_name = '{$tableName}'")->fetch('assoc');

        if(array_key_exists('AUTO_INCREMENT', $result))
        {
            return intval($result['AUTO_INCREMENT']);
        }

        return $this->getLatestID() + 1;
    }

    public function getLatestID()
    {
        $record = $this->find()
            ->select(['id'])
            ->orderDesc('created')
            ->orderDesc('id')
            ->first();
        return $record ? $record['id'] : 0;
    }

    public function getTicketsFor(
        string $memberId,
        array $filter = [], // custom filter
        array $sort = ['created' => 'desc'],
        array $pagination = [1, 10],
        // Support diapazone of date
        string $period = "month",
        string $from = null,
        string $to = null
    )
    {
        $where = $filter;
        $where['member_id'] = $memberId;
        $query = $this->find();
        foreach($sort as $field => $ord) {
            if ($ord == 'desk') {
                $query->orderDesc($field);
            } else {
                $query->orderAsc($field);
            }
        }
        if ($from) {
            if ($period == static::PERIOD_MONTH)
            {
                $parts = explode('/', $from);
                if(count($parts) == 2)
                {
                    $from = implode("/", [$parts[0], "01", $parts[1]]);
                }                
            }
            $from = new FrozenDate($from);
            $where['created >='] = $from;
        }
        if ($to) {
            $parts = explode('/', $to);
            if(count($parts) == 2)
            {
                $to = implode("/", [$parts[0], "01", $parts[1]]);
            }  
            $to = new FrozenDate("{$to}");
            $to = $to->modify('+ 1 day');
            $where['created <='] = $to;
        }
        if($period == static::PERIOD_DAY && $from)
        {
            $where['created <='] = $from->modify('+1 day');
        }
        if ($period == static::PERIOD_MONTH && $from)
        {
            $where['created >='] = $from->firstOfMonth();
            $where['created <='] = $from->modify('+ 1 month');
        }
        $query->where($where);
        $full = $query->all();
        $items = $full;        
            
        return [
            'rows' => $items->toArray(),
            'total' => $full->count(),
            'current' => $pagination[0],
            'rowCount' => $pagination[1]
        ];
    }

    public function calcIndicatorsForTickets(array $fullRowsList)
    {
        $summary = [];
        foreach($fullRowsList as $item)
        {
            if(!isset($summary[$item['status_id']]))
            {
                $summary[$item['status_id']] = 0;
            }
            $summary[$item['status_id']]++;
        }

        return $summary;
    }

    public function editTicket(int $id, int $statusId, $categoryId, string $memberId, $incidentCategoryId)
    {
        $insert = [
            'member_id' => $memberId,
            'status_id' => $statusId,
            'category_id' => $categoryId,
            'incident_category_id' => $incidentCategoryId
        ];
        if($id < 1)
        {
            $ticket = $this->newEntity($insert);
        } else {
            $ticket = $this->get($id);
            $ticket = $this->patchEntity($ticket, $insert);
        }
        $this->save($ticket);
        $result = $ticket->toArray();
        $result['errors'] = $ticket->getErrors();
        return $result;
    }

    public function getByActivityIdAndMemberId($activityId, $memberId)
    {
        return $this->find()
            ->where([
                'action_id' => $activityId,
                'member_id' => $memberId
            ])
            ->contain(['Resolutions' => [
                'sort' => ['id' => 'DESC']
            ]])
            ->first();
    }

    public function getByActivityIds($activityIds, $order = [])
    {
        if(!is_array($activityIds))
        {
            $activityIds = [$activityIds];
        }
        return $this->find()->where(['action_id IN' => $activityIds])->order($order)->all();
    }

    public function deleteTicketByActionId($activityId, $memberId)
    {
        $ticket = $this->getByActivityIdAndMemberId($activityId, $memberId);
        if($ticket)
        {
            return $this->delete($ticket);
        } else {
            return __('Ticket not exist');
        }
    }

    public function getTicketsExcludingStatusesAndExceedingDeadlineTime($deadlineTime, $statusIds, $memberId): array
    {
        return $this->find()->where([
            'member_id' => $memberId,
            'status_id NOT IN' => $statusIds,
            'created <' => $deadlineTime])
            ->toArray();
    }

    public function getTicketsIncludingStatusesAndExceedingDeadlineTime($deadlineTime, $statusIds, $memberId): array
    {
        return $this->find()->where([
            'member_id' => $memberId,
            'status_id IN' => $statusIds,
            'sla_notified !=' => 1, // only not notified
            'created <' => $deadlineTime])
            ->toArray();
    }

    public function changeTicketsStatus($ids, $statusId)
    {
        $tickets = $this->find()->where(['id IN' => $ids])->all();
        foreach($tickets as $ticket)
        {
            $ticket->status_id = $statusId;
        }

        $result = $this->saveMany($tickets);
        return $result ? true : false;
    }

    public function markAsSlaNotified($ids)
    {
        $tickets = $this->find()->where(['id IN' => $ids])->all();
        foreach($tickets as $ticket)
        {
            $ticket->sla_notified = 1;
        }

        $result = $this->saveMany($tickets);
        return $result ? true : false;
    }

    public function markAsViolated(\App\Model\Entity\Ticket $ticket)
    {
        $ticket->is_violated = 1;
        $ticket->violated_by = $ticket['responsibleId'];
        $ticket->violated = FrozenTime::now();

        $this->save($ticket);

        if($ticket->hasErrors())
        {
            return [
                'error' => true,
                'messages' => $ticket->getErrors()
            ];
        }

        return ['error' => false];
    }

    public function filterActivitiesByCategories($result, $paginationStart, $categoryId, $incidentCategoryId, $order)
    {
        if($categoryId || $incidentCategoryId)
        {
            $activities = [];

            $chunks = array_chunk($result['activities'], 50, true);

            if($categoryId)
            {
                $filter['category_id'] = $categoryId;
            }

            if($incidentCategoryId)
            {
                $filter['incident_category_id'] = $incidentCategoryId;
            }

            foreach($chunks as $chunk)
            {
                $filter['action_id IN'] = array_keys($chunk);

                $arResult = $this->find()
                    ->where($filter)
                    ->order($order)
                    ->toArray();
                foreach($arResult as $item)
                {
                    $activities[$item['action_id']] = $result['activities'][$item['action_id']];
                }
            }
            $total = count($activities);
            $activities = array_slice($activities, $paginationStart, 50, true);
        } else {
            $activities = array_slice($result['activities'], $paginationStart, 50, true);
            $total = $result['total'];
        }

        return [
            'activities' => $activities,
            'total' => $total
        ];
    }
}
