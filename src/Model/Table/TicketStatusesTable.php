<?php
declare(strict_types=1);

namespace App\Model\Table;

use Cake\ORM\Query;
use Cake\ORM\RulesChecker;
use Cake\ORM\Table;
use Cake\Validation\Validator;

/**
 * TicketStatuses Model
 *
 * @method \App\Model\Entity\TicketStatus newEmptyEntity()
 * @method \App\Model\Entity\TicketStatus newEntity(array $data, array $options = [])
 * @method \App\Model\Entity\TicketStatus[] newEntities(array $data, array $options = [])
 * @method \App\Model\Entity\TicketStatus get($primaryKey, $options = [])
 * @method \App\Model\Entity\TicketStatus findOrCreate($search, ?callable $callback = null, $options = [])
 * @method \App\Model\Entity\TicketStatus patchEntity(\Cake\Datasource\EntityInterface $entity, array $data, array $options = [])
 * @method \App\Model\Entity\TicketStatus[] patchEntities(iterable $entities, array $data, array $options = [])
 * @method \App\Model\Entity\TicketStatus|false save(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \App\Model\Entity\TicketStatus saveOrFail(\Cake\Datasource\EntityInterface $entity, $options = [])
 * @method \App\Model\Entity\TicketStatus[]|\Cake\Datasource\ResultSetInterface|false saveMany(iterable $entities, $options = [])
 * @method \App\Model\Entity\TicketStatus[]|\Cake\Datasource\ResultSetInterface saveManyOrFail(iterable $entities, $options = [])
 * @method \App\Model\Entity\TicketStatus[]|\Cake\Datasource\ResultSetInterface|false deleteMany(iterable $entities, $options = [])
 * @method \App\Model\Entity\TicketStatus[]|\Cake\Datasource\ResultSetInterface deleteManyOrFail(iterable $entities, $options = [])
 */
class TicketStatusesTable extends Table
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

        $this->setTable('ticket_statuses');
        $this->setDisplayField('name');
        $this->setPrimaryKey('id');
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
            ->scalar('name')
            ->maxLength('name', 255)
            ->requirePresence('name', 'create')
            ->notEmptyString('name');

        return $validator;
    }

    public function getStartStatusForMemberTickets(string $memberId)
    {
        return $this->find()
            ->where([
                'member_id' => $memberId,
                'active' => true,
            ])
            ->orderAsc('created')
            ->first();
    }

    public function getStatusesFor(string $memberId)
    {
        $rawList = $this->find()
            ->where([
                'member_id' => $memberId,
            ])
            ->all()
            ->toList();
        $list = [];
        foreach($rawList as $status)
        {
            $list[$status->id] = $status;
        }
        return $list;
    }

    public function editStatus($id = null, string $name, string $memberId, bool $active = true)
    {
        $insert = [
            'member_id' => $memberId,
            'name' => $name,
            'active' => (int)$active
        ];
        if($id == null)
        {
            $status = $this->newEntity($insert);
        } else {
            $status = $this->get($id);
            $status = $this->patchEntity($status, $insert);
        }
        $this->save($status);
        
        return [
            'id' => $status->id,
            'name' => $status->name,
            'active' => !!$status->active,
            'member_id' => $status->member_id,
        ];
    }
}
