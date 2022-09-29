<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Query;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

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
            ->integer('category_id')
            ->requirePresence('category_id', 'create')
            ->notEmptyString('category_id');

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

    public function create(string $memberId, array $activity, int $categoryId, int $statusId, $prevActivityId)
    {
        $entity = $this->newEntity([
            'status_id' => $statusId,
            'category_id' => $categoryId,
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

    public function getLatestID()
    {
        $record = $this->find()
            ->select(['id'])
            ->orderDesc('created')
            ->orderDesc('id')
            ->first();
        return $record ? $record['id'] : 0;
    }

    public function getTicketsFor(string $memberId)
    {
        return $this->find()
            ->where(['member_id' => $memberId])
            ->toList();
    }

    public function editTicket(int $id, int $statusId, int $categoryId, string $memberId)
    {
        $insert = [
            'member_id' => $memberId,
            'status_id' => $statusId,
            'category_id' => $categoryId,
        ];
        if($id < 1)
        {
            $ticket = $this->newEntity($insert);
        } else {
            $ticket = $this->get($id);
            $ticket = $this->patchEntity($ticket, $insert);
        }
        $this->save($ticket);
        return [
            'id' => $ticket->id,
            'member_id' => $ticket->member_id,
            'status_id' => $ticket->status_id,
            'category_id' => $ticket->category_id,
        ];
    }

    public function getByActivityIdAndMemberId($activityId, $memberId)
    {
        return $this->find()
            ->where([
                'action_id' => $activityId,
                'member_id' => $memberId
            ])
            ->first();
    }
}