<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Query;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * TicketHistory Model
 *
 * @property \App\Model\Table\TicketsTable&\Cake\ORM\Association\BelongsTo $Tickets
 * @property \App\Model\Table\EventTypesTable&\Cake\ORM\Association\BelongsTo $EventTypes
 *
 * @method \App\Model\Entity\TicketHistory newEmptyEntity()
 * @method \App\Model\Entity\TicketHistory newEntity(array $data, array $options = [])
 * @method \App\Model\Entity\TicketHistory[] newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\TicketHistory get($primaryKey, $options = [])
 * @method \App\Model\Entity\TicketHistory findOrCreate($search, ?callable $callback = null, $options = [])
 * @method \App\Model\Entity\TicketHistory patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method \App\Model\Entity\TicketHistory[] patchEntities(iterable $entities, array $data, array $options = [])
 * @method \App\Model\Entity\TicketHistory|false save(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \App\Model\Entity\TicketHistory saveOrFail(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \App\Model\Entity\TicketHistory[]|\Cake\Datasource\ResultSetInterface|false saveMany(iterable $entities, $options = [])
 * @method \App\Model\Entity\TicketHistory[]|\Cake\Datasource\ResultSetInterface saveManyOrFail(iterable $entities, $options = [])
 * @method \App\Model\Entity\TicketHistory[]|\Cake\Datasource\ResultSetInterface|false deleteMany(iterable $entities, $options = [])
 * @method \App\Model\Entity\TicketHistory[]|\Cake\Datasource\ResultSetInterface deleteManyOrFail(iterable $entities, $options = [])
 *
 * @mixin \Cake\ORM\Behavior\TimestampBehavior
 */
class TicketHistoryTable extends Table
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

        $this->setTable('ticket_history');
        $this->setDisplayField('id');
        $this->setPrimaryKey('id');

        $this->addBehavior('Timestamp');

        $this->belongsTo('Tickets', [
            'foreignKey' => 'ticket_id',
            'joinType' => 'INNER',
        ]);
        $this->belongsTo('EventTypes', [
            'foreignKey' => 'event_type_id',
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
            ->integer('ticket_id')
            ->requirePresence('ticket_id', 'create')
            ->notEmptyString('ticket_id');

        $validator
            ->integer('user_id')
            ->requirePresence('user_id', 'create')
            ->notEmptyString('user_id');

        $validator
            ->integer('event_type_id')
            ->requirePresence('event_type_id', 'create')
            ->notEmptyString('event_type_id');

        $validator
            ->scalar('old_value')
            ->maxLength('old_value', 255)
            ->allowEmptyString('old_value');

        $validator
            ->scalar('new_value')
            ->maxLength('new_value', 255)
            ->allowEmptyString('new_value');

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
        $rules->add($rules->existsIn('ticket_id', 'Tickets'), ['errorField' => 'ticket_id']);
        $rules->add($rules->existsIn('event_type_id', 'EventTypes'), ['errorField' => 'event_type_id']);

        return $rules;
    }

    public function create($ticketId, $userId, $eventTypeId, $oldValue = null, $newValue = null)
    {
        $entity = $this->newEntity([
            'ticket_id' => $ticketId,
            'user_id' => $userId,
            'event_type_id' => $eventTypeId,
            'old_value' => $oldValue,
            'new_value' => $newValue
        ]);
        if (!$entity->hasErrors()) {
            $this->save($entity);
        }
        return $entity;
    }

    public function getHistoryByTicketID($ticketID)
    {
        return $this->find()
            ->contain(['EventTypes'])
            ->where([
                'ticket_id' => $ticketID,
            ])
            ->order(['TicketHistory.id' => 'DESC'])->all()->toArray();
    }
}
