<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Query;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * TicketBindings Model
 *
 * @method \App\Model\Entity\TicketBinding newEmptyEntity()
 * @method \App\Model\Entity\TicketBinding newEntity(array $data, array $options = [])
 * @method \App\Model\Entity\TicketBinding[] newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\TicketBinding get($primaryKey, $options = [])
 * @method \App\Model\Entity\TicketBinding findOrCreate($search, ?callable $callback = null, $options = [])
 * @method \App\Model\Entity\TicketBinding patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method \App\Model\Entity\TicketBinding[] patchEntities(iterable $entities, array $data, array $options = [])
 * @method \App\Model\Entity\TicketBinding|false save(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \App\Model\Entity\TicketBinding saveOrFail(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \App\Model\Entity\TicketBinding[]|\Cake\Datasource\ResultSetInterface|false saveMany(iterable $entities, $options = [])
 * @method \App\Model\Entity\TicketBinding[]|\Cake\Datasource\ResultSetInterface saveManyOrFail(iterable $entities, $options = [])
 * @method \App\Model\Entity\TicketBinding[]|\Cake\Datasource\ResultSetInterface|false deleteMany(iterable $entities, $options = [])
 * @method \App\Model\Entity\TicketBinding[]|\Cake\Datasource\ResultSetInterface deleteManyOrFail(iterable $entities, $options = [])
 *
 * @mixin \Cake\ORM\Behavior\TimestampBehavior
 */
class TicketBindingsTable extends Table
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

        $this->setTable('ticket_bindings');
        $this->setDisplayField('id');
        $this->setPrimaryKey('id');

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
            ->integer('activity_id')
            ->requirePresence('activity_id', 'create')
            ->notEmptyString('activity_id');

        $validator
            ->integer('entity_id')
            ->requirePresence('entity_id', 'create')
            ->notEmptyString('entity_id');

        $validator
            ->integer('entity_type_id')
            ->requirePresence('entity_type_id', 'create')
            ->notEmptyString('entity_type_id');

        return $validator;
    }

    public function create($activityId, $entityId, $entityTypeId)
    {
        $entity = $this->newEntity([
            'activity_id' => $activityId,
            'entity_id' => $entityId,
            'entity_type_id' => $entityTypeId
        ]);
        if (!$entity->hasErrors()) {
            $this->save($entity);
        }
        return $entity;
    }

    public function deleteIfExists($activityId, $entityId, $entityTypeId)
    {
        $row = $this->find()->where(['entity_id' => $entityId, 'entity_type_id' => $entityTypeId])->first();
        if($row)
        {
            return $this->delete($row);
        } else {
            return false;
        }
    }

    public function getBindingsByEntityIdAndEntityTypeId($entityId, $entityTypeId)
    {
        return $this->find()->where(['entity_id' => $entityId, 'entity_type_id' => $entityTypeId])->toArray();
    }
}
